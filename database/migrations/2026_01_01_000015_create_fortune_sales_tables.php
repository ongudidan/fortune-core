<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        // Sales / Orders
        if (! Schema::hasTable($prefix.'sales')) {
            Schema::create($prefix.'sales', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('invoice_number')->unique();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->decimal('subtotal', 18, 2)->default(0);
                $table->decimal('discount_amount', 18, 2)->default(0);
                $table->decimal('tax_amount', 18, 2)->default(0);
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->decimal('cash_paid', 18, 2)->default(0);
                $table->decimal('mpesa_paid', 18, 2)->default(0);
                $table->decimal('wallet_paid', 18, 2)->default(0);
                $table->decimal('change_amount', 18, 2)->default(0);
                $table->string('payment_status', 30)->default('paid'); // paid, partial, pending
                $table->string('status', 30)->default('completed'); // completed, pending, cancelled
                $table->string('payment_method', 50)->default('cash'); // cash, mpesa, wallet, split, credit
                $table->string('sale_type', 30)->default('debit'); // debit, credit
                $table->string('mpesa_reference')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // Sale Line Items
        if (! Schema::hasTable($prefix.'sale_items')) {
            Schema::create($prefix.'sale_items', function (Blueprint $table) use ($prefix) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sale_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('product_variation_id')->nullable()->index();
                $table->unsignedBigInteger('unit_id')->nullable()->index();
                $table->decimal('quantity', 18, 4);
                $table->decimal('conversion_factor', 18, 4)->default(1);
                $table->decimal('unit_price', 18, 2)->default(0);
                $table->decimal('unit_cost', 18, 4)->default(0);
                $table->decimal('total_price', 18, 2)->default(0);
                $table->decimal('tax_amount', 18, 2)->default(0);
                $table->decimal('discount_amount', 18, 2)->default(0);
                $table->timestamps();

                $table->foreign('sale_id')->references('id')->on($prefix.'sales')->cascadeOnDelete();
            });
        }

        // Sale Returns (Return Inward)
        if (! Schema::hasTable($prefix.'sale_returns')) {
            Schema::create($prefix.'sale_returns', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('return_number')->unique();
                $table->unsignedBigInteger('sale_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->decimal('total_refund_amount', 18, 2)->default(0);
                $table->string('status', 30)->default('completed');
                $table->string('reason')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->timestamps();
            });
        }

        // Sale Return Items
        if (! Schema::hasTable($prefix.'sale_return_items')) {
            Schema::create($prefix.'sale_return_items', function (Blueprint $table) use ($prefix) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sale_return_id')->index();
                $table->unsignedBigInteger('sale_item_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('quantity_returned', 18, 4);
                $table->decimal('unit_price', 18, 2)->default(0);
                $table->decimal('refund_amount', 18, 2)->default(0);
                $table->timestamps();

                $table->foreign('sale_return_id')->references('id')->on($prefix.'sale_returns')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        Schema::dropIfExists($prefix.'sale_return_items');
        Schema::dropIfExists($prefix.'sale_returns');
        Schema::dropIfExists($prefix.'sale_items');
        Schema::dropIfExists($prefix.'sales');
    }
};
