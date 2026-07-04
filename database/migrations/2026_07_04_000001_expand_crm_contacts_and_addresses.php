<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            // Personal Information
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('gender')->nullable()->after('anniversary');
            $table->string('nationality')->nullable()->after('gender');
            $table->text('languages')->nullable()->after('nationality'); // Comma-separated or JSON array

            // Communication Channels Extra
            $table->string('personal_email')->nullable()->after('email');
            $table->string('assistant_email')->nullable()->after('personal_email');
            $table->string('work_phone')->nullable()->after('phone');
            $table->string('mobile_phone')->nullable()->after('work_phone');
            $table->string('home_phone')->nullable()->after('mobile_phone');
            $table->string('assistant_phone')->nullable()->after('home_phone');
            $table->string('fax')->nullable()->after('assistant_phone');
            $table->string('whatsapp')->nullable()->after('fax');
            $table->string('telegram')->nullable()->after('whatsapp');
            $table->string('signal')->nullable()->after('telegram');
            $table->string('youtube_url')->nullable()->after('facebook_url');

            // Professional & Hierarchy Extra
            $table->uuid('manager_id')->nullable()->after('user_id');
            $table->string('buying_role')->nullable()->after('buying_influence'); // Decision Maker, Influencer, Champion, User, Gatekeeper, Blocker
            $table->boolean('is_decision_maker')->default(false)->after('buying_role');
            $table->boolean('is_influencer')->default(false)->after('is_decision_maker');
            $table->boolean('is_technical_contact')->default(false)->after('is_influencer');

            // Classification & Segmentation
            $table->string('tier')->default('Tier C')->after('buying_role'); // Tier A, Tier B, Tier C
            $table->string('segment')->default('SMB')->after('tier'); // Enterprise, Mid-Market, SMB
            $table->string('lifecycle_stage')->default('Lead')->after('segment'); // Subscriber, Lead, MQL, SQL, Opportunity, Customer, Evangelist
            $table->string('classification')->nullable()->after('lifecycle_stage'); // High Touch, Low Touch, Tech Touch
            $table->string('status')->default('Active')->after('classification'); // Active, Inactive, Dormant, Bounced, Do Not Contact

            // Consent Toggles
            $table->boolean('sms_consent')->default(false)->after('gdpr_consent_status');
            $table->boolean('whatsapp_consent')->default(false)->after('sms_consent');
            $table->boolean('email_consent')->default(false)->after('whatsapp_consent');
            $table->boolean('do_not_call')->default(false)->after('email_consent');
            $table->boolean('do_not_email')->default(false)->after('do_not_call');
            $table->boolean('do_not_sms')->default(false)->after('do_not_email');

            // Foreign Key
            $table->foreign('manager_id')
                ->references('id')
                ->on('crm_contacts')
                ->onDelete('set null');

            $table->index('manager_id');
            $table->index('tier');
            $table->index('segment');
            $table->index('lifecycle_stage');
            $table->index('status');
        });

        Schema::create('crm_contact_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('contact_id');
            $table->string('type'); // billing, shipping, office, home, branch, warehouse, emergency, primary
            $table->boolean('is_primary')->default(false);
            $table->string('street');
            $table->string('city');
            $table->string('county')->nullable();
            $table->string('region')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country');
            $table->string('coordinates')->nullable(); // JSON or "lat,lng"
            $table->string('timezone')->nullable();
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
        });

        Schema::create('crm_contact_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('contact_id');
            $table->string('channel'); // email, sms, whatsapp, phone
            $table->string('status'); // granted, revoked, pending
            $table->string('purpose'); // marketing, transactional, support
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('consented_at');
            $table->string('source')->nullable(); // webform, agent, portal
            $table->text('notes')->nullable();
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
            $table->index('channel');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contact_consents');
        Schema::dropIfExists('crm_contact_addresses');

        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn([
                'middle_name',
                'gender',
                'nationality',
                'languages',
                'personal_email',
                'assistant_email',
                'work_phone',
                'mobile_phone',
                'home_phone',
                'assistant_phone',
                'fax',
                'whatsapp',
                'telegram',
                'signal',
                'youtube_url',
                'manager_id',
                'buying_role',
                'is_decision_maker',
                'is_influencer',
                'is_technical_contact',
                'tier',
                'segment',
                'lifecycle_stage',
                'classification',
                'status',
                'sms_consent',
                'whatsapp_consent',
                'email_consent',
                'do_not_call',
                'do_not_email',
                'do_not_sms',
            ]);
        });
    }
};
