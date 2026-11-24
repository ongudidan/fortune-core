<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        if (! Schema::hasTable($prefix.'sms_providers')) {
            Schema::create($prefix.'sms_providers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'sms_configs')) {
            Schema::create($prefix.'sms_configs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('sms_provider_id')->nullable();
                $table->string('sender_id')->nullable();
                $table->text('api_key')->nullable();
                $table->string('partner_id')->nullable();
                $table->integer('alert_threshold')->default(100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'sms_templates')) {
            Schema::create($prefix.'sms_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name');
                $table->text('content');
                $table->string('trigger_type')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'system_sms_templates')) {
            Schema::create($prefix.'system_sms_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('event_type')->index();
                $table->text('content');
                $table->boolean('is_active')->default(true);
                $table->integer('schedule_hours')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'sms_campaigns')) {
            Schema::create($prefix.'sms_campaigns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('sms_config_id')->nullable();
                $table->unsignedBigInteger('sms_template_id')->nullable();
                $table->string('title');
                $table->text('message');
                $table->integer('total_recipients')->default(0);
                $table->integer('sent_count')->default(0);
                $table->integer('delivered_count')->default(0);
                $table->integer('failed_count')->default(0);
                $table->string('status')->default('draft');
                $table->boolean('is_scheduled')->default(false);
                $table->dateTime('scheduled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'sms_logs')) {
            Schema::create($prefix.'sms_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('sms_config_id')->nullable();
                $table->unsignedBigInteger('sms_template_id')->nullable();
                $table->unsignedBigInteger('system_sms_template_id')->nullable();
                $table->unsignedBigInteger('sms_campaign_id')->nullable();
                $table->string('phone_number')->index();
                $table->string('network')->nullable();
                $table->text('message');
                $table->string('message_id')->nullable()->index();
                $table->string('status')->default('pending');
                $table->string('delivery_status')->default('pending');
                $table->decimal('cost', 10, 4)->default(0.0000);
                $table->json('provider_response')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        Schema::dropIfExists($prefix.'sms_logs');
        Schema::dropIfExists($prefix.'sms_campaigns');
        Schema::dropIfExists($prefix.'system_sms_templates');
        Schema::dropIfExists($prefix.'sms_templates');
        Schema::dropIfExists($prefix.'sms_configs');
        Schema::dropIfExists($prefix.'sms_providers');
    }
};
