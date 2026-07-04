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
        // 1. Reusable polymorphic CRM Activities table
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('loggable_type')->nullable(); // Polymorphic target (e.g., App\Domain\CRM\Models\Lead, App\Domain\CRM\Models\Contact, Opportunity, etc.)
            $table->uuid('loggable_id')->nullable();
            $table->uuid('user_id')->nullable(); // Owner / Assigned User
            $table->string('type'); // phone_call, meeting, email, sms, whatsapp, internal_note, follow_up_task, appointment, demo, proposal, quote, status_change, assignment_change, reminder, system_event
            $table->string('subject');
            $table->text('description')->nullable();
            $table->jsonb('properties')->nullable(); // Duration, direction, outcome, etc.
            
            // Task/reminder specific fields
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->string('priority')->default('medium'); // low, medium, high
            $table->boolean('is_recurring')->default(false);
            $table->jsonb('recurring_rules')->nullable();
            $table->timestamp('last_reminder_sent_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('organization_id');
            $table->index(['loggable_type', 'loggable_id']);
            $table->index('user_id');
            $table->index('type');
            $table->index('due_at');
            $table->index('is_completed');
        });

        // 2. Polymorphic Notes table supporting versioning & threads
        Schema::create('crm_activity_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('notable_type'); // E.g., Lead, Contact, Activity
            $table->uuid('notable_id');
            $table->uuid('user_id')->nullable();
            $table->text('content'); // Markdown support
            $table->integer('version')->default(1);
            $table->uuid('parent_id')->nullable(); // Threading support
            $table->uuid('original_note_id')->nullable(); // Version history linking

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('parent_id')
                ->references('id')
                ->on('crm_activity_notes')
                ->onDelete('cascade');

            $table->foreign('original_note_id')
                ->references('id')
                ->on('crm_activity_notes')
                ->onDelete('cascade');

            $table->index('organization_id');
            $table->index(['notable_type', 'notable_id']);
            $table->index('user_id');
        });

        // 3. Queued Reminders table
        Schema::create('crm_activity_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('activity_id')->nullable(); // Optional link to a specific activity
            $table->uuid('user_id'); // Who gets the reminder
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('remind_at');
            $table->string('method')->default('in_app'); // in_app, email, sms
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->jsonb('recurring_rules')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('activity_id')
                ->references('id')
                ->on('crm_activities')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index('organization_id');
            $table->index('activity_id');
            $table->index('user_id');
            $table->index('remind_at');
            $table->index('is_sent');
        });

        // 4. Activity Attachments pivot linking to stored_files
        Schema::create('crm_activity_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('activity_id');
            $table->uuid('stored_file_id');
            $table->uuid('user_id')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('activity_id')
                ->references('id')
                ->on('crm_activities')
                ->onDelete('cascade');

            $table->foreign('stored_file_id')
                ->references('id')
                ->on('stored_files')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('organization_id');
            $table->index('activity_id');
            $table->index('stored_file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_activity_attachments');
        Schema::dropIfExists('crm_activity_reminders');
        Schema::dropIfExists('crm_activity_notes');
        Schema::dropIfExists('crm_activities');
    }
};
