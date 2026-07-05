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
        // 1. Create crm_visitors table
        Schema::create('crm_visitors', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUIDv7
            $table->uuid('organization_id')->nullable(); // Tenant isolation (GDPR ready)
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->integer('total_sessions')->default(0);
            $table->integer('total_page_views')->default(0);
            
            // Geographic data
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('timezone')->nullable();
            
            // Device Intelligence
            $table->string('preferred_language')->nullable();
            $table->string('browser')->nullable();
            $table->string('operating_system')->nullable();
            $table->string('device_type')->nullable(); // Desktop, Tablet, Mobile
            $table->string('screen_resolution')->nullable();
            $table->string('viewport')->nullable();
            $table->string('network_type')->nullable();

            // Attribution details
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->jsonb('campaign_history')->nullable(); // Complete attribution history (jsonb)
            $table->jsonb('referral_chain')->nullable(); // referral chain array
            $table->jsonb('first_touch')->nullable(); // first touch information
            $table->jsonb('last_touch')->nullable(); // last touch information

            // GDPR & Privacy Compliances
            $table->string('cookie_consent')->nullable(); // e.g., 'accepted', 'rejected', 'functional_only'
            $table->boolean('do_not_track')->default(false);
            $table->timestamp('anonymized_at')->nullable(); // Timestamp of GDPR anonymization
            $table->softDeletes(); // Soft deletion for data retention
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->index('organization_id');
            $table->index('first_seen_at');
        });

        // 2. Create crm_visitor_sessions table
        Schema::create('crm_visitor_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary(); // session_uuid (UUIDv7)
            $table->uuid('visitor_id');
            $table->uuid('organization_id')->nullable();
            $table->timestamp('start_time')->useCurrent();
            $table->timestamp('end_time')->nullable();
            $table->integer('duration')->nullable(); // duration in seconds
            $table->text('referrer')->nullable();
            $table->text('landing_page')->nullable();
            $table->text('exit_page')->nullable();
            $table->integer('pages_visited')->default(0);
            $table->boolean('bounce')->default(true);
            $table->boolean('returning_visitor')->default(false);
            $table->timestamps();

            $table->foreign('visitor_id')
                ->references('id')
                ->on('crm_visitors')
                ->onDelete('cascade');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->index('visitor_id');
            $table->index('organization_id');
            $table->index('start_time');
        });

        // 3. Create crm_visitor_page_views table
        Schema::create('crm_visitor_page_views', function (Blueprint $table) {
            $table->uuid('id')->primary(); // pageview_uuid
            $table->uuid('session_id');
            $table->uuid('visitor_id');
            $table->uuid('organization_id')->nullable();
            $table->text('url');
            $table->string('route_name')->nullable();
            $table->string('page_title')->nullable();
            $table->timestamp('timestamp')->useCurrent();
            $table->integer('time_on_page')->nullable(); // in seconds
            $table->integer('scroll_depth')->nullable(); // percentage (e.g. 75)
            
            // Analytics tracking arrays (jsonb)
            $table->jsonb('cta_clicks')->nullable(); // [{cta_id, label, clicked_at}]
            $table->jsonb('downloads')->nullable(); // [{filename, url, downloaded_at}]
            $table->jsonb('outbound_links')->nullable(); // [{url, label, clicked_at}]
            $table->timestamps();

            $table->foreign('session_id')
                ->references('id')
                ->on('crm_visitor_sessions')
                ->onDelete('cascade');

            $table->foreign('visitor_id')
                ->references('id')
                ->on('crm_visitors')
                ->onDelete('cascade');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->index('session_id');
            $table->index('visitor_id');
            $table->index('organization_id');
        });

        // 4. Update crm_leads table to associate visitor_id
        Schema::table('crm_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_leads', 'visitor_id')) {
                $table->uuid('visitor_id')->nullable()->after('lead_source_id');
                
                $table->foreign('visitor_id')
                    ->references('id')
                    ->on('crm_visitors')
                    ->onDelete('set null');

                $table->index('visitor_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            if (Schema::hasColumn('crm_leads', 'visitor_id')) {
                $table->dropForeign(['visitor_id']);
                $table->dropColumn('visitor_id');
            }
        });

        Schema::dropIfExists('crm_visitor_page_views');
        Schema::dropIfExists('crm_visitor_sessions');
        Schema::dropIfExists('crm_visitors');
    }
};
