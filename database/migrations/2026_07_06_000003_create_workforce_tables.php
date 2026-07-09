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
        // 1. Departments
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('slug');
            $table->uuid('manager_id')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('manager_id')->references('id')->on('users')->onDelete('set null');
            
            $table->index('organization_id');
            $table->unique(['organization_id', 'slug']);
        });

        // 2. Positions
        Schema::create('positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('department_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');

            $table->index('organization_id');
            $table->unique(['organization_id', 'slug']);
        });

        // 3. Employee Profiles
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('user_id')->unique();
            $table->uuid('department_id')->nullable();
            $table->uuid('position_id')->nullable();
            $table->uuid('reporting_to_id')->nullable();
            $table->integer('skills_expert_score')->default(0);
            $table->string('status')->default('active'); // active, on_leave, inactive
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');
            $table->foreign('reporting_to_id')->references('id')->on('employee_profiles')->onDelete('set null');

            $table->index('organization_id');
            $table->index('user_id');
        });

        // 4. Teams
        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('slug');
            $table->uuid('manager_id')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('manager_id')->references('id')->on('users')->onDelete('set null');

            $table->index('organization_id');
            $table->unique(['organization_id', 'slug']);
        });

        // 5. Employee Profile Teams Pivot (Multiple teams per organization)
        Schema::create('employee_profile_teams', function (Blueprint $table) {
            $table->uuid('employee_profile_id');
            $table->uuid('team_id');

            $table->foreign('employee_profile_id')->references('id')->on('employee_profiles')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');

            $table->primary(['employee_profile_id', 'team_id']);
        });

        // 6. Skills
        Schema::create('skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('slug');
            $table->string('category')->nullable(); // Technology, Language, Design, QA, PM etc.
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');

            $table->index('organization_id');
            $table->unique(['organization_id', 'slug']);
        });

        // 7. Employee Skills Pivot with meta attributes
        Schema::create('employee_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_profile_id');
            $table->uuid('skill_id');
            $table->string('type')->default('primary'); // primary, secondary
            $table->string('experience_level')->default('intermediate'); // beginner, intermediate, advanced, expert
            $table->string('certification')->nullable();
            $table->timestamps();

            $table->foreign('employee_profile_id')->references('id')->on('employee_profiles')->onDelete('cascade');
            $table->foreign('skill_id')->references('id')->on('skills')->onDelete('cascade');

            $table->index('employee_profile_id');
            $table->unique(['employee_profile_id', 'skill_id']);
        });

        // 8. Availabilities
        Schema::create('availabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_profile_id');
            $table->date('date');
            $table->string('status')->default('available'); // available, unavailable, partially_available
            $table->integer('capacity_percentage')->default(100);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_profile_id')->references('id')->on('employee_profiles')->onDelete('cascade');

            $table->index('employee_profile_id');
            $table->unique(['employee_profile_id', 'date']);
        });

        // 9. Work Schedules
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_profile_id')->nullable();
            $table->uuid('team_id')->nullable();
            $table->string('name');
            $table->jsonb('schedule_data')->nullable(); // JSON configuration of hours/days
            $table->timestamps();

            $table->foreign('employee_profile_id')->references('id')->on('employee_profiles')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });

        // 10. Leave Requests
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('employee_profile_id');
            $table->string('type'); // vacation, sick, emergency, remote_work
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('reason')->nullable();
            $table->uuid('approved_by_id')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_profile_id')->references('id')->on('employee_profiles')->onDelete('cascade');
            $table->foreign('approved_by_id')->references('id')->on('users')->onDelete('set null');

            $table->index('organization_id');
            $table->index('employee_profile_id');
        });

        // 11. Assignments
        Schema::create('assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('employee_profile_id');
            $table->unsignedBigInteger('project_id')->nullable(); // References projects
            $table->uuid('opportunity_id')->nullable(); // References crm_opportunities
            $table->string('role'); // Developer, Designer, QA, Project Manager, Content Writer, Support Agent, Finance Officer
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('estimated_workload', 5, 2)->default(0.00); // e.g. hours per week or percentage (40 hrs/week default)
            $table->decimal('actual_workload', 5, 2)->default(0.00);
            $table->string('status')->default('active'); // active, completed, planned
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_profile_id')->references('id')->on('employee_profiles')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('opportunity_id')->references('id')->on('crm_opportunities')->onDelete('cascade');

            $table->index('organization_id');
            $table->index('employee_profile_id');
        });

        // 12. Time Entries
        Schema::create('time_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('employee_profile_id');
            $table->uuid('assignment_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->integer('duration_minutes')->default(0);
            $table->boolean('is_billable')->default(true);
            $table->boolean('is_manual')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('employee_profile_id')->references('id')->on('employee_profiles')->onDelete('cascade');
            $table->foreign('assignment_id')->references('id')->on('assignments')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');

            $table->index('organization_id');
            $table->index('employee_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('availabilities');
        Schema::dropIfExists('employee_skills');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('employee_profile_teams');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('employee_profiles');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
