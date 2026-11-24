<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        if (! Schema::hasTable($prefix.'stock_requests')) {
            Schema::create($prefix.'stock_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_no')->unique();
                $table->unsignedBigInteger('from_location_id')->index();
                $table->unsignedBigInteger('to_location_id')->index();
                $table->string('status')->default('pending'); // pending, approved, dispatched, received, rejected
                $table->unsignedBigInteger('requested_by')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'stock_request_items')) {
            Schema::create($prefix.'stock_request_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_request_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->decimal('quantity_requested', 15, 4)->default(0.0000);
                $table->decimal('quantity_approved', 15, 4)->default(0.0000);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'stock_transfers')) {
            Schema::create($prefix.'stock_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('transfer_no')->unique();
                $table->unsignedBigInteger('from_location_id')->nullable()->index();
                $table->unsignedBigInteger('to_location_id')->nullable()->index();
                $table->unsignedBigInteger('from_outlet_id')->nullable()->index(); // Legacy alias
                $table->unsignedBigInteger('to_outlet_id')->nullable()->index();   // Legacy alias
                $table->string('status')->default('in_transit'); // in_transit, completed, cancelled
                $table->unsignedBigInteger('dispatched_by')->nullable()->index();
                $table->unsignedBigInteger('received_by')->nullable()->index();
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'stock_transfer_items')) {
            Schema::create($prefix.'stock_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_transfer_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('batch_id')->nullable()->index();
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->decimal('quantity_sent', 15, 4)->default(0.0000);
                $table->decimal('quantity_received', 15, 4)->default(0.0000);
                $table->decimal('variance', 15, 4)->default(0.0000);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'stock_transfer_reversals')) {
            Schema::create($prefix.'stock_transfer_reversals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_transfer_id')->index();
                $table->string('reversal_no')->unique();
                $table->string('status')->default('completed');
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('requested_by')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'stock_transfer_reversal_items')) {
            Schema::create($prefix.'stock_transfer_reversal_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('stock_transfer_reversal_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('batch_id')->nullable();
                $table->decimal('quantity_reversed', 15, 4)->default(0.0000);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');
        Schema::dropIfExists($prefix.'stock_transfer_reversal_items');
        Schema::dropIfExists($prefix.'stock_transfer_reversals');
        Schema::dropIfExists($prefix.'stock_transfer_items');
        Schema::dropIfExists($prefix.'stock_transfers');
        Schema::dropIfExists($prefix.'stock_request_items');
        Schema::dropIfExists($prefix.'stock_requests');
    }
};
