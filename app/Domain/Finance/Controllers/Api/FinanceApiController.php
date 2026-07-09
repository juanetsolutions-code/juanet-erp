<?php

namespace App\Domain\Finance\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Services\FinanceService;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Estimate;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\RecurringInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FinanceApiController extends Controller
{
    protected FinanceService $service;

    public function __construct(FinanceService $service)
    {
        $this->service = $service;
    }

    protected function getOrgId(Request $request): string
    {
        // Fallback organization ID for demonstration/tenant contexts
        return $request->header('X-Organization-ID') ?? 'org-001';
    }

    public function dashboard(Request $request): JsonResponse
    {
        $orgId = $this->getOrgId($request);
        $stats = $this->service->getDashboardStats($orgId);

        return response()->json($stats);
    }

    public function ledger(Request $request): JsonResponse
    {
        $orgId = $this->getOrgId($request);
        $ledgerData = $this->service->getLedgerData($orgId);

        return response()->json([
            'data' => $ledgerData
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $orgId = $this->getOrgId($request);
        $invoices = Invoice::where('organization_id', $orgId)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $invoices
        ]);
    }

    public function createInvoice(Request $request): JsonResponse
    {
        $orgId = $this->getOrgId($request);
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0.00',
            'items.*.tax_rate_id' => 'nullable|string',
        ]);

        $invoice = $this->service->createInvoice($orgId, $validated);

        return response()->json([
            'message' => 'Invoice created successfully.',
            'data' => $invoice
        ], 201);
    }

    public function payInvoice(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:M-PESA,Card,Bank Transfer,Cash,Manual',
            'transaction_reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $payment = $this->service->makePayment(
            invoiceId: $id,
            amount: (float) $validated['amount'],
            method: $validated['payment_method'],
            reference: $validated['transaction_reference'] ?? null,
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Payment processed successfully.',
            'data' => $payment
        ]);
    }

    public function convertEstimate(Request $request, string $id): JsonResponse
    {
        $invoice = $this->service->convertEstimateToInvoice($id);

        return response()->json([
            'message' => 'Estimate converted to invoice successfully.',
            'data' => $invoice
        ]);
    }

    public function estimates(Request $request): JsonResponse
    {
        $orgId = $this->getOrgId($request);
        $estimates = Estimate::where('organization_id', $orgId)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $estimates
        ]);
    }

    public function expenses(Request $request): JsonResponse
    {
        $orgId = $this->getOrgId($request);
        $expenses = Expense::where('organization_id', $orgId)
            ->orderBy('expense_date', 'desc')
            ->get();

        return response()->json([
            'data' => $expenses
        ]);
    }

    public function createExpense(Request $request): JsonResponse
    {
        $orgId = $this->getOrgId($request);
        $validated = $request->validate([
            'category' => 'required|string|in:Software,Hosting,Domains,Marketing,Payroll,Equipment,Travel,Miscellaneous',
            'amount' => 'required|numeric|min:0.01',
            'merchant' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reference_number' => 'nullable|string',
            'expense_date' => 'nullable|date',
        ]);

        $expense = $this->service->createExpense($orgId, $validated);

        return response()->json([
            'message' => 'Expense logged successfully.',
            'data' => $expense
        ], 201);
    }

    public function recurring(Request $request): JsonResponse
    {
        $orgId = $this->getOrgId($request);
        $recurring = RecurringInvoice::where('organization_id', $orgId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $recurring
        ]);
    }

    public function processRecurring(Request $request): JsonResponse
    {
        $orgId = $this->getOrgId($request);
        $count = $this->service->processRecurringBilling($orgId);

        return response()->json([
            'message' => "Recurring billing process complete. {$count} invoices generated."
        ]);
    }

    public function getReport(Request $request): JsonResponse
    {
        $orgId = $this->getOrgId($request);
        $validated = $request->validate([
            'type' => 'required|string|in:p_and_l,ar,tax',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $report = $this->service->getFinanceReport(
            orgId: $orgId,
            type: $validated['type'],
            startDate: $validated['start_date'] ?? null,
            endDate: $validated['end_date'] ?? null
        );

        return response()->json([
            'data' => $report
        ]);
    }
}
