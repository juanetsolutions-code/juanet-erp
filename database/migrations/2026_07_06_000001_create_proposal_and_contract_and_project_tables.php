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
        // 1. Proposals Table
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('client_id');
            $table->uuid('organization_id')->default('00000000-0000-0000-0000-000000000000');
            $table->string('title');
            $table->string('status')->default('draft');
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 2. Proposal Sections Table
        Schema::create('proposal_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 3. Proposal Items Table
        Schema::create('proposal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->onDelete('cascade');
            $table->text('description');
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->timestamps();
        });

        // 4. Proposal Revisions Table
        Schema::create('proposal_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->onDelete('cascade');
            $table->integer('version');
            $table->jsonb('content');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // 5. Proposal Activities Table
        Schema::create('proposal_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('activity');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        // 6. Proposal Comments Table
        Schema::create('proposal_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->onDelete('cascade');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->text('content');
            $table->timestamps();
        });

        // 7. Contracts Table
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('organization_id')->default('00000000-0000-0000-0000-000000000000');
            $table->unsignedBigInteger('client_id');
            $table->string('title');
            $table->string('document_url')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        // 8. Signatures Table
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->unsignedBigInteger('signer_id');
            $table->string('ip_address', 45);
            $table->string('signature_hash');
            $table->timestamp('signed_at');
            $table->string('signature_type')->default('typed');
            $table->text('signature_data');
            $table->timestamps();
        });

        // 9. Projects Table
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('reference_number')->nullable();
            $table->unsignedBigInteger('user_id'); // original field
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->uuid('organization_id')->default('00000000-0000-0000-0000-000000000000');
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('status')->default('initiated');
            $table->decimal('budget', 15, 2)->default(0.00);
            $table->string('timeline')->nullable();
            $table->timestamp('expected_completion')->nullable();
            $table->timestamps();
        });

        // 10. Project Milestones Table
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('title');
            $table->string('status')->default('pending');
            $table->timestamp('due_date')->nullable();
            $table->timestamps();
        });

        // 11. Drop older conflicting project_tasks if exists, then create the new one
        Schema::dropIfExists('project_tasks');

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained('project_milestones')->onDelete('cascade');
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->string('title');
            $table->string('priority')->default('medium');
            $table->string('status')->default('todo');
            $table->timestamp('due_date')->nullable();
            $table->timestamps();
        });

        // 12. Project Comments Table
        Schema::create('project_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->text('content');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });

        // 13. Project Files Table
        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('filename');
            $table->string('path');
            $table->unsignedBigInteger('user_id');
            $table->string('folder')->nullable();
            $table->integer('version')->default(1);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });

        // 14. Project Approvals Table
        Schema::create('project_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        // 15. Project Activities Table
        Schema::create('project_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('activity');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_activities');
        Schema::dropIfExists('project_approvals');
        Schema::dropIfExists('project_files');
        Schema::dropIfExists('project_comments');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('signatures');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('proposal_comments');
        Schema::dropIfExists('proposal_activities');
        Schema::dropIfExists('proposal_revisions');
        Schema::dropIfExists('proposal_items');
        Schema::dropIfExists('proposal_sections');
        Schema::dropIfExists('proposals');
    }
};
