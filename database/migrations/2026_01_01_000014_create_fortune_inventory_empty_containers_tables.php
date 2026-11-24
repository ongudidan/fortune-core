<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        if (! Schema::hasTable($prefix.'empty_container_movements')) {
            Schema::create($prefix.'empty_container_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('outlet_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('empty_product_id')->index();
                $table->decimal('quantity_yielded', 15, 4)->default(0.0000);
                $table->decimal('quantity_returned', 15, 4)->default(0.0000);
                $table->decimal('quantity_change', 15, 4)->default(0.0000);
                $table->decimal('partial_yield_change', 15, 4)->default(0.0000);
                $table->decimal('balance_owed', 15, 4)->default(0.0000);
                $table->string('action')->nullable(); // sale_yield, customer_return, supplier_dispatch
                $table->string('movement_type')->default('creation'); // creation, return, deduction
                $table->string('reference_type')->nullable()->index();
                $table->string('reference_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'outlet_partial_empties')) {
            Schema::create($prefix.'outlet_partial_empties', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('outlet_id')->nullable()->index();
                $table->unsignedBigInteger('empty_product_id')->index();
                $table->decimal('accumulated_yield', 15, 4)->default(0.0000);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');
        Schema::dropIfExists($prefix.'outlet_partial_empties');
        Schema::dropIfExists($prefix.'empty_container_movements');
    }
};
