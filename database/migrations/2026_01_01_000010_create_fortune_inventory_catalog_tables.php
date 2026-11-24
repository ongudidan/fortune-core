<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        if (! Schema::hasTable($prefix.'categories')) {
            Schema::create($prefix.'categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable()->index();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'brands')) {
            Schema::create($prefix.'brands', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable()->index();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'units')) {
            Schema::create($prefix.'units', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('short_name')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'products')) {
            Schema::create($prefix.'products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('sku')->nullable()->index();
                $table->string('barcode')->nullable()->index();
                $table->unsignedBigInteger('category_id')->nullable()->index();
                $table->unsignedBigInteger('brand_id')->nullable()->index();
                $table->unsignedBigInteger('unit_id')->nullable()->index();
                $table->unsignedBigInteger('base_unit_id')->nullable()->index();
                $table->unsignedBigInteger('reorder_unit_id')->nullable()->index();
                $table->decimal('cost_price', 15, 4)->default(0.0000);
                $table->decimal('selling_price', 15, 2)->default(0.00);
                $table->decimal('wholesale_price', 15, 2)->nullable();
                $table->decimal('reorder_level', 15, 2)->default(10.00);
                $table->boolean('has_variations')->default(false);
                $table->boolean('is_returnable_container')->default(false);
                $table->unsignedBigInteger('container_product_id')->nullable()->index();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'product_variations')) {
            Schema::create($prefix.'product_variations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('name');
                $table->string('sku')->nullable()->index();
                $table->string('barcode')->nullable()->index();
                $table->unsignedBigInteger('unit_id')->nullable()->index();
                $table->decimal('conversion_factor', 15, 4)->default(1.0000);
                $table->decimal('cost_price', 15, 4)->nullable();
                $table->decimal('selling_price', 15, 2)->nullable();
                $table->decimal('wholesale_price', 15, 2)->nullable();
                $table->unsignedBigInteger('empty_product_id')->nullable()->index();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'product_unit_conversions')) {
            Schema::create($prefix.'product_unit_conversions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('from_unit_id')->nullable()->index();
                $table->unsignedBigInteger('to_unit_id')->nullable()->index();
                $table->unsignedBigInteger('unit_id')->nullable()->index(); // Target pack unit
                $table->string('operator', 5)->default('*'); // * or /
                $table->decimal('factor', 15, 4)->default(1.0000);
                $table->decimal('wholesale_price', 15, 2)->nullable();
                $table->string('barcode')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');
        Schema::dropIfExists($prefix.'product_unit_conversions');
        Schema::dropIfExists($prefix.'product_variations');
        Schema::dropIfExists($prefix.'products');
        Schema::dropIfExists($prefix.'units');
        Schema::dropIfExists($prefix.'brands');
        Schema::dropIfExists($prefix.'categories');
    }
};
