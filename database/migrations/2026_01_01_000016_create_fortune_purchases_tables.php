<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        // Purchase Orders
        if (! Schema::hasTable($prefix.'purchase_orders')) {
            Schema::create($prefix.'purchase_orders', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('purchase_number')->unique();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('supplier_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('status', 30)->default('pending'); // pending, approved, received, cancelled
                $table->decimal('subtotal_amount', 18, 2)->default(0);
                $table->decimal('tax_amount', 18, 2)->default(0);
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->date('purchase_date')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        // Purchase Order Items
        if (! Schema::hasTable($prefix.'purchase_order_items')) {
            Schema::create($prefix.'purchase_order_items', function (Blueprint $table) use ($prefix) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('product_variation_id')->nullable()->index();
                $table->unsignedBigInteger('unit_id')->nullable()->index();
                $table->decimal('conversion_factor', 18, 4)->default(1);
                $table->decimal('quantity_ordered', 18, 4)->default(0);
                $table->decimal('quantity_received', 18, 4)->default(0);
                $table->decimal('unit_cost', 18, 4)->default(0);
                $table->decimal('total_cost', 18, 2)->default(0);
                $table->timestamps();

                $table->foreign('purchase_order_id')->references('id')->on($prefix.'purchase_orders')->cascadeOnDelete();
            });
        }

        // Purchases / GRN Invoices
        if (! Schema::hasTable($prefix.'purchases')) {
            Schema::create($prefix.'purchases', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('invoice_number')->unique();
                $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('supplier_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('status', 30)->default('received'); // received, paid, partial
                $table->decimal('subtotal_amount', 18, 2)->default(0);
                $table->decimal('tax_amount', 18, 2)->default(0);
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->decimal('amount_paid', 18, 2)->default(0);
                $table->date('invoice_date')->nullable();
                $table->date('due_date')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        // Purchase Items
        if (! Schema::hasTable($prefix.'purchase_items')) {
            Schema::create($prefix.'purchase_items', function (Blueprint $table) use ($prefix) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('purchase_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('product_variation_id')->nullable()->index();
                $table->unsignedBigInteger('unit_id')->nullable()->index();
                $table->unsignedBigInteger('batch_id')->nullable()->index();
                $table->decimal('conversion_factor', 18, 4)->default(1);
                $table->decimal('quantity', 18, 4);
                $table->decimal('unit_cost', 18, 4)->default(0);
                $table->decimal('total_cost', 18, 2)->default(0);
                $table->timestamps();

                $table->foreign('purchase_id')->references('id')->on($prefix.'purchases')->cascadeOnDelete();
            });
        }

        // Purchase Returns (Debit Note to Supplier)
        if (! Schema::hasTable($prefix.'purchase_returns')) {
            Schema::create($prefix.'purchase_returns', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('return_no')->unique();
                $table->unsignedBigInteger('purchase_id')->nullable()->index();
                $table->unsignedBigInteger('supplier_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->string('status', 30)->default('completed');
                $table->string('reason')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // Purchase Return Items
        if (! Schema::hasTable($prefix.'purchase_return_items')) {
            Schema::create($prefix.'purchase_return_items', function (Blueprint $table) use ($prefix) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('purchase_return_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('quantity_returned', 18, 4);
                $table->decimal('unit_cost', 18, 4)->default(0);
                $table->decimal('total_cost', 18, 2)->default(0);
                $table->timestamps();

                $table->foreign('purchase_return_id')->references('id')->on($prefix.'purchase_returns')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        Schema::dropIfExists($prefix.'purchase_return_items');
        Schema::dropIfExists($prefix.'purchase_returns');
        Schema::dropIfExists($prefix.'purchase_items');
        Schema::dropIfExists($prefix.'purchases');
        Schema::dropIfExists($prefix.'purchase_order_items');
        Schema::dropIfExists($prefix.'purchase_orders');
    }
};
