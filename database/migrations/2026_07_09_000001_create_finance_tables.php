<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tax Rates
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->decimal('rate', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index('organization_id');
        });

        // 2. Estimates
        Schema::create('estimates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('estimate_number');
            $table->uuid('client_id')->nullable();
            $table->string('client_name');
            $table->string('client_email');
            $table->string('status')->default('draft'); // draft, pending, sent, approved, declined
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('tax_total', 15, 2)->default(0.00);
            $table->decimal('total', 15, 2)->default(0.00);
            $table->date('estimate_date');
            $table->date('expiry_date')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('revision_history')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index('organization_id');
            $table->unique(['organization_id', 'estimate_number']);
        });

        // 3. Estimate Items
        Schema::create('estimate_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('estimate_id');
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->uuid('tax_rate_id')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('total', 15, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('estimate_id')->references('id')->on('estimates')->onDelete('cascade');
            $table->foreign('tax_rate_id')->references('id')->on('tax_rates')->onDelete('set null');
        });

        // 4. Recurring Invoices
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('client_id')->nullable();
            $table->string('client_name');
            $table->string('client_email');
            $table->string('billing_cycle'); // weekly, monthly, quarterly, yearly
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->string('status')->default('active'); // active, paused, completed
            $table->jsonb('template_data'); // items, terms, billing frequency
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index('organization_id');
        });

        // 5. Invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('invoice_number');
            $table->uuid('client_id')->nullable();
            $table->string('client_name');
            $table->string('client_email');
            $table->string('status')->default('draft'); // draft, pending, sent, viewed, partially paid, paid, overdue, cancelled, void
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('tax_total', 15, 2)->default(0.00);
            $table->decimal('total', 15, 2)->default(0.00);
            $table->decimal('amount_paid', 15, 2)->default(0.00);
            $table->decimal('amount_remaining', 15, 2)->default(0.00);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->uuid('estimate_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->uuid('recurring_invoice_id')->nullable();
            $table->string('payment_link')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('estimate_id')->references('id')->on('estimates')->onDelete('set null');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('recurring_invoice_id')->references('id')->on('recurring_invoices')->onDelete('set null');

            $table->index('organization_id');
            $table->unique(['organization_id', 'invoice_number']);
        });

        // 6. Invoice Items
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->uuid('tax_rate_id')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('total', 15, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('tax_rate_id')->references('id')->on('tax_rates')->onDelete('set null');
        });

        // 7. Payments
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('invoice_id')->nullable();
            $table->string('payment_method'); // M-PESA, Card, Bank Transfer, Cash, Manual
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('transaction_reference')->nullable();
            $table->string('status')->default('completed'); // pending, completed, failed, refunded
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');

            $table->index('organization_id');
        });

        // 8. Expenses
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('category'); // Software, Hosting, Domains, Marketing, Payroll, Equipment, Travel, Miscellaneous
            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->string('merchant')->nullable();
            $table->string('description')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('status')->default('paid'); // pending, approved, paid, rejected
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');

            $table->index('organization_id');
        });

        // 9. Credit Notes
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('invoice_id');
            $table->string('credit_note_number');
            $table->decimal('amount', 15, 2);
            $table->date('issue_date');
            $table->string('reason');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');

            $table->index('organization_id');
        });

        // 10. Refunds
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('payment_id');
            $table->string('refund_number');
            $table->decimal('amount', 15, 2);
            $table->date('refund_date');
            $table->string('reason');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');

            $table->index('organization_id');
        });

        // 11. Transactions (Immutable Ledger Entries)
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('ledgerable_type')->nullable();
            $table->uuid('ledgerable_id')->nullable();
            $table->string('type'); // debit, credit
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('description');
            $table->string('reference_number')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');

            $table->index('organization_id');
            $table->index(['ledgerable_type', 'ledgerable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('recurring_invoices');
        Schema::dropIfExists('estimate_items');
        Schema::dropIfExists('estimates');
        Schema::dropIfExists('tax_rates');
    }
};
