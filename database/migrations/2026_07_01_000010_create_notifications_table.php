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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('user_id');
            $table->string('title');
            $table->text('body');
            $table->string('type')->default('info'); // info, success, warning, error
            $table->string('category')->default('system'); // system, billing, crm, security
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->boolean('is_read')->default(false);
            $table->json('data')->nullable(); // Action payloads, links, etc.
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['organization_id', 'user_id']);
            $table->index(['is_read']);
            $table->index(['created_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('organization_id')->nullable();
            $table->json('channels'); // ['database' => true, 'email' => false, etc.]
            $table->json('categories'); // ['system' => true, 'billing' => true, etc.]
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('set null');

            $table->unique(['user_id', 'organization_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
