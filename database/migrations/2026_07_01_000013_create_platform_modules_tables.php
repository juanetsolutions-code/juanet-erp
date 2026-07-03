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
        // 1. CRM Leads Table
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('email');
            $table->string('status')->default('new'); // new, contacted, won, lost
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });

        // 2. Marketplace Listings Table
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('title');
            $table->text('description');
            $table->decimal('price', 15, 2);
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });

        // 3. CMS Pages Table
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('title');
            $table->string('slug');
            $table->longText('content');
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });

        // 4. Projects Tasks Table
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('todo'); // todo, in_progress, done
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });

        // 5. Finance Invoices Table
        Schema::create('finance_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('invoice_number');
            $table->string('client_name');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });

        // 6. Support Tickets Table
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('subject');
            $table->text('message');
            $table->string('status')->default('open'); // open, pending, closed
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });

        // 7. AI Prompts Table
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->text('prompt_text');
            $table->text('response_text');
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('finance_invoices');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('cms_pages');
        Schema::dropIfExists('marketplace_listings');
        Schema::dropIfExists('crm_leads');
    }
};
