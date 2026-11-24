<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Product;
use Ongudidan\FortuneCore\Modules\Inventory\Models\ProductInventory;
use Ongudidan\FortuneCore\Modules\Inventory\Models\ProductVariation;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Sale;
use Ongudidan\FortuneCore\Modules\Inventory\Models\SaleItem;
use Ongudidan\FortuneCore\Modules\Inventory\Models\SaleReturn;
use Ongudidan\FortuneCore\Modules\Inventory\Models\SaleReturnItem;
use Ongudidan\FortuneCore\Modules\Ledger\Services\LedgerService;
use Ongudidan\FortuneCore\Modules\Wallets\Services\WalletService;

class SaleService
{
    public function __construct(
        protected StockMovementService $stockMovementService,
        protected ?EmptyContainerService $emptyContainerService = null,
        protected ?WalletService $walletService = null,
        protected ?LedgerService $ledgerService = null
    ) {
        $this->emptyContainerService = $emptyContainerService ?? (class_exists(EmptyContainerService::class) ? app(EmptyContainerService::class) : null);
        $this->walletService = $walletService ?? (class_exists(WalletService::class) ? app(WalletService::class) : null);
        $this->ledgerService = $ledgerService ?? (class_exists(LedgerService::class) ? app(LedgerService::class) : null);
    }

    /**
     * Create a new wholesale or retail sale.
     * Deducts stock atomically, evaluates empty container yield,
     * settles multi-tender payments, and posts balanced double-entry GL lines.
     *
     * @param  array{
     *     location_id: int,
     *     customer_id?: int|null,
     *     user_id?: int|null,
     *     company_id?: int|null,
     *     branch_id?: int|null,
     *     invoice_number?: string|null,
     *     items: array<int, array{
     *         product_id: int,
     *         product_variation_id?: int|null,
     *         unit_id?: int|null,
     *         quantity: float,
     *         conversion_factor?: float,
     *         unit_price: float,
     *         unit_cost?: float|null,
     *         tax_amount?: float,
     *         discount_amount?: float
     *     }>,
     *     cash_paid?: float,
     *     mpesa_paid?: float,
     *     wallet_paid?: float,
     *     payment_method?: string,
     *     sale_type?: string,
     *     mpesa_reference?: string|null,
     *     notes?: string|null,
     *     discount_amount?: float,
     *     tax_amount?: float
     * }  $data
     */
    public function createSale(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $locationId = (int) $data['location_id'];
            $customerId = ! empty($data['customer_id']) ? (int) $data['customer_id'] : null;
            $userId = ! empty($data['user_id']) ? (int) $data['user_id'] : auth()->id();
            $invoiceNumber = $data['invoice_number'] ?? ('INV-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -5)));

            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw new \InvalidArgumentException('Sale items cannot be empty.');
            }

            // Calculate subtotals
            $subtotal = 0.0;
            $itemTaxTotal = 0.0;
            $itemDiscountTotal = 0.0;

            foreach ($itemsData as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['unit_price'] ?? 0);
                $tax = (float) ($item['tax_amount'] ?? 0);
                $discount = (float) ($item['discount_amount'] ?? 0);

                $subtotal += round($qty * $price, 2);
                $itemTaxTotal += $tax;
                $itemDiscountTotal += $discount;
            }

            $overallDiscount = (float) ($data['discount_amount'] ?? 0) + $itemDiscountTotal;
            $overallTax = (float) ($data['tax_amount'] ?? 0) + $itemTaxTotal;
            $totalAmount = max(0.0, round($subtotal - $overallDiscount + $overallTax, 2));

            $cashPaid = (float) ($data['cash_paid'] ?? 0);
            $mpesaPaid = (float) ($data['mpesa_paid'] ?? 0);
            $walletPaid = (float) ($data['wallet_paid'] ?? 0);
            $totalPaid = round($cashPaid + $mpesaPaid + $walletPaid, 2);

            $changeAmount = max(0.0, round($totalPaid - $totalAmount, 2));
            $paymentMethod = $data['payment_method'] ?? ($walletPaid > 0 && ($cashPaid > 0 || $mpesaPaid > 0) ? 'split' : ($mpesaPaid > 0 ? 'mpesa' : ($walletPaid > 0 ? 'wallet' : 'cash')));
            $saleType = $data['sale_type'] ?? ($totalPaid < $totalAmount ? 'credit' : 'debit');

            $paymentStatus = 'paid';
            if ($totalPaid <= 0) {
                $paymentStatus = 'pending';
            } elseif ($totalPaid < $totalAmount) {
                $paymentStatus = 'partial';
            }

            // Create Sale record
            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'location_id' => $locationId,
                'customer_id' => $customerId,
                'user_id' => $userId,
                'company_id' => $data['company_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $overallDiscount,
                'tax_amount' => $overallTax,
                'total_amount' => $totalAmount,
                'cash_paid' => $cashPaid,
                'mpesa_paid' => $mpesaPaid,
                'wallet_paid' => $walletPaid,
                'change_amount' => $changeAmount,
                'payment_status' => $paymentStatus,
                'status' => 'completed',
                'payment_method' => $paymentMethod,
                'sale_type' => $saleType,
                'mpesa_reference' => $data['mpesa_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Process Items and Stock Movements
            foreach ($itemsData as $item) {
                $productId = (int) $item['product_id'];
                $product = Product::findOrFail($productId);
                $variationId = ! empty($item['product_variation_id']) ? (int) $item['product_variation_id'] : null;
                $variation = $variationId ? ProductVariation::find($variationId) : null;
                $unitId = ! empty($item['unit_id']) ? (int) $item['unit_id'] : $product->unit_id;
                $conversionFactor = (float) ($item['conversion_factor'] ?? 1.0);
                $qty = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];

                // Get current unit cost
                $inventory = ProductInventory::where('product_id', $productId)
                    ->where(function ($q) use ($locationId) {
                        $q->where('location_id', $locationId)
                            ->orWhere('outlet_id', $locationId);
                    })->first();

                $unitCost = ! empty($item['unit_cost'])
                    ? (float) $item['unit_cost']
                    : ($inventory ? (float) $inventory->average_cost : (float) $product->cost_price);

                $totalPrice = round($qty * $unitPrice, 2);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $productId,
                    'product_variation_id' => $variationId,
                    'unit_id' => $unitId,
                    'quantity' => $qty,
                    'conversion_factor' => $conversionFactor,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'total_price' => $totalPrice,
                    'tax_amount' => (float) ($item['tax_amount'] ?? 0),
                    'discount_amount' => (float) ($item['discount_amount'] ?? 0),
                ]);

                // Apply stock deduction + COGS via StockMovementService
                $this->stockMovementService->applySale(
                    productId: $productId,
                    quantity: $qty,
                    locationId: $locationId,
                    reference: $sale->invoice_number,
                    unitId: $unitId,
                    conversionFactor: $conversionFactor,
                    notes: "Sale invoice {$sale->invoice_number}",
                    userId: $userId
                );

                // Yield returnable empty containers if enabled
                if (config('fortune.inventory.enable_empty_containers', true) && $this->emptyContainerService) {
                    $target = $variation ?: $product;
                    $this->emptyContainerService->processSaleYield(
                        locationId: $locationId,
                        productId: $productId,
                        variation: $target,
                        quantitySold: $qty,
                        customerId: $customerId,
                        referenceType: 'SALE',
                        referenceId: $sale->id,
                        userId: $userId,
                        notes: "Empty yield from {$sale->invoice_number}"
                    );
                }
            }

            // Settle wallet payment if used
            if ($walletPaid > 0 && $customerId && $this->walletService) {
                $this->walletService->debitCustomerWallet(
                    customerId: $customerId,
                    amount: $walletPaid,
                    reference: $sale->invoice_number,
                    description: "Payment for invoice {$sale->invoice_number}",
                    saleId: $sale->id,
                    targetCreditAccount: config('fortune.accounts.accounts_receivable', '1200')
                );
            }

            // Double-Entry General Ledger Posting for Revenue and Tenders
            if (config('fortune.modules.ledger', true) && $this->ledgerService && $totalAmount > 0) {
                $revenueAccount = config('fortune.accounts.sales_revenue', '4000');
                $cashAccount = config('fortune.accounts.main_vault', '1001');
                $mpesaAccount = config('fortune.accounts.mpesa_till', '1020');
                $arAccount = config('fortune.accounts.accounts_receivable', '1200');

                // Net cash received retained (deducting any change returned)
                $netCash = min($cashPaid, max(0.0, $totalAmount - $mpesaPaid - $walletPaid));
                $netMpesa = min($mpesaPaid, max(0.0, $totalAmount - $netCash - $walletPaid));

                // Balance remaining to be carried on customer AR (or covered by wallet transaction clearing AR)
                $arAmount = round($totalAmount - ($netCash + $netMpesa), 2);

                $journalLines = [];

                if ($netCash > 0) {
                    $journalLines[] = [
                        'account_code' => $cashAccount,
                        'debit' => $netCash,
                        'credit' => 0.00,
                        'entry_type' => 'CashSale',
                        'customer_id' => $customerId,
                        'reference' => $sale->invoice_number,
                        'description' => "Cash received for {$sale->invoice_number}",
                    ];
                }

                if ($netMpesa > 0) {
                    $journalLines[] = [
                        'account_code' => $mpesaAccount,
                        'debit' => $netMpesa,
                        'credit' => 0.00,
                        'entry_type' => 'MpesaSale',
                        'customer_id' => $customerId,
                        'reference' => $sale->invoice_number,
                        'description' => "M-Pesa payment for {$sale->invoice_number}",
                    ];
                }

                if ($arAmount > 0) {
                    $journalLines[] = [
                        'account_code' => $arAccount,
                        'debit' => $arAmount,
                        'credit' => 0.00,
                        'entry_type' => 'CreditSale',
                        'customer_id' => $customerId,
                        'reference' => $sale->invoice_number,
                        'description' => "Receivable for {$sale->invoice_number}",
                    ];
                }

                // Credit Sales Revenue for the complete sale total
                $journalLines[] = [
                    'account_code' => $revenueAccount,
                    'debit' => 0.00,
                    'credit' => $totalAmount,
                    'entry_type' => 'SaleRevenue',
                    'customer_id' => $customerId,
                    'reference' => $sale->invoice_number,
                    'description' => "Sales revenue for {$sale->invoice_number}",
                ];

                $this->ledgerService->postJournal($journalLines);
            }

            return $sale->load(['items.product', 'location']);
        });
    }

    /**
     * Process customer sale return (Return Inward).
     * Restores stock and batches, and posts GL reversal lines.
     *
     * @param  array<int, array{sale_item_id: int, quantity_returned: float}>  $items
     */
    public function processReturn(Sale $sale, array $items, string $reason = '', ?int $userId = null): SaleReturn
    {
        return DB::transaction(function () use ($sale, $items, $reason, $userId) {
            $returnNumber = 'RET-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -5));
            $totalRefund = 0.0;
            $userId = $userId ?: auth()->id();

            $saleReturn = SaleReturn::create([
                'return_number' => $returnNumber,
                'sale_id' => $sale->id,
                'location_id' => $sale->location_id,
                'customer_id' => $sale->customer_id,
                'total_refund_amount' => 0,
                'status' => 'completed',
                'reason' => $reason ?: 'Customer return',
                'processed_by' => $userId,
            ]);

            foreach ($items as $itemData) {
                $saleItem = SaleItem::where('sale_id', $sale->id)->findOrFail($itemData['sale_item_id']);
                $qtyReturned = (float) $itemData['quantity_returned'];
                if ($qtyReturned <= 0) {
                    continue;
                }

                $unitPrice = (float) $saleItem->unit_price;
                $refundAmount = round($qtyReturned * $unitPrice, 2);
                $totalRefund += $refundAmount;

                SaleReturnItem::create([
                    'sale_return_id' => $saleReturn->id,
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'quantity_returned' => $qtyReturned,
                    'unit_price' => $unitPrice,
                    'refund_amount' => $refundAmount,
                ]);

                // Restore stock via adjustment
                $this->stockMovementService->applyAdjustment(
                    productId: $saleItem->product_id,
                    quantityChange: $qtyReturned,
                    locationId: $sale->location_id,
                    movementType: 'return_inward',
                    referenceType: 'SALE_RET',
                    referenceId: $saleReturn->id,
                    unitId: $saleItem->unit_id,
                    conversionFactor: (float) $saleItem->conversion_factor,
                    notes: "Customer return inward for {$sale->invoice_number}",
                    userId: $userId,
                    customUnitCost: (float) $saleItem->unit_cost
                );
            }

            $saleReturn->update(['total_refund_amount' => $totalRefund]);

            // Post GL reversal for refund: Debit Sales Revenue/Return (4000), Credit Cash/AR (1001/1200)
            if (config('fortune.modules.ledger', true) && $this->ledgerService && $totalRefund > 0) {
                $revenueAccount = config('fortune.accounts.sales_returns', config('fortune.accounts.sales_revenue', '4000'));
                $settleAccount = $sale->customer_id
                    ? config('fortune.accounts.accounts_receivable', '1200')
                    : config('fortune.accounts.main_vault', '1001');

                $this->ledgerService->postJournal([
                    [
                        'account_code' => $revenueAccount,
                        'debit' => $totalRefund,
                        'credit' => 0.00,
                        'entry_type' => 'SaleReturn',
                        'customer_id' => $sale->customer_id,
                        'reference' => $saleReturn->return_number,
                        'description' => "Return refund for {$sale->invoice_number}",
                    ],
                    [
                        'account_code' => $settleAccount,
                        'debit' => 0.00,
                        'credit' => $totalRefund,
                        'entry_type' => 'SaleReturn',
                        'customer_id' => $sale->customer_id,
                        'reference' => $saleReturn->return_number,
                        'description' => "Return credit/refund for {$sale->invoice_number}",
                    ],
                ]);
            }

            return $saleReturn->load('items');
        });
    }
}
