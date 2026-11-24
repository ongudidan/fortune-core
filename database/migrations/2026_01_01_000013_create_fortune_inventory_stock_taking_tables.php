<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        if (! Schema::hasTable($prefix.'stock_takings')) {
            Schema::create($prefix.'stock_takings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('outlet_id')->nullable()->index(); // Legacy alias
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->date('date')->index();
                $table->string('status')->default('draft'); // draft, in_progress, completed, approved
                $table->unsignedBigInteger('performed_by')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->decimal('total_variance_value', 15, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'stock_taking_items')) {
            Schema::create($prefix.'stock_taking_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_taking_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('book_quantity', 15, 4)->default(0.0000);
                $table->decimal('physical_quantity', 15, 4)->default(0.0000);
                $table->decimal('variance_quantity', 15, 4)->default(0.0000);
                $table->decimal('cost_price', 15, 4)->default(0.0000);
                $table->decimal('variance_value', 15, 2)->default(0.00);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'stock_taking_item_breakdowns')) {
            Schema::create($prefix.'stock_taking_item_breakdowns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_taking_item_id')->index();
                $table->unsignedBigInteger('unit_id')->nullable()->index();
                $table->decimal('packages_counted', 15, 4)->default(0.0000);
                $table->decimal('loose_units_counted', 15, 4)->default(0.0000);
                $table->decimal('normalized_quantity', 15, 4)->default(0.0000);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'inventory_stock_adjustments')) {
            Schema::create($prefix.'inventory_stock_adjustments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_taking_id')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('outlet_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('quantity', 15, 4)->default(0.0000);
                $table->string('type')->default('addition'); // addition, deduction
                $table->decimal('unit_cost', 15, 4)->default(0.0000);
                $table->decimal('total_value', 15, 2)->default(0.00);
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'inventory_write_offs')) {
            Schema::create($prefix.'inventory_write_offs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('outlet_id')->nullable()->index();
                $table->string('reference_no')->unique();
                $table->decimal('total_loss_value', 15, 2)->default(0.00);
                $table->string('status')->default('completed'); // draft, approved, completed
                $table->string('reason')->nullable(); // spoilage, breakage, expiry
                $table->unsignedBigInteger('requested_by')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'inventory_write_off_batches')) {
            Schema::create($prefix.'inventory_write_off_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventory_write_off_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('batch_id')->nullable()->index();
                $table->decimal('quantity', 15, 4)->default(0.0000);
                $table->decimal('unit_cost', 15, 4)->default(0.0000);
                $table->decimal('total_cost', 15, 2)->default(0.00);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');
        Schema::dropIfExists($prefix.'inventory_write_off_batches');
        Schema::dropIfExists($prefix.'inventory_write_offs');
        Schema::dropIfExists($prefix.'inventory_stock_adjustments');
        Schema::dropIfExists($prefix.'stock_taking_item_breakdowns');
        Schema::dropIfExists($prefix.'stock_taking_items');
        Schema::dropIfExists($prefix.'stock_takings');
    }
};
