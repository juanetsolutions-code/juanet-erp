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
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->string('preferred_name')->nullable()->after('last_name');
            $table->string('department')->nullable()->after('job_title');
            $table->string('decision_maker_level')->nullable()->after('department'); // C-Level, Director, VP, Manager, Individual Contributor
            $table->string('buying_influence')->nullable()->after('decision_maker_level'); // Decision Maker, Influencer, Gatekeeper, Blocker, Champion
            $table->string('linkedin_url')->nullable()->after('buying_influence');
            $table->string('twitter_url')->nullable()->after('linkedin_url');
            $table->string('facebook_url')->nullable()->after('twitter_url');
            $table->string('website')->nullable()->after('facebook_url');
            $table->string('profile_image_url')->nullable()->after('website');
            $table->string('preferred_language')->default('en')->after('profile_image_url');
            $table->string('timezone')->default('UTC')->after('preferred_language');
            $table->date('birthday')->nullable()->after('timezone');
            $table->date('anniversary')->nullable()->after('birthday');
            $table->text('notes')->nullable()->after('anniversary');
            $table->jsonb('communication_preferences')->nullable()->after('custom_fields');
            $table->string('gdpr_consent_status')->default('not_asked')->after('communication_preferences'); // not_asked, granted, declined
            $table->integer('health_score')->default(70)->after('gdpr_consent_status');
            $table->string('health_status')->default('Healthy')->after('health_score');
            $table->jsonb('health_breakdown')->nullable()->after('health_status');
            $table->uuid('user_id')->nullable()->after('company_id'); // Contact owner

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('user_id');
            $table->index('health_score');
            $table->index('gdpr_consent_status');
        });

        Schema::create('crm_contact_company_associations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('contact_id');
            $table->uuid('company_id');
            $table->string('role')->nullable(); // primary, secondary, contractor, advisor
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('crm_companies')
                ->onDelete('cascade');

            $table->index('organization_id');
            $table->index('contact_id');
            $table->index('company_id');
        });

        Schema::create('crm_contact_relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('contact_id');
            $table->uuid('related_contact_id');
            $table->string('type'); // manager, assistant, colleague, executive, decision_maker, influencer, technical_contact, legal_contact, finance_contact, emergency_contact
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->onDelete('cascade');

            $table->foreign('related_contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->onDelete('cascade');

            $table->index('organization_id');
            $table->index('contact_id');
            $table->index('related_contact_id');
            $table->index('type');
        });

        Schema::create('crm_contact_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('contact_id');
            $table->string('type'); // email, phone
            $table->string('value');
            $table->string('label')->default('work'); // primary, work, personal, mobile, whatsapp, etc.
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->onDelete('cascade');

            $table->index('organization_id');
            $table->index('contact_id');
            $table->index('type');
            $table->index('value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_contact_methods');
        Schema::dropIfExists('crm_contact_relationships');
        Schema::dropIfExists('crm_contact_company_associations');

        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'preferred_name',
                'department',
                'decision_maker_level',
                'buying_influence',
                'linkedin_url',
                'twitter_url',
                'facebook_url',
                'website',
                'profile_image_url',
                'preferred_language',
                'timezone',
                'birthday',
                'anniversary',
                'notes',
                'communication_preferences',
                'gdpr_consent_status',
                'health_score',
                'health_status',
                'health_breakdown',
                'user_id',
            ]);
        });
    }
};
