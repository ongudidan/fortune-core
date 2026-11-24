<?php

namespace Ongudidan\FortuneCore\Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\FortuneCore\Modules\Inventory\Models\Purchase;
use Ongudidan\FortuneCore\Modules\Inventory\Models\PurchaseItem;
use Ongudidan\FortuneCore\Modules\Inventory\Models\PurchaseOrder;
use Ongudidan\FortuneCore\Modules\Inventory\Models\PurchaseOrderItem;
use Ongudidan\FortuneCore\Modules\Inventory\Models\PurchaseReturn;
use Ongudidan\FortuneCore\Modules\Inventory\Models\PurchaseReturnItem;
use Ongudidan\FortuneCore\Modules\Ledger\Services\LedgerService;
use Ongudidan\FortuneCore\Modules\Wallets\Services\WalletService;

class PurchaseService
{
    public function __construct(
        protected StockMovementService $stockMovementService,
        protected ?WalletService $walletService = null,
        protected ?LedgerService $ledgerService = null
    ) {
        $this->walletService = $walletService ?? (class_exists(WalletService::class) ? app(WalletService::class) : null);
        $this->ledgerService = $ledgerService ?? (class_exists(LedgerService::class) ? app(LedgerService::class) : null);
    }

    /**
     * Create a Purchase Order.
     *
     * @param  array{
     *     supplier_id: int,
     *     location_id?: int|null,
     *     company_id?: int|null,
     *     branch_id?: int|null,
     *     purchase_date?: string|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         product_id: int,
     *         product_variation_id?: int|null,
     *         unit_id?: int|null,
     *         quantity_ordered: float,
     *         unit_cost: float,
     *         conversion_factor?: float
     *     }>
     * }  $data
     */
    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $purchaseNumber = 'PO-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -5));
            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw new \InvalidArgumentException('Purchase order items cannot be empty.');
            }

            $subtotal = 0.0;
            foreach ($itemsData as $item) {
                $qty = (float) ($item['quantity_ordered'] ?? 0);
                $cost = (float) ($item['unit_cost'] ?? 0);
                $subtotal += round($qty * $cost, 2);
            }

            $taxAmount = (float) ($data['tax_amount'] ?? 0);
            $totalAmount = round($subtotal + $taxAmount, 2);

            $po = PurchaseOrder::create([
                'purchase_number' => $purchaseNumber,
                'supplier_id' => (int) $data['supplier_id'],
                'location_id' => ! empty($data['location_id']) ? (int) $data['location_id'] : null,
                'company_id' => $data['company_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'status' => 'pending',
                'subtotal_amount' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'purchase_date' => $data['purchase_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($itemsData as $item) {
                $qty = (float) $item['quantity_ordered'];
                $cost = (float) $item['unit_cost'];
                $totalCost = round($qty * $cost, 2);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => (int) $item['product_id'],
                    'product_variation_id' => $item['product_variation_id'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'conversion_factor' => (float) ($item['conversion_factor'] ?? 1.0),
                    'quantity_ordered' => $qty,
                    'quantity_received' => 0,
                    'unit_cost' => $cost,
                    'total_cost' => $totalCost,
                ]);
            }

            return $po->load('items.product');
        });
    }

    /**
     * Receive goods from a supplier (GRN / Supplier Intake).
     * Atomically adds stock, creates FIFO batches, records cost movements,
     * updates PO fulfillment, and posts Debit 1300 (Asset) / Credit 2050 (GRNI Clearing).
     */
    public function receiveGoods(PurchaseOrder|array $data, ?int $locationId = null, ?int $userId = null): Purchase
    {
        return DB::transaction(function () use ($data, $locationId, $userId) {
            $invoiceNumber = 'GRN-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -5));
            $userId = $userId ?: auth()->id();

            $po = $data instanceof PurchaseOrder ? $data : null;
            $supplierId = $po ? $po->supplier_id : (int) $data['supplier_id'];
            $locId = $locationId ?: ($po ? $po->location_id : (int) ($data['location_id'] ?? 1));

            $itemsToReceive = $po ? $po->items : ($data['items'] ?? []);
            if (empty($itemsToReceive)) {
                throw new \InvalidArgumentException('No items provided for goods receipt.');
            }

            $subtotal = 0.0;
            foreach ($itemsToReceive as $item) {
                $qty = (float) (is_array($item) ? ($item['quantity'] ?? $item['quantity_ordered']) : ($item->quantity_ordered - $item->quantity_received));
                $cost = (float) (is_array($item) ? $item['unit_cost'] : $item->unit_cost);
                $subtotal += round($qty * $cost, 2);
            }

            $purchase = Purchase::create([
                'invoice_number' => $invoiceNumber,
                'purchase_order_id' => $po?->id,
                'location_id' => $locId,
                'supplier_id' => $supplierId,
                'company_id' => $po?->company_id ?? ($data['company_id'] ?? null),
                'branch_id' => $po?->branch_id ?? ($data['branch_id'] ?? null),
                'status' => 'received',
                'subtotal_amount' => $subtotal,
                'tax_amount' => 0,
                'total_amount' => $subtotal,
                'amount_paid' => 0,
                'invoice_date' => now()->toDateString(),
                'notes' => $po ? "GRN for Purchase Order {$po->purchase_number}" : ($data['notes'] ?? 'Supplier Goods Receipt'),
                'created_by' => $userId,
            ]);

            foreach ($itemsToReceive as $item) {
                $productId = (int) (is_array($item) ? $item['product_id'] : $item->product_id);
                $qty = (float) (is_array($item) ? ($item['quantity'] ?? $item['quantity_ordered']) : ($item->quantity_ordered - $item->quantity_received));
                $cost = (float) (is_array($item) ? $item['unit_cost'] : $item->unit_cost);
                $unitId = is_array($item) ? ($item['unit_id'] ?? null) : $item->unit_id;
                $conversionFactor = (float) (is_array($item) ? ($item['conversion_factor'] ?? 1.0) : $item->conversion_factor);

                if ($qty <= 0) {
                    continue;
                }

                $totalCost = round($qty * $cost, 2);

                // Intake stock + create batch + GL debit 1300 / credit 2050
                $applied = $this->stockMovementService->applyPurchase(
                    productId: $productId,
                    quantity: $qty,
                    unitCost: $cost,
                    locationId: $locId,
                    reference: $purchase->invoice_number,
                    unitId: $unitId,
                    conversionFactor: $conversionFactor,
                    notes: "Intake for {$purchase->invoice_number}",
                    userId: $userId
                );

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $productId,
                    'product_variation_id' => is_array($item) ? ($item['product_variation_id'] ?? null) : $item->product_variation_id,
                    'unit_id' => $unitId,
                    'batch_id' => $applied->batch?->id,
                    'conversion_factor' => $conversionFactor,
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'total_cost' => $totalCost,
                ]);

                // Update PO item received quantity
                if ($po && ! is_array($item)) {
                    $item->increment('quantity_received', $qty);
                }
            }

            // Update PO status
            if ($po) {
                $allReceived = $po->items()->whereRaw('quantity_received < quantity_ordered')->doesntExist();
                $po->update(['status' => $allReceived ? 'received' : 'partial']);
            }

            return $purchase->load(['items.product', 'location']);
        });
    }

    /**
     * Settle a Supplier Invoice / GRN.
     * Reduces AP / GRNI clearing against Bank, Cash Vault, or Supplier Advance prepayment.
     */
    public function settleSupplierInvoice(Purchase $purchase, float $amount, string $paymentMethod = 'bank', ?string $reference = null): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Settlement amount must be greater than zero.');
        }

        DB::transaction(function () use ($purchase, $amount, $paymentMethod, $reference) {
            $currentPaid = (float) $purchase->amount_paid;
            $newPaid = round($currentPaid + $amount, 2);
            $totalAmount = (float) $purchase->total_amount;

            $status = $newPaid >= $totalAmount ? 'paid' : 'partial';
            $purchase->update([
                'amount_paid' => $newPaid,
                'status' => $status,
            ]);

            // If settled using existing supplier advance prepayments
            if ($paymentMethod === 'advance' && $purchase->supplier_id && $this->walletService) {
                $this->walletService->debitSupplierAdvance(
                    supplierId: $purchase->supplier_id,
                    amount: $amount,
                    reference: $reference ?: $purchase->invoice_number,
                    description: "Advance offset for GRN {$purchase->invoice_number}"
                );

                return;
            }

            // Post double-entry settlement: Debit 2050 (GRNI) / 2000 (AP), Credit 1010 (Bank) / 1001 (Cash)
            if (config('fortune.modules.ledger', true) && $this->ledgerService) {
                $liabilityAccount = config('fortune.inventory.accounts.grni_clearing', '2050');
                $creditAccount = $paymentMethod === 'cash'
                    ? config('fortune.accounts.main_vault', '1001')
                    : config('fortune.accounts.bank_operating', '1010');

                $this->ledgerService->postJournal([
                    [
                        'account_code' => $liabilityAccount,
                        'debit' => $amount,
                        'credit' => 0.00,
                        'entry_type' => 'SupplierPayment',
                        'supplier_id' => $purchase->supplier_id,
                        'reference' => $reference ?: $purchase->invoice_number,
                        'description' => "Payment for GRN {$purchase->invoice_number}",
                    ],
                    [
                        'account_code' => $creditAccount,
                        'debit' => 0.00,
                        'credit' => $amount,
                        'entry_type' => 'SupplierPayment',
                        'supplier_id' => $purchase->supplier_id,
                        'reference' => $reference ?: $purchase->invoice_number,
                        'description' => "Disbursement for {$purchase->invoice_number}",
                    ],
                ]);
            }
        });
    }

    /**
     * Process return of goods back to supplier (Debit Note).
     * Deducts physical stock and batch quantities, and reverses AP/GRNI liability.
     *
     * @param  array<int, array{product_id: int, quantity_returned: float, unit_cost: float}>  $items
     */
    public function processPurchaseReturn(Purchase $purchase, array $items, string $reason = ''): PurchaseReturn
    {
        return DB::transaction(function () use ($purchase, $items, $reason) {
            $returnNo = 'DN-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -5));
            $totalAmount = 0.0;

            $purchaseReturn = PurchaseReturn::create([
                'return_no' => $returnNo,
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'location_id' => $purchase->location_id,
                'total_amount' => 0,
                'status' => 'completed',
                'reason' => $reason ?: 'Supplier return / Debit note',
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $qty = (float) $item['quantity_returned'];
                $unitCost = (float) $item['unit_cost'];
                $lineTotal = round($qty * $unitCost, 2);
                $totalAmount += $lineTotal;

                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'product_id' => $productId,
                    'quantity_returned' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineTotal,
                ]);

                // Deduct physical stock
                $this->stockMovementService->applyAdjustment(
                    productId: $productId,
                    quantityChange: -$qty,
                    locationId: (int) $purchase->location_id,
                    movementType: 'purchase_return',
                    referenceType: 'PUR_RET',
                    referenceId: $purchaseReturn->id,
                    notes: "Return to supplier for {$purchase->invoice_number}",
                    customUnitCost: $unitCost
                );
            }

            $purchaseReturn->update(['total_amount' => $totalAmount]);

            return $purchaseReturn->load('items');
        });
    }
}
