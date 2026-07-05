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
        Schema::create('crm_visitor_behavior_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUIDv7
            $table->uuid('visitor_id')->unique(); // 1-to-1 with Visitor
            $table->uuid('organization_id')->nullable(); // Tenant isolation

            // Engagement & Intent
            $table->integer('engagement_score')->default(0);
            $table->string('purchase_intent')->default('Low Intent');

            // Rich JSONB metrics (PostgreSQL 16 ready)
            $table->jsonb('service_interests')->nullable(); // {service_name: confidence_percentage}
            $table->jsonb('product_interests')->nullable(); // {product_name: {viewed: bool, bookmarked: bool, count: int}}
            $table->jsonb('content_intelligence')->nullable(); // {favorite_topics: [], reading_depth: float, preferred_categories: []}
            $table->jsonb('customer_value')->nullable(); // {estimated_deal_size: float, enterprise_prob: float, sme_prob: float, startup_prob: float, ltv: float, confidence: float}
            
            // Historical tracking
            $table->jsonb('score_history')->nullable(); // [{score: int, timestamp: string}]
            $table->text('timeline_summary')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('visitor_id')
                ->references('id')
                ->on('crm_visitors')
                ->onDelete('cascade');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->index('organization_id');
            $table->index('engagement_score');
            $table->index('purchase_intent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_visitor_behavior_profiles');
    }
};
