<?php

namespace App\Domain\Finance\Services;

use App\Contracts\EventBus;
use App\Domain\Finance\Events\InvoicePaid;
use App\Domain\Finance\Events\InvoiceSent;
use App\Domain\Finance\Events\PaymentFailed;
use App\Domain\Finance\Events\RecurringInvoiceGenerated;
use App\Domain\Finance\Models\CreditNote;
use App\Domain\Finance\Models\Estimate;
use App\Domain\Finance\Models\EstimateItem;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\InvoiceItem;
use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Models\RecurringInvoice;
use App\Domain\Finance\Models\Refund;
use App\Domain\Finance\Models\TaxRate;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Notification\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FinanceService
{
    protected EventBus $eventBus;
    protected NotificationService $notificationService;

    public function __construct(EventBus $eventBus, NotificationService $notificationService)
    {
        $this->eventBus = $eventBus;
        $this->notificationService = $notificationService;
    }

    // ==========================================
    // TAX RATES
    // ==========================================

    public function createTaxRate(string $orgId, string $name, float $rate): TaxRate
    {
        return TaxRate::create([
            'organization_id' => $orgId,
            'name' => $name,
            'rate' => $rate,
            'is_active' => true,
        ]);
    }

    public function getTaxRates(string $orgId): Collection
    {
        return TaxRate::where('organization_id', $orgId)->get();
    }

    // ==========================================
    // ESTIMATES
    // ==========================================

    public function createEstimate(string $orgId, array $data): Estimate
    {
        $estimateNumber = $data['estimate_number'] ?? 'EST-' . date('Ymd') . '-' . rand(1000, 9999);
        
        $estimate = Estimate::create([
            'organization_id' => $orgId,
            'estimate_number' => $estimateNumber,
            'client_id' => $data['client_id'] ?? null,
            'client_name' => $data['client_name'],
            'client_email' => $data['client_email'],
            'status' => $data['status'] ?? 'draft',
            'subtotal' => $data['subtotal'] ?? 0.00,
            'tax_total' => $data['tax_total'] ?? 0.00,
            'total' => $data['total'] ?? 0.00,
            'estimate_date' => $data['estimate_date'] ?? Carbon::today()->toDateString(),
            'expiry_date' => $data['expiry_date'] ?? Carbon::today()->addDays(30)->toDateString(),
            'terms_conditions' => $data['terms_conditions'] ?? null,
            'notes' => $data['notes'] ?? null,
            'revision_history' => [
                [
                    'version' => 1,
                    'date' => Carbon::now()->toDateTimeString(),
                    'action' => 'Created estimate',
                    'status' => $data['status'] ?? 'draft'
                ]
            ],
        ]);

        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $this->createEstimateItem($estimate->id, $item);
            }
        }

        $this->recalculateEstimateTotals($estimate->id);
        return $estimate->refresh();
    }

    public function createEstimateItem(string $estimateId, array $itemData): EstimateItem
    {
        $quantity = $itemData['quantity'] ?? 1.00;
        $unitPrice = $itemData['unit_price'] ?? 0.00;
        $taxRateId = $itemData['tax_rate_id'] ?? null;
        
        $subtotal = $quantity * $unitPrice;
        $taxAmount = 0.00;

        if ($taxRateId) {
            $taxRate = TaxRate::find($taxRateId);
            if ($taxRate) {
                $taxAmount = $subtotal * ($taxRate->rate / 100);
            }
        }

        $total = $subtotal + $taxAmount;

        return EstimateItem::create([
            'estimate_id' => $estimateId,
            'description' => $itemData['description'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate_id' => $taxRateId,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    public function updateEstimateStatus(string $estimateId, string $status, ?string $notes = null): Estimate
    {
        $estimate = Estimate::findOrFail($estimateId);
        $oldStatus = $estimate->status;
        $estimate->status = $status;

        $history = $estimate->revision_history ?? [];
        $nextVer = count($history) + 1;
        $history[] = [
            'version' => $nextVer,
            'date' => Carbon::now()->toDateTimeString(),
            'action' => "Status changed from {$oldStatus} to {$status}",
            'status' => $status,
            'notes' => $notes
        ];
        $estimate->revision_history = $history;
        $estimate->save();

        return $estimate;
    }

    public function recalculateEstimateTotals(string $estimateId): void
    {
        $estimate = Estimate::findOrFail($estimateId);
        $items = $estimate->items;

        $subtotal = $items->sum('subtotal');
        $taxTotal = $items->sum('tax_amount');
        $total = $items->sum('total');

        $estimate->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ]);
    }

    // ==========================================
    // INVOICES
    // ==========================================

    public function createInvoice(string $orgId, array $data): Invoice
    {
        $invoiceNumber = $data['invoice_number'] ?? 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
        
        $invoice = Invoice::create([
            'organization_id' => $orgId,
            'invoice_number' => $invoiceNumber,
            'client_id' => $data['client_id'] ?? null,
            'client_name' => $data['client_name'],
            'client_email' => $data['client_email'],
            'status' => $data['status'] ?? 'draft',
            'subtotal' => $data['subtotal'] ?? 0.00,
            'tax_total' => $data['tax_total'] ?? 0.00,
            'total' => $data['total'] ?? 0.00,
            'amount_paid' => 0.00,
            'amount_remaining' => $data['total'] ?? 0.00,
            'invoice_date' => $data['invoice_date'] ?? Carbon::today()->toDateString(),
            'due_date' => $data['due_date'] ?? Carbon::today()->addDays(30)->toDateString(),
            'estimate_id' => $data['estimate_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'recurring_invoice_id' => $data['recurring_invoice_id'] ?? null,
            'payment_link' => $data['payment_link'] ?? 'https://juanet.co.ke/pay/invoice/' . Str::random(16),
        ]);

        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $this->createInvoiceItem($invoice->id, $item);
            }
        }

        $this->recalculateInvoiceTotals($invoice->id);
        $invoice->refresh();

        // Ledger Entry for New Invoice
        $this->createLedgerEntry(
            $orgId,
            Invoice::class,
            $invoice->id,
            'credit',
            $invoice->total,
            "Invoice #{$invoice->invoice_number} issued to {$invoice->client_name}",
            $invoice->invoice_number
        );

        if ($invoice->status === 'sent') {
            $this->eventBus->dispatch(new InvoiceSent($invoice->toArray(), $orgId));
            $this->notifyUser($orgId, "Invoice #{$invoice->invoice_number} Sent", "Invoice has been issued to {$invoice->client_name} for {$invoice->total} KES.");
        }

        return $invoice;
    }

    public function createInvoiceItem(string $invoiceId, array $itemData): InvoiceItem
    {
        $quantity = $itemData['quantity'] ?? 1.00;
        $unitPrice = $itemData['unit_price'] ?? 0.00;
        $taxRateId = $itemData['tax_rate_id'] ?? null;
        
        $subtotal = $quantity * $unitPrice;
        $taxAmount = 0.00;

        if ($taxRateId) {
            $taxRate = TaxRate::find($taxRateId);
            if ($taxRate) {
                $taxAmount = $subtotal * ($taxRate->rate / 100);
            }
        }

        $total = $subtotal + $taxAmount;

        return InvoiceItem::create([
            'invoice_id' => $invoiceId,
            'description' => $itemData['description'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate_id' => $taxRateId,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    public function updateInvoiceStatus(string $invoiceId, string $status): Invoice
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->status = $status;
        $invoice->save();

        if ($status === 'sent') {
            $this->eventBus->dispatch(new InvoiceSent($invoice->toArray(), $invoice->organization_id));
            $this->notifyUser($invoice->organization_id, "Invoice #{$invoice->invoice_number} Sent", "Invoice has been sent to {$invoice->client_name}.");
        }

        return $invoice;
    }

    public function recalculateInvoiceTotals(string $invoiceId): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $items = $invoice->items;

        $subtotal = $items->sum('subtotal');
        $taxTotal = $items->sum('tax_amount');
        $total = $items->sum('total');

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $total,
            'amount_remaining' => $total - $invoice->amount_paid,
        ]);
    }

    public function convertEstimateToInvoice(string $estimateId): Invoice
    {
        $estimate = Estimate::findOrFail($estimateId);
        $this->updateEstimateStatus($estimateId, 'approved', 'Converted to invoice');

        $items = $estimate->items->map(function ($item) {
            return [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_rate_id' => $item->tax_rate_id,
            ];
        })->toArray();

        return $this->createInvoice($estimate->organization_id, [
            'client_id' => $estimate->client_id,
            'client_name' => $estimate->client_name,
            'client_email' => $estimate->client_email,
            'subtotal' => $estimate->subtotal,
            'tax_total' => $estimate->tax_total,
            'total' => $estimate->total,
            'estimate_id' => $estimate->id,
            'items' => $items,
        ]);
    }

    // ==========================================
    // PAYMENTS
    // ==========================================

    public function makePayment(string $invoiceId, float $amount, string $method, ?string $reference = null, ?string $notes = null): Payment
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $orgId = $invoice->organization_id;

        $payment = Payment::create([
            'organization_id' => $orgId,
            'invoice_id' => $invoice->id,
            'payment_method' => $method,
            'amount' => $amount,
            'payment_date' => Carbon::today()->toDateString(),
            'transaction_reference' => $reference,
            'status' => 'completed',
            'notes' => $notes,
        ]);

        // Update Invoice status and amount paid
        $newAmountPaid = $invoice->amount_paid + $amount;
        $newAmountRemaining = max(0.00, $invoice->total - $newAmountPaid);
        
        $newStatus = 'partially paid';
        if ($newAmountRemaining <= 0.01) {
            $newStatus = 'paid';
        }

        $invoice->update([
            'amount_paid' => $newAmountPaid,
            'amount_remaining' => $newAmountRemaining,
            'status' => $newStatus,
        ]);

        // Immutable Ledger Entry for payment
        $this->createLedgerEntry(
            $orgId,
            Payment::class,
            $payment->id,
            'credit',
            $amount,
            "Received payment via {$method} for Invoice #{$invoice->invoice_number}",
            $reference
        );

        // Dispatch InvoicePaid if fully paid
        if ($newStatus === 'paid') {
            $this->eventBus->dispatch(new InvoicePaid($invoice->toArray(), $orgId));
            $this->notifyUser($orgId, "Invoice #{$invoice->invoice_number} Paid", "Payment of {$amount} KES received from {$invoice->client_name}. Status is fully paid.");
        } else {
            $this->notifyUser($orgId, "Payment Received for Invoice #{$invoice->invoice_number}", "Partial payment of {$amount} KES received. Status is partially paid.");
        }

        return $payment;
    }

    public function recordFailedPayment(string $invoiceId, float $amount, string $method, string $reason): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $orgId = $invoice->organization_id;

        $paymentData = [
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'payment_method' => $method,
            'reason' => $reason,
            'client_name' => $invoice->client_name,
            'client_email' => $invoice->client_email,
        ];

        $this->eventBus->dispatch(new PaymentFailed($paymentData, $orgId));
        $this->notifyUser($orgId, "Payment Failed for Invoice #{$invoice->invoice_number}", "Payment of {$amount} KES via {$method} failed: {$reason}.");
    }

    // ==========================================
    // EXPENSES
    // ==========================================

    public function createExpense(string $orgId, array $data): Expense
    {
        $expense = Expense::create([
            'organization_id' => $orgId,
            'category' => $data['category'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'] ?? Carbon::today()->toDateString(),
            'merchant' => $data['merchant'] ?? null,
            'description' => $data['description'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'status' => $data['status'] ?? 'paid',
        ]);

        // Immutable Ledger Entry for Expense (Debit outflow)
        $this->createLedgerEntry(
            $orgId,
            Expense::class,
            $expense->id,
            'debit',
            $expense->amount,
            "Expense recorded under {$expense->category} - Merchant: {$expense->merchant}",
            $expense->reference_number
        );

        return $expense;
    }

    // ==========================================
    // CREDIT NOTES & REFUNDS
    // ==========================================

    public function createCreditNote(string $orgId, string $invoiceId, float $amount, string $reason): CreditNote
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $creditNoteNumber = 'CN-' . date('Ymd') . '-' . rand(1000, 9999);

        $cn = CreditNote::create([
            'organization_id' => $orgId,
            'invoice_id' => $invoice->id,
            'credit_note_number' => $creditNoteNumber,
            'amount' => $amount,
            'issue_date' => Carbon::today()->toDateString(),
            'reason' => $reason,
        ]);

        // Immutable Ledger Entry for Credit Note (debit outflow adjustment)
        $this->createLedgerEntry(
            $orgId,
            CreditNote::class,
            $cn->id,
            'debit',
            $amount,
            "Credit Note {$creditNoteNumber} issued against Invoice #{$invoice->invoice_number}",
            $creditNoteNumber
        );

        // Adjust invoice balance
        $newRemaining = max(0.00, $invoice->amount_remaining - $amount);
        $invoice->update([
            'amount_remaining' => $newRemaining,
            'status' => $newRemaining <= 0.01 ? 'void' : $invoice->status,
        ]);

        return $cn;
    }

    public function createRefund(string $orgId, string $paymentId, float $amount, string $reason): Refund
    {
        $payment = Payment::findOrFail($paymentId);
        $refundNumber = 'RF-' . date('Ymd') . '-' . rand(1000, 9999);

        $refund = Refund::create([
            'organization_id' => $orgId,
            'payment_id' => $payment->id,
            'refund_number' => $refundNumber,
            'amount' => $amount,
            'refund_date' => Carbon::today()->toDateString(),
            'reason' => $reason,
        ]);

        // Immutable Ledger Entry for Refund (Debit outflow)
        $this->createLedgerEntry(
            $orgId,
            Refund::class,
            $refund->id,
            'debit',
            $amount,
            "Refund {$refundNumber} processed for Payment ref: {$payment->transaction_reference}",
            $refundNumber
        );

        return $refund;
    }

    // ==========================================
    // RECURRING BILLING
    // ==========================================

    public function createRecurringInvoice(string $orgId, array $data): RecurringInvoice
    {
        return RecurringInvoice::create([
            'organization_id' => $orgId,
            'client_id' => $data['client_id'] ?? null,
            'client_name' => $data['client_name'],
            'client_email' => $data['client_email'],
            'billing_cycle' => $data['billing_cycle'], // weekly, monthly, quarterly, yearly
            'start_date' => $data['start_date'] ?? Carbon::today()->toDateString(),
            'end_date' => $data['end_date'] ?? null,
            'status' => 'active',
            'template_data' => $data['template_data'], // items, subtotal, total, notes
        ]);
    }

    public function processRecurringBilling(string $orgId): int
    {
        $recurringInvoices = RecurringInvoice::where('organization_id', $orgId)
            ->where('status', 'active')
            ->get();

        $generatedCount = 0;

        foreach ($recurringInvoices as $recurring) {
            $lastGenerated = $recurring->last_generated_at ? Carbon::parse($recurring->last_generated_at) : null;
            $shouldGenerate = false;

            if (!$lastGenerated) {
                $shouldGenerate = Carbon::today()->greaterThanOrEqualTo($recurring->start_date);
            } else {
                $cycle = strtolower($recurring->billing_cycle);
                $nextDate = match($cycle) {
                    'weekly' => $lastGenerated->addWeek(),
                    'monthly' => $lastGenerated->addMonth(),
                    'quarterly' => $lastGenerated->addMonths(3),
                    'yearly' => $lastGenerated->addYear(),
                    default => null
                };

                if ($nextDate && Carbon::today()->greaterThanOrEqualTo($nextDate)) {
                    $shouldGenerate = true;
                }
            }

            if ($shouldGenerate) {
                // Generate Invoice
                $invoice = $this->createInvoice($orgId, [
                    'client_id' => $recurring->client_id,
                    'client_name' => $recurring->client_name,
                    'client_email' => $recurring->client_email,
                    'status' => 'sent', // auto send
                    'subtotal' => $recurring->template_data['subtotal'] ?? 0.00,
                    'tax_total' => $recurring->template_data['tax_total'] ?? 0.00,
                    'total' => $recurring->template_data['total'] ?? 0.00,
                    'invoice_date' => Carbon::today()->toDateString(),
                    'due_date' => Carbon::today()->addDays(14)->toDateString(),
                    'recurring_invoice_id' => $recurring->id,
                    'items' => $recurring->template_data['items'] ?? [],
                ]);

                $recurring->update([
                    'last_generated_at' => Carbon::now(),
                ]);

                $this->eventBus->dispatch(new RecurringInvoiceGenerated($invoice->toArray(), $orgId));
                $this->notifyUser($orgId, "Recurring Invoice Generated", "Invoice #{$invoice->invoice_number} generated for {$recurring->client_name}.");
                
                $generatedCount++;
            }
        }

        return $generatedCount;
    }

    // ==========================================
    // LEDGER TRANSACTIONS (IMMUTABLE)
    // ==========================================

    public function createLedgerEntry(
        string $orgId,
        string $ledgerableType,
        string $ledgerableId,
        string $type,
        float $amount,
        string $description,
        ?string $ref = null
    ): Transaction {
        return Transaction::create([
            'organization_id' => $orgId,
            'ledgerable_type' => $ledgerableType,
            'ledgerable_id' => $ledgerableId,
            'type' => $type, // debit, credit
            'amount' => $amount,
            'transaction_date' => Carbon::today()->toDateString(),
            'description' => $description,
            'reference_number' => $ref,
        ]);
    }

    public function getLedgerData(string $orgId): Collection
    {
        return Transaction::where('organization_id', $orgId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ==========================================
    // DASHBOARD & REPORTS SERVICES
    // ==========================================

    public function getDashboardStats(string $orgId): array
    {
        // Revenue (Sum of all completed payments / fully paid or partially paid invoices)
        $revenue = (float) Payment::where('organization_id', $orgId)
            ->where('status', 'completed')
            ->sum('amount');

        // Expenses
        $expenses = (float) Expense::where('organization_id', $orgId)
            ->where('status', 'paid')
            ->sum('amount');

        $profit = $revenue - $expenses;

        // Outstanding Invoices
        $outstanding = (float) Invoice::where('organization_id', $orgId)
            ->whereIn('status', ['sent', 'viewed', 'partially paid'])
            ->sum('amount_remaining');

        // Taxes collected (from invoice items where invoice is paid or partially paid)
        $taxes = (float) Invoice::where('organization_id', $orgId)
            ->whereIn('status', ['paid', 'partially paid'])
            ->sum('tax_total');

        // Estimate MRR / ARR from active recurring invoices
        $activeRecurring = RecurringInvoice::where('organization_id', $orgId)
            ->where('status', 'active')
            ->get();

        $mrr = 0.00;
        foreach ($activeRecurring as $recurring) {
            $total = (float) ($recurring->template_data['total'] ?? 0.00);
            $cycle = strtolower($recurring->billing_cycle);
            
            $mrr += match($cycle) {
                'weekly' => $total * 4.33,
                'monthly' => $total,
                'quarterly' => $total / 3,
                'yearly' => $total / 12,
                default => 0.00
            };
        }

        $arr = $mrr * 12;

        // Cash Flow timeline (credits vs debits by date)
        $ledger = $this->getLedgerData($orgId);
        $cashFlow = [];
        foreach ($ledger as $tx) {
            $date = Carbon::parse($tx->transaction_date)->format('Y-M-d');
            if (!isset($cashFlow[$date])) {
                $cashFlow[$date] = ['date' => $date, 'credit' => 0.00, 'debit' => 0.00];
            }
            if ($tx->type === 'credit') {
                $cashFlow[$date]['credit'] += (float) $tx->amount;
            } else {
                $cashFlow[$date]['debit'] += (float) $tx->amount;
            }
        }

        return [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'profit' => $profit,
            'outstanding' => $outstanding,
            'taxes' => $taxes,
            'mrr' => $mrr,
            'arr' => $arr,
            'cashFlow' => array_values($cashFlow),
        ];
    }

    public function getFinanceReport(string $orgId, string $type, ?string $startDate = null, ?string $endDate = null): array
    {
        $queryInvoice = Invoice::where('organization_id', $orgId);
        $queryPayment = Payment::where('organization_id', $orgId);
        $queryExpense = Expense::where('organization_id', $orgId);

        if ($startDate) {
            $queryInvoice->where('invoice_date', '>=', $startDate);
            $queryPayment->where('payment_date', '>=', $startDate);
            $queryExpense->where('expense_date', '>=', $startDate);
        }
        if ($endDate) {
            $queryInvoice->where('invoice_date', '<=', $endDate);
            $queryPayment->where('payment_date', '<=', $endDate);
            $queryExpense->where('expense_date', '<=', $endDate);
        }

        $invoices = $queryInvoice->get();
        $payments = $queryPayment->get();
        $expenses = $queryExpense->get();

        if ($type === 'p_and_l') {
            $income = $payments->where('status', 'completed')->sum('amount');
            $outflow = $expenses->where('status', 'paid')->sum('amount');
            return [
                'type' => 'Profit & Loss',
                'total_income' => $income,
                'total_expenses' => $outflow,
                'net_profit' => $income - $outflow,
            ];
        }

        if ($type === 'ar') {
            return [
                'type' => 'Accounts Receivable',
                'total_receivable' => $invoices->whereIn('status', ['pending', 'sent', 'viewed', 'partially paid'])->sum('amount_remaining'),
                'draft_invoices_value' => $invoices->where('status', 'draft')->sum('total'),
                'overdue_invoices_value' => $invoices->where('status', 'overdue')->sum('amount_remaining'),
            ];
        }

        if ($type === 'tax') {
            return [
                'type' => 'Tax Summary',
                'tax_collected' => $invoices->whereIn('status', ['paid', 'partially paid'])->sum('tax_total'),
                'tax_pending' => $invoices->whereIn('status', ['sent', 'viewed', 'partially paid'])->sum('tax_total') - $invoices->whereIn('status', ['sent', 'viewed', 'partially paid'])->sum('amount_paid') * 0.16, // approximate Kenyan standard
            ];
        }

        return [
            'type' => 'General Financials',
            'invoices_count' => $invoices->count(),
            'payments_count' => $payments->count(),
            'expenses_count' => $expenses->count(),
        ];
    }

    // ==========================================
    // NOTIFICATION INTEGRATION
    // ==========================================

    protected function notifyUser(string $orgId, string $title, string $body): void
    {
        // Simple helper to find any staff / owner / active users and send them notifications
        $admin = \App\Models\User::first(); // Send to the first platform user for demo/notification integration
        if ($admin) {
            $this->notificationService->send(
                userId: $admin->id,
                titleOrTemplate: $title,
                bodyOrEventName: $body,
                type: 'info',
                category: 'billing',
                priority: 'normal',
                organizationId: $orgId
            );
        }
    }
}
