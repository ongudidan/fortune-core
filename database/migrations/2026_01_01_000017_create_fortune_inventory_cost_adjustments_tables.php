<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        if (! Schema::hasTable($prefix.'inventory_cost_adjustments')) {
            Schema::create($prefix.'inventory_cost_adjustments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('unit_id')->nullable()->index();
                $table->decimal('factor', 18, 4)->default(1);
                $table->decimal('old_unit_cost', 18, 4)->default(0);
                $table->decimal('new_unit_cost', 18, 4)->default(0);
                $table->decimal('current_stock', 18, 4)->default(0);
                $table->decimal('cost_difference', 18, 4)->default(0);
                $table->decimal('total_value_impact', 18, 2)->default(0);
                $table->string('reason')->nullable();
                $table->string('status', 30)->default('Approved'); // Pending, Approved, Rejected
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        Schema::dropIfExists($prefix.'inventory_cost_adjustments');
    }
};
