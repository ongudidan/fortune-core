<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        if (! Schema::hasTable($prefix.'locations')) {
            Schema::create($prefix.'locations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable()->index();
                $table->string('type')->default('warehouse'); // warehouse, retail, van
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'product_inventories')) {
            Schema::create($prefix.'product_inventories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('outlet_id')->nullable()->index(); // Legacy alias
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->decimal('current_stock', 15, 4)->default(0.0000);
                $table->decimal('available_quantity', 15, 4)->default(0.0000);
                $table->decimal('reserved_stock', 15, 4)->default(0.0000);
                $table->decimal('reorder_level', 15, 4)->default(10.0000);
                $table->decimal('average_cost', 15, 4)->default(0.0000);
                $table->decimal('base_cost', 15, 4)->default(0.0000);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'inventory_batches')) {
            Schema::create($prefix.'inventory_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('outlet_id')->nullable()->index();
                $table->string('batch_number')->index();
                $table->decimal('cost_price', 15, 4)->default(0.0000);
                $table->decimal('selling_price', 15, 2)->nullable();
                $table->date('mfg_date')->nullable();
                $table->date('expiry_date')->nullable()->index();
                $table->decimal('initial_quantity', 15, 4)->default(0.0000);
                $table->decimal('current_quantity', 15, 4)->default(0.0000);
                $table->decimal('quantity_available', 15, 4)->default(0.0000);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'inventory_stock_movements')) {
            Schema::create($prefix.'inventory_stock_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('outlet_id')->nullable()->index();
                $table->unsignedBigInteger('batch_id')->nullable()->index();
                $table->decimal('quantity', 15, 4)->default(0.0000);
                $table->decimal('quantity_change', 15, 4)->default(0.0000);
                $table->decimal('running_balance', 15, 4)->default(0.0000);
                $table->string('movement_type')->index(); // purchase, sale, transfer_in, transfer_out, adjustment, return, write_off
                $table->string('reference_type')->nullable()->index();
                $table->string('reference_id')->nullable()->index();
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->decimal('conversion_factor', 15, 4)->default(1.0000);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('computer_used')->nullable();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'inventory_cost_movements')) {
            Schema::create($prefix.'inventory_cost_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('outlet_id')->nullable()->index();
                $table->unsignedBigInteger('batch_id')->nullable()->index();
                $table->decimal('unit_cost', 15, 4)->default(0.0000);
                $table->decimal('total_cost', 15, 4)->default(0.0000);
                $table->string('cost_type')->default('fifo'); // fifo, avco
                $table->string('reference_type')->nullable()->index();
                $table->string('reference_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');
        Schema::dropIfExists($prefix.'inventory_cost_movements');
        Schema::dropIfExists($prefix.'inventory_stock_movements');
        Schema::dropIfExists($prefix.'inventory_batches');
        Schema::dropIfExists($prefix.'product_inventories');
        Schema::dropIfExists($prefix.'locations');
    }
};
