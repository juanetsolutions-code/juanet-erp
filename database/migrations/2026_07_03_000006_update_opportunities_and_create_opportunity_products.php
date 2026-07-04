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
        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->string('opportunity_number')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('source')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->date('actual_close_date')->nullable();
            $table->decimal('estimated_revenue', 15, 2)->default(0.00);
            $table->decimal('weighted_revenue', 15, 2)->default(0.00);
            $table->integer('win_probability')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('forecast_category')->default('pipeline'); // commit, best_case, pipeline, omitted
            $table->string('competitor')->nullable();
            $table->text('lost_reason')->nullable();
            $table->text('won_reason')->nullable();
            $table->string('sales_team')->nullable();
            
            // AI Readiness Placeholders
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->decimal('ai_win_probability_prediction', 5, 2)->nullable();
            $table->text('ai_next_best_action')->nullable();
            $table->string('ai_deal_health')->nullable();
            $table->text('ai_risk_detection')->nullable();
            $table->jsonb('ai_upsell_recommendations')->nullable();
        });

        Schema::create('crm_opportunity_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('opportunity_id');
            $table->uuid('product_id')->nullable();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->decimal('discount', 15, 2)->default(0.00);
            $table->decimal('tax', 15, 2)->default(0.00);
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->boolean('recurring_billing_flag')->default(false);
            $table->string('subscription_interval')->nullable(); // monthly, annual, etc.
            $table->boolean('manual_pricing_override')->default(false);
            $table->decimal('price_snapshot', 15, 2)->default(0.00);
            $table->integer('lock_version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('opportunity_id')
                ->references('id')
                ->on('crm_opportunities')
                ->onDelete('cascade');

            $table->index('organization_id');
            $table->index('opportunity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_opportunity_products');

        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->dropColumn([
                'opportunity_number',
                'description',
                'source',
                'expected_close_date',
                'actual_close_date',
                'estimated_revenue',
                'weighted_revenue',
                'win_probability',
                'currency',
                'forecast_category',
                'competitor',
                'lost_reason',
                'won_reason',
                'sales_team',
                'ai_confidence',
                'ai_win_probability_prediction',
                'ai_next_best_action',
                'ai_deal_health',
                'ai_risk_detection',
                'ai_upsell_recommendations'
            ]);
        });
    }
};
