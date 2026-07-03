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
        // 1. Transactional Outbox Table
        Schema::create('event_outboxes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            
            $table->string('event_name', 255);
            $table->string('event_type', 50); // queued, immediate, scheduled, webhook, internal
            $table->longText('payload'); // JSON payload of the event
            
            $table->string('status', 50)->default('pending'); // pending, processing, published, failed
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(5);
            $table->text('error_message')->nullable();
            
            $table->timestamp('scheduled_at')->nullable();
            $table->string('idempotency_key', 255)->nullable();
            $table->string('webhook_url', 500)->nullable();
            
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->index(['status', 'scheduled_at']);
            $table->index(['idempotency_key']);
            $table->index(['event_name']);
        });

        // 2. Dead Letter Queue (DLQ) Table
        Schema::create('event_dlqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('original_outbox_id')->nullable();
            
            $table->string('event_name', 255);
            $table->string('event_type', 50);
            $table->longText('payload');
            $table->text('failure_reason')->nullable();
            
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->index(['event_name']);
        });

        // 3. Idempotent Keys Table
        Schema::create('idempotent_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 255)->unique();
            $table->string('status', 50)->default('processing'); // processing, completed, failed
            $table->longText('result')->nullable(); // Cached response/result data if completed
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotent_keys');
        Schema::dropIfExists('event_dlqs');
        Schema::dropIfExists('event_outboxes');
    }
};
