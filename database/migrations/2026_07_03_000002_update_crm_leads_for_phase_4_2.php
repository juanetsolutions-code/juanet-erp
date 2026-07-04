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
        // 1. Update crm_leads table with new enterprise-grade tracking columns
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->integer('score')->default(0)->after('status');
            $table->json('score_breakdown')->nullable()->after('score');
            $table->timestamp('last_activity_at')->nullable()->after('score_breakdown');
            $table->string('duplicate_status')->default('none')->after('last_activity_at'); // none, potential, duplicate
            $table->uuid('duplicate_of_id')->nullable()->after('duplicate_status');

            $table->foreign('duplicate_of_id')
                ->references('id')
                ->on('crm_leads')
                ->onDelete('set null');

            $table->index('duplicate_of_id');
            $table->index('score');
        });

        // 2. Create crm_lead_status_history for lifecycle auditing
        Schema::create('crm_lead_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('lead_id');
            $table->string('from_status');
            $table->string('to_status');
            $table->uuid('changed_by')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('lead_id')
                ->references('id')
                ->on('crm_leads')
                ->onDelete('cascade');

            $table->foreign('changed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('organization_id');
            $table->index('lead_id');
        });

        // 3. Create crm_lead_assignment_history for ownership auditing
        Schema::create('crm_lead_assignment_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('lead_id');
            $table->uuid('from_user_id')->nullable();
            $table->uuid('to_user_id')->nullable();
            $table->uuid('assigned_by')->nullable();
            $table->string('method')->default('manual'); // manual, round_robin, load_balanced, team, manager_override
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('lead_id')
                ->references('id')
                ->on('crm_leads')
                ->onDelete('cascade');

            $table->foreign('from_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('to_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('assigned_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('organization_id');
            $table->index('lead_id');
        });

        // 4. Create crm_lead_activities for Lead Activity Timeline
        Schema::create('crm_lead_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('lead_id');
            $table->uuid('user_id')->nullable();
            $table->string('type'); // creation, edit, assignment, email, note, call, meeting, status_change, conversion, attachment, workflow
            $table->text('description');
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('lead_id')
                ->references('id')
                ->on('crm_leads')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('organization_id');
            $table->index('lead_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_lead_activities');
        Schema::dropIfExists('crm_lead_assignment_history');
        Schema::dropIfExists('crm_lead_status_history');

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropForeign(['duplicate_of_id']);
            $table->dropColumn([
                'score',
                'score_breakdown',
                'last_activity_at',
                'duplicate_status',
                'duplicate_of_id',
            ]);
        });
    }
};
