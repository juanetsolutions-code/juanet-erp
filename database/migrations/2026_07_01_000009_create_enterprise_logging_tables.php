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
        // 1. Activity Logs Table
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('action');
            $table->text('description')->nullable();
            $table->string('module')->default('core');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['organization_id', 'user_id']);
            $table->index(['module']);
            $table->index(['action']);
            $table->index(['created_at']);
        });

        // 2. Audit Logs Table
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('auditable_type');
            $table->uuid('auditable_id');
            $table->string('event'); // created, updated, deleted, etc.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['organization_id', 'user_id']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['event']);
            $table->index(['created_at']);
        });

        // 3. Security Logs Table
        Schema::create('security_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('event_type'); // login, failed_login, logout, password_change, api_token_created, etc.
            $table->string('severity', 20)->default('info'); // info, warning, critical
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['organization_id', 'user_id']);
            $table->index(['event_type']);
            $table->index(['severity']);
            $table->index(['created_at']);
        });

        // 4. Exception Logs Table
        Schema::create('exception_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('exception_class');
            $table->text('message');
            $table->longText('trace')->nullable();
            $table->text('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->text('url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['organization_id', 'user_id']);
            $table->index(['exception_class']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exception_logs');
        Schema::dropIfExists('security_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('activity_logs');
    }
};
