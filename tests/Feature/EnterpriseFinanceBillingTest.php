<?php

use App\Models\Organization;
use App\Models\User;
use App\Domain\Finance\Models\TaxRate;
use App\Domain\Finance\Models\Estimate;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Finance\Models\RecurringInvoice;
use App\Domain\Finance\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('finance service can create tax rates, estimates, and convert to invoice with taxes', function () {
    $org = Organization::create([
        'name' => 'JUANET Finance Corp',
        'domain' => 'finance.juanet.co.ke'
    ]);

    $service = app(FinanceService::class);

    // 1. Create Tax Rate
    $tax = $service->createTaxRate($org->id, 'Kenya VAT', 16.0);
    expect($tax->name)->toBe('Kenya VAT')
        ->and($tax->rate)->toBe('16.00')
        ->and($tax->organization_id)->toBe($org->id);

    // 2. Create Estimate with items
    $estimateData = [
        'client_name' => 'Safaricom PLC',
        'client_email' => 'billing@safaricom.co.ke',
        'status' => 'draft',
        'items' => [
            [
                'description' => 'SaaS Cloud ERP Core Installation',
                'quantity' => 1,
                'unit_price' => 100000.00,
                'tax_rate_id' => $tax->id
            ]
        ]
    ];

    $estimate = $service->createEstimate($org->id, $estimateData);
    expect($estimate->subtotal)->toBe('100000.00')
        ->and($estimate->tax_total)->toBe('16000.00')
        ->and($estimate->total)->toBe('116000.00');

    // 3. Convert Estimate to Invoice
    $invoice = $service->convertEstimateToInvoice($estimate->id);
    expect($invoice->client_name)->toBe('Safaricom PLC')
        ->and($invoice->subtotal)->toBe('100000.00')
        ->and($invoice->total)->toBe('116000.00')
        ->and($invoice->status)->toBe('sent');

    // 4. Verify Approved status of parent Estimate
    $estimate->refresh();
    expect($estimate->status)->toBe('approved');

    // 5. Verify Ledger Posting for invoice (Accounts Receivable credit)
    $this->assertDatabaseHas('transactions', [
        'organization_id' => $org->id,
        'ledgerable_type' => Invoice::class,
        'ledgerable_id' => $invoice->id,
        'type' => 'credit',
        'amount' => 116000.00
    ]);
});

test('payments transition invoice status and record credits in double-entry ledger', function () {
    $org = Organization::create([
        'name' => 'JUANET Finance Corp',
        'domain' => 'finance.juanet.co.ke'
    ]);

    $service = app(FinanceService::class);
    $tax = $service->createTaxRate($org->id, 'Kenya VAT', 16.0);

    $invoice = $service->createInvoice($org->id, [
        'client_name' => 'Equity Bank Kenya',
        'client_email' => 'finance@equitybank.co.ke',
        'status' => 'sent',
        'items' => [
            [
                'description' => 'Enterprise API Gateway Integration',
                'quantity' => 1,
                'unit_price' => 200000.00,
                'tax_rate_id' => $tax->id
            ]
        ]
    ]);

    expect($invoice->total)->toBe('232000.00')
        ->and($invoice->amount_paid)->toBe('0.00')
        ->and($invoice->amount_remaining)->toBe('232000.00');

    // Make partial payment
    $payment1 = $service->makePayment(
        invoiceId: $invoice->id,
        amount: 100000.00,
        method: 'M-PESA',
        reference: 'QGR49FLK38',
        notes: 'First tranche payment'
    );

    $invoice->refresh();
    expect($invoice->status)->toBe('partially paid')
        ->and($invoice->amount_paid)->toBe('100000.00')
        ->and($invoice->amount_remaining)->toBe('132000.00');

    // Verify Ledger Posting for partial payment
    $this->assertDatabaseHas('transactions', [
        'organization_id' => $org->id,
        'ledgerable_type' => Payment::class,
        'ledgerable_id' => $payment1->id,
        'type' => 'credit',
        'amount' => 100000.00,
        'reference_number' => 'QGR49FLK38'
    ]);

    // Make final payment
    $payment2 = $service->makePayment(
        invoiceId: $invoice->id,
        amount: 132000.00,
        method: 'Card',
        reference: 'CHG_920381029',
        notes: 'Final balance settlement'
    );

    $invoice->refresh();
    expect($invoice->status)->toBe('paid')
        ->and($invoice->amount_paid)->toBe('232000.00')
        ->and($invoice->amount_remaining)->toBe('0.00');

    // Verify Ledger Posting for final payment
    $this->assertDatabaseHas('transactions', [
        'organization_id' => $org->id,
        'ledgerable_type' => Payment::class,
        'ledgerable_id' => $payment2->id,
        'type' => 'credit',
        'amount' => 132000.00
    ]);
});

test('operating expenses post debits directly to treasury ledger', function () {
    $org = Organization::create([
        'name' => 'JUANET Finance Corp',
        'domain' => 'finance.juanet.co.ke'
    ]);

    $service = app(FinanceService::class);

    $expense = $service->createExpense($org->id, [
        'category' => 'Hosting',
        'amount' => 14500.00,
        'merchant' => 'Amazon Web Services',
        'description' => 'AWS EC2 Production environment clusters',
        'reference_number' => 'AWS-INV-99201',
    ]);

    expect($expense->category)->toBe('Hosting')
        ->and($expense->amount)->toBe('14500.00')
        ->and($expense->merchant)->toBe('Amazon Web Services');

    // Verify Immutable Debit Entry in Ledger
    $this->assertDatabaseHas('transactions', [
        'organization_id' => $org->id,
        'ledgerable_type' => Expense::class,
        'ledgerable_id' => $expense->id,
        'type' => 'debit',
        'amount' => 14500.00,
        'reference_number' => 'AWS-INV-99201'
    ]);
});

test('recurring billing automation can schedule templates and process invoice generation', function () {
    $org = Organization::create([
        'name' => 'JUANET Finance Corp',
        'domain' => 'finance.juanet.co.ke'
    ]);

    $service = app(FinanceService::class);

    $recurring = $service->createRecurringInvoice($org->id, [
        'client_name' => 'Bamburi Cement',
        'client_email' => 'supplychain@bamburi.lafarge.com',
        'billing_cycle' => 'monthly',
        'start_date' => now()->subDay()->toDateString(), // due yesterday, so must trigger
        'template_data' => [
            'subtotal' => 50000.00,
            'tax_total' => 8000.00,
            'total' => 58000.00,
            'items' => [
                [
                    'description' => 'SLA Support Tier A Monthly',
                    'quantity' => 1,
                    'unit_price' => 50000.00,
                    'tax_rate_id' => 'tax-16'
                ]
            ]
        ]
    ]);

    expect($recurring->billing_cycle)->toBe('monthly')
        ->and($recurring->status)->toBe('active');

    // Process Recurring Billing
    $generatedCount = $service->processRecurringBilling($org->id);
    expect($generatedCount)->toBe(1);

    // Verify last_generated_at timestamp got updated
    $recurring->refresh();
    expect($recurring->last_generated_at)->not->toBeNull();

    // Verify generated invoice and transactional outbox entries
    $this->assertDatabaseHas('invoices', [
        'organization_id' => $org->id,
        'recurring_invoice_id' => $recurring->id,
        'client_name' => 'Bamburi Cement',
        'total' => 58000.00
    ]);
});

test('profit and loss accounts receivable and tax reports calculate correct treasury positions', function () {
    $org = Organization::create([
        'name' => 'JUANET Finance Corp',
        'domain' => 'finance.juanet.co.ke'
    ]);

    $service = app(FinanceService::class);
    $tax = $service->createTaxRate($org->id, 'Kenya VAT', 16.0);

    // 1. Earn Revenue via Payment
    $invoice = $service->createInvoice($org->id, [
        'client_name' => 'Safaricom PLC',
        'client_email' => 'billing@safaricom.co.ke',
        'status' => 'sent',
        'items' => [
            [
                'description' => 'SaaS Custom Modules',
                'quantity' => 1,
                'unit_price' => 100000.00,
                'tax_rate_id' => $tax->id
            ]
        ]
    ]);
    $service->makePayment($invoice->id, 116000.00, 'M-PESA', 'TX-REF-OK');

    // 2. Register Expense
    $service->createExpense($org->id, [
        'category' => 'Hosting',
        'amount' => 16000.00,
        'merchant' => 'AWS',
    ]);

    // 3. Generate Dashboard Stats
    $stats = $service->getDashboardStats($org->id);
    expect($stats['revenue'])->toBe(116000.0)
        ->and($stats['expenses'])->toBe(16000.0)
        ->and($stats['profit'])->toBe(100000.0)
        ->and($stats['taxes'])->toBe(16000.0);

    // 4. Generate Profit & Loss Report
    $pAndL = $service->getFinanceReport($org->id, 'p_and_l');
    expect($pAndL['total_income'])->toBe(116000.0)
        ->and($pAndL['total_expenses'])->toBe(16000.0)
        ->and($pAndL['net_profit'])->toBe(100000.0);
});
