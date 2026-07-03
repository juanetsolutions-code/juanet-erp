<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop existing crm_leads if it exists (from placeholder migration)
        Schema::dropIfExists('crm_leads');

        // 2. crm_industries
        Schema::create('crm_industries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('slug');
            $table->integer('lock_version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->index('organization_id');
            $table->unique(['organization_id', 'slug', 'deleted_at']);
        });

        // 3. crm_lead_sources
        Schema::create('crm_lead_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('slug');
            $table->integer('lock_version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->index('organization_id');
            $table->unique(['organization_id', 'slug', 'deleted_at']);
        });

        // 4. crm_companies
        Schema::create('crm_companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('industry_id')->nullable();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->jsonb('custom_fields')->nullable();
            $table->integer('lock_version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('industry_id')
                ->references('id')
                ->on('crm_industries')
                ->onDelete('set null');

            $table->index('organization_id');
            $table->index('industry_id');
        });

        // 5. crm_contacts
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('company_id')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->jsonb('custom_fields')->nullable();
            $table->integer('lock_version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('crm_companies')
                ->onDelete('set null');

            $table->index('organization_id');
            $table->index('company_id');
            $table->unique(['organization_id', 'email', 'deleted_at']);
        });

        // 6. crm_pipelines
        Schema::create('crm_pipelines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('lock_version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->index('organization_id');
        });

        // 7. crm_pipeline_stages
        Schema::create('crm_pipeline_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('pipeline_id');
            $table->string('name');
            $table->integer('probability')->default(10);
            $table->integer('sort_order')->default(0);
            $table->integer('lock_version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('pipeline_id')
                ->references('id')
                ->on('crm_pipelines')
                ->onDelete('cascade');

            $table->index('organization_id');
            $table->index('pipeline_id');
        });

        // 8. crm_opportunities
        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('company_id')->nullable();
            $table->uuid('contact_id')->nullable();
            $table->uuid('pipeline_id');
            $table->uuid('pipeline_stage_id');
            $table->uuid('user_id')->nullable(); // Owner
            $table->string('name');
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->date('close_date')->nullable();
            $table->string('status')->default('open'); // open, won, lost
            $table->jsonb('custom_fields')->nullable();
            $table->integer('lock_version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('crm_companies')
                ->onDelete('set null');

            $table->foreign('contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->onDelete('set null');

            $table->foreign('pipeline_id')
                ->references('id')
                ->on('crm_pipelines')
                ->onDelete('cascade');

            $table->foreign('pipeline_stage_id')
                ->references('id')
                ->on('crm_pipeline_stages')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('organization_id');
            $table->index('pipeline_id');
            $table->index('pipeline_stage_id');
            $table->index('user_id');
        });

        // 9. crm_leads
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('company_id')->nullable();
            $table->uuid('contact_id')->nullable();
            $table->uuid('lead_source_id')->nullable();
            $table->uuid('user_id')->nullable(); // Owner
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('status')->default('new'); // new, contacted, qualified, unqualified, converted, lost
            $table->jsonb('custom_fields')->nullable();
            $table->integer('lock_version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('crm_companies')
                ->onDelete('set null');

            $table->foreign('contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->onDelete('set null');

            $table->foreign('lead_source_id')
                ->references('id')
                ->on('crm_lead_sources')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('organization_id');
            $table->index('company_id');
            $table->index('contact_id');
            $table->index('lead_source_id');
            $table->index('user_id');
            $table->unique(['organization_id', 'email', 'deleted_at']);
        });

        // 10. crm_tags
        Schema::create('crm_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('slug');
            $table->string('color')->nullable();
            $table->integer('lock_version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->index('organization_id');
            $table->unique(['organization_id', 'slug']);
        });

        // 11. crm_custom_fields
        Schema::create('crm_custom_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('model_type'); // e.g. Lead, Contact, Company, Opportunity
            $table->string('name');
            $table->string('field_type'); // text, number, date, boolean, select
            $table->jsonb('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('lock_version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->index('organization_id');
        });

        // 12. crm_taggables (Polymorphic pivot)
        Schema::create('crm_taggables', function (Blueprint $table) {
            $table->uuid('tag_id');
            $table->uuid('taggable_id');
            $table->string('taggable_type');

            $table->foreign('tag_id')
                ->references('id')
                ->on('crm_tags')
                ->onDelete('cascade');

            $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
            $table->index(['taggable_id', 'taggable_type']);
        });

        // GIN indexes for JSONB fields under Postgres
        try {
            DB::statement('CREATE INDEX crm_companies_custom_fields_gin ON crm_companies USING gin(custom_fields)');
            DB::statement('CREATE INDEX crm_contacts_custom_fields_gin ON crm_contacts USING gin(custom_fields)');
            DB::statement('CREATE INDEX crm_opportunities_custom_fields_gin ON crm_opportunities USING gin(custom_fields)');
            DB::statement('CREATE INDEX crm_leads_custom_fields_gin ON crm_leads USING gin(custom_fields)');
        } catch (\Throwable $e) {
            // Fallback
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_taggables');
        Schema::dropIfExists('crm_custom_fields');
        Schema::dropIfExists('crm_tags');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_pipeline_stages');
        Schema::dropIfExists('crm_pipelines');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_companies');
        Schema::dropIfExists('crm_lead_sources');
        Schema::dropIfExists('crm_industries');
    }
};
