<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Notification Channels
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique(); // In-App, Email, SMS, WhatsApp, Push Notifications, Webhook
            $table->string('key')->unique();  // in_app, email, sms, whatsapp, push, webhook
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Notification Templates
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->string('name')->unique(); // e.g., crm.lead.created, proposal.accepted, etc.
            $table->string('subject')->nullable();
            $table->text('body_markdown');
            $table->text('body_html')->nullable();
            $table->json('channels')->nullable(); // channels enabled for this template by default
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });

        // 3. Notification Deliveries
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('notification_id');
            $table->string('channel'); // in_app, email, sms, whatsapp, push, webhook
            $table->string('recipient')->nullable();
            $table->string('status')->default('queued'); // queued, sent, delivered, opened, clicked, failed
            $table->text('error_message')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('notification_id')
                ->references('id')
                ->on('notifications')
                ->onDelete('cascade');
        });

        // 4. Notification Logs
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('event_name');
            $table->json('payload')->nullable();
            $table->string('status')->default('processed'); // processed, skipped, failed
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_channels');
    }
};
