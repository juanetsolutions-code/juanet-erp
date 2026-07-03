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
        // 1. Settings Table (handles Platform, Organization, and User Settings with encryption/type casts)
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 255);
            $table->longText('value')->nullable();
            
            $table->string('type', 50)->default('string'); // string, boolean, json, integer, float
            $table->string('group', 50)->default('platform'); // platform, organization, user
            $table->uuid('owner_id')->nullable(); // Can map to organization_id or user_id depending on group
            
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            $table->unique(['key', 'group', 'owner_id']);
            $table->index(['group', 'owner_id']);
            $table->index(['key']);
        });

        // 2. Feature Flags Table (handles Boolean Flags, Rollout Rules, Beta Features)
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 255)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false); // Global toggle state
            $table->boolean('is_beta')->default(false); // Gated behind beta access enrollments
            
            // Rules dictionary allowing complex targeting (e.g. tenant lists, user lists, rollout percentages)
            $table->json('rules')->nullable(); 
            
            $table->timestamps();

            $table->index(['key']);
        });

        // 3. Beta Feature Enrollments (Gating Organizations or Users to experimental features)
        Schema::create('beta_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('feature_flag_key', 255);
            $table->uuid('organization_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->timestamps();

            $table->foreign('feature_flag_key')
                ->references('key')
                ->on('feature_flags')
                ->onDelete('cascade');

            $table->unique(['feature_flag_key', 'organization_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beta_enrollments');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('settings');
    }
};
