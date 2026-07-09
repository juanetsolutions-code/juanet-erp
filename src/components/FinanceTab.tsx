import React, { useState } from "react";
import {
  DollarSign,
  FileText,
  FileSpreadsheet,
  ArrowUpRight,
  ArrowDownLeft,
  Calendar,
  CheckCircle,
  XCircle,
  Clock,
  Plus,
  RefreshCw,
  Search,
  Filter,
  Download,
  Percent,
  TrendingUp,
  CreditCard,
  Building,
  User,
  Users,
  Briefcase,
  Layers,
  Sparkles,
  Award,
  BookOpen,
  Eye,
  Trash2,
  AlertCircle,
  ChevronRight,
  Scissors,
  Repeat,
  Printer,
  ShoppingCart
} from "lucide-react";

// Matches DB Models exactly
interface TaxRate {
  id: string;
  name: string;
  rate: number;
  is_active: boolean;
}

interface InvoiceItem {
  id: string;
  description: string;
  quantity: number;
  unit_price: number;
  tax_rate_id: string | null;
  subtotal: number;
  tax_amount: number;
  total: number;
}

interface Invoice {
  id: string;
  invoice_number: string;
  client_id: string | null;
  client_name: string;
  client_email: string;
  status: "draft" | "pending" | "sent" | "viewed" | "partially paid" | "paid" | "overdue" | "cancelled" | "void";
  subtotal: number;
  tax_total: number;
  total: number;
  amount_paid: number;
  amount_remaining: number;
  invoice_date: string;
  due_date: string;
  estimate_id: string | null;
  project_id: string | null;
  recurring_invoice_id: string | null;
  payment_link: string;
  items: InvoiceItem[];
}

interface EstimateItem {
  id: string;
  description: string;
  quantity: number;
  unit_price: number;
  tax_rate_id: string | null;
  subtotal: number;
  tax_amount: number;
  total: number;
}

interface Estimate {
  id: string;
  estimate_number: string;
  client_id: string | null;
  client_name: string;
  client_email: string;
  status: "draft" | "pending" | "sent" | "approved" | "declined";
  subtotal: number;
  tax_total: number;
  total: number;
  estimate_date: string;
  expiry_date: string;
  terms_conditions: string;
  notes: string;
  revision_history: {
    version: number;
    date: string;
    action: string;
    status: string;
    notes?: string;
  }[];
  items: EstimateItem[];
}

interface Payment {
  id: string;
  invoice_id: string;
  invoice_number: string;
  payment_method: "M-PESA" | "Card" | "Bank Transfer" | "Cash" | "Manual";
  amount: number;
  payment_date: string;
  transaction_reference: string;
  status: "pending" | "completed" | "failed" | "refunded";
  notes: string;
}

interface Expense {
  id: string;
  category: "Software" | "Hosting" | "Domains" | "Marketing" | "Payroll" | "Equipment" | "Travel" | "Miscellaneous";
  amount: number;
  expense_date: string;
  merchant: string;
  description: string;
  reference_number: string;
  status: "pending" | "approved" | "paid" | "rejected";
}

interface LedgerEntry {
  id: string;
  ledgerable_type: string;
  ledgerable_id: string;
  type: "debit" | "credit";
  amount: number;
  transaction_date: string;
  description: string;
  reference_number: string | null;
}

interface RecurringInvoice {
  id: string;
  client_name: string;
  client_email: string;
  billing_cycle: "weekly" | "monthly" | "quarterly" | "yearly";
  start_date: string;
  end_date: string | null;
  last_generated_at: string | null;
  status: "active" | "paused" | "completed";
  template_data: {
    subtotal: number;
    tax_total: number;
    total: number;
    items: any[];
  };
}

export default function FinanceTab() {
  const [activeSubTab, setActiveSubTab] = useState<"dashboard" | "invoices" | "estimates" | "expenses" | "ledger" | "recurring" | "integrations" | "reports">("dashboard");

  // Mock Tax Rates
  const [taxRates] = useState<TaxRate[]>([
    { id: "tax-16", name: "Kenya VAT (16%)", rate: 16.0, is_active: true },
    { id: "tax-0", name: "Zero Rated (0%)", rate: 0.0, is_active: true }
  ]);

  // Initial High Fidelity Invoice Data
  const [invoices, setInvoices] = useState<Invoice[]>([
    {
      id: "INV-2026-0001",
      invoice_number: "INV-2026-0001",
      client_id: "client-001",
      client_name: "Safaricom PLC",
      client_email: "billing@safaricom.co.ke",
      status: "paid",
      subtotal: 150000.0,
      tax_total: 24000.0,
      total: 174000.0,
      amount_paid: 174000.0,
      amount_remaining: 0.0,
      invoice_date: "2026-07-01",
      due_date: "2026-07-31",
      estimate_id: "EST-2026-001",
      project_id: null,
      recurring_invoice_id: null,
      payment_link: "https://juanet.cloud/pay/invoice/INV-2026-0001",
      items: [
        {
          id: "item-1",
          description: "Cloud ERP Core Module Implementation & Tenant Sync Setup",
          quantity: 1,
          unit_price: 150000.0,
          tax_rate_id: "tax-16",
          subtotal: 150000.0,
          tax_amount: 24000.0,
          total: 174000.0
        }
      ]
    },
    {
      id: "INV-2026-0002",
      invoice_number: "INV-2026-0002",
      client_id: "client-002",
      client_name: "Equity Bank Kenya",
      client_email: "finance@equitybank.co.ke",
      status: "partially paid",
      subtotal: 450000.0,
      tax_total: 72000.0,
      total: 522000.0,
      amount_paid: 200000.0,
      amount_remaining: 322000.0,
      invoice_date: "2026-07-05",
      due_date: "2026-08-05",
      estimate_id: null,
      project_id: "101",
      recurring_invoice_id: null,
      payment_link: "https://juanet.cloud/pay/invoice/INV-2026-0002",
      items: [
        {
          id: "item-2",
          description: "High Fidelity API Gateway Integration (Fintech Core Sandbox)",
          quantity: 1,
          unit_price: 450000.0,
          tax_rate_id: "tax-16",
          subtotal: 450000.0,
          tax_amount: 72000.0,
          total: 522000.0
        }
      ]
    },
    {
      id: "INV-2026-0003",
      invoice_number: "INV-2026-0003",
      client_id: "client-003",
      client_name: "Bamburi Cement",
      client_email: "supplychain@bamburi.lafarge.com",
      status: "sent",
      subtotal: 80000.0,
      tax_total: 12800.0,
      total: 92800.0,
      amount_paid: 0.0,
      amount_remaining: 92800.0,
      invoice_date: "2026-07-08",
      due_date: "2026-07-22",
      estimate_id: null,
      project_id: null,
      recurring_invoice_id: null,
      payment_link: "https://juanet.cloud/pay/invoice/INV-2026-0003",
      items: [
        {
          id: "item-3",
          description: "Workforce Planner & Skills Matrix Dynamic Module customization",
          quantity: 1,
          unit_price: 80000.0,
          tax_rate_id: "tax-16",
          subtotal: 80000.0,
          tax_amount: 12800.0,
          total: 92800.0
        }
      ]
    }
  ]);

  // Initial High Fidelity Estimate Data
  const [estimates, setEstimates] = useState<Estimate[]>([
    {
      id: "EST-2026-001",
      estimate_number: "EST-2026-001",
      client_id: "client-001",
      client_name: "Safaricom PLC",
      client_email: "billing@safaricom.co.ke",
      status: "approved",
      subtotal: 150000.0,
      tax_total: 24000.0,
      total: 174000.0,
      estimate_date: "2026-06-25",
      expiry_date: "2026-07-25",
      terms_conditions: "Payment due within 30 days of conversion to invoice.",
      notes: "SaaS migration scoping phase.",
      revision_history: [
        { version: 1, date: "2026-06-25 10:00", action: "Created estimate", status: "draft" },
        { version: 2, date: "2026-06-27 14:30", action: "Approved and signed by Client PM", status: "approved" }
      ],
      items: [
        {
          id: "est-item-1",
          description: "Cloud ERP Core Module Implementation & Tenant Sync Setup",
          quantity: 1,
          unit_price: 150000.0,
          tax_rate_id: "tax-16",
          subtotal: 150000.0,
          tax_amount: 24000.0,
          total: 174000.0
        }
      ]
    },
    {
      id: "EST-2026-002",
      estimate_number: "EST-2026-002",
      client_id: "client-004",
      client_name: "KCB Bank Kenya",
      client_email: "digital@kcbgroup.com",
      status: "pending",
      subtotal: 620000.0,
      tax_total: 99200.0,
      total: 719200.0,
      estimate_date: "2026-07-06",
      expiry_date: "2026-08-06",
      terms_conditions: "Standard financial service integration agreement applies.",
      notes: "Pending security review audit.",
      revision_history: [
        { version: 1, date: "2026-07-06 09:15", action: "Created estimate & sent to Digital Banking lead", status: "pending" }
      ],
      items: [
        {
          id: "est-item-2",
          description: "Fintech Sandbox Orchestrator with Multi-tenant isolation features",
          quantity: 1,
          unit_price: 620000.0,
          tax_rate_id: "tax-16",
          subtotal: 620000.0,
          tax_amount: 99200.0,
          total: 719200.0
        }
      ]
    }
  ]);

  // Initial High Fidelity Payments Data
  const [payments, setPayments] = useState<Payment[]>([
    {
      id: "PAY-001",
      invoice_id: "INV-2026-0001",
      invoice_number: "INV-2026-0001",
      payment_method: "M-PESA",
      amount: 174000.0,
      payment_date: "2026-07-02",
      transaction_reference: "QGR49FLK38",
      status: "completed",
      notes: "Lipa Na M-PESA B2B portal hook transaction"
    },
    {
      id: "PAY-002",
      invoice_id: "INV-2026-0002",
      invoice_number: "INV-2026-0002",
      payment_method: "Card",
      amount: 200000.0,
      payment_date: "2026-07-06",
      transaction_reference: "CHG_920381029",
      status: "completed",
      notes: "Partial payment via Stripe Integration Gateway"
    }
  ]);

  // Initial High Fidelity Expenses
  const [expenses, setExpenses] = useState<Expense[]>([
    {
      id: "EXP-001",
      category: "Hosting",
      amount: 14500.0,
      expense_date: "2026-07-01",
      merchant: "Amazon Web Services",
      description: "AWS Cloud Infrastructure production environment hosting",
      reference_number: "AWS-INV-99201",
      status: "paid"
    },
    {
      id: "EXP-002",
      category: "Software",
      amount: 4200.0,
      expense_date: "2026-07-03",
      merchant: "GitHub Inc.",
      description: "GitHub Enterprise Monorepo licensing seats",
      reference_number: "GH-2026-44012",
      status: "paid"
    },
    {
      id: "EXP-003",
      category: "Payroll",
      amount: 120000.0,
      expense_date: "2026-07-05",
      merchant: "External QA Consultant",
      description: "Payment for security audit contract validation",
      reference_number: "PAY-QA-JULY",
      status: "paid"
    }
  ]);

  // Immutable Ledger Data (Created from Invoices, Payments, Expenses)
  const [ledger, setLedger] = useState<LedgerEntry[]>([
    {
      id: "TX-001",
      ledgerable_type: "Invoice",
      ledgerable_id: "INV-2026-0001",
      type: "credit",
      amount: 174000.0,
      transaction_date: "2026-07-01",
      description: "Invoice #INV-2026-0001 issued to Safaricom PLC",
      reference_number: "INV-2026-0001"
    },
    {
      id: "TX-002",
      ledgerable_type: "Payment",
      ledgerable_id: "PAY-001",
      type: "credit",
      amount: 174000.0,
      transaction_date: "2026-07-02",
      description: "Received payment via M-PESA for Invoice #INV-2026-0001",
      reference_number: "QGR49FLK38"
    },
    {
      id: "TX-003",
      ledgerable_type: "Expense",
      ledgerable_id: "EXP-001",
      type: "debit",
      amount: 14500.0,
      transaction_date: "2026-07-01",
      description: "Expense recorded under Hosting - Merchant: Amazon Web Services",
      reference_number: "AWS-INV-99201"
    },
    {
      id: "TX-004",
      ledgerable_type: "Invoice",
      ledgerable_id: "INV-2026-0002",
      type: "credit",
      amount: 522000.0,
      transaction_date: "2026-07-05",
      description: "Invoice #INV-2026-0002 issued to Equity Bank Kenya",
      reference_number: "INV-2026-0002"
    },
    {
      id: "TX-005",
      ledgerable_type: "Payment",
      ledgerable_id: "PAY-002",
      type: "credit",
      amount: 200000.0,
      transaction_date: "2026-07-06",
      description: "Received payment via Card for Invoice #INV-2026-0002",
      reference_number: "CHG_920381029"
    }
  ]);

  // Recurring Billing Templates
  const [recurringInvoices, setRecurringInvoices] = useState<RecurringInvoice[]>([
    {
      id: "REC-001",
      client_name: "Apex Global Group",
      client_email: "support@apexglobal.co",
      billing_cycle: "monthly",
      start_date: "2026-07-01",
      end_date: "2027-07-01",
      last_generated_at: "2026-07-01",
      status: "active",
      template_data: {
        subtotal: 50000.00,
        tax_total: 8000.00,
        total: 58000.00,
        items: [
          { description: "SaaS Dev Maintenance and SLA Support Tier A", quantity: 1, unit_price: 50000.00, tax_rate_id: "tax-16" }
        ]
      }
    }
  ]);

  // Invoice / Estimate form state
  const [isInvoiceModalOpen, setIsInvoiceModalOpen] = useState(false);
  const [newInvoice, setNewInvoice] = useState({
    client_name: "",
    client_email: "",
    invoice_date: "2026-07-09",
    due_date: "2026-08-09",
    description: "",
    unit_price: 0,
    quantity: 1,
    tax_rate_id: "tax-16"
  });

  // Expense form state
  const [isExpenseModalOpen, setIsExpenseModalOpen] = useState(false);
  const [newExpense, setNewExpense] = useState({
    category: "Software" as any,
    amount: 0,
    merchant: "",
    description: "",
    reference_number: ""
  });

  // Simulator notifications state
  const [simulatorMessage, setSimulatorMessage] = useState<string | null>(null);

  // Compute stats
  const totalRevenue = payments.reduce((sum, pay) => sum + pay.amount, 0);
  const totalExpenses = expenses.reduce((sum, exp) => sum + exp.amount, 0);
  const totalProfit = totalRevenue - totalExpenses;
  const outstandingInvoicesValue = invoices.reduce((sum, inv) => {
    if (["sent", "viewed", "partially paid"].includes(inv.status)) {
      return sum + inv.amount_remaining;
    }
    return sum;
  }, 0);
  const totalTaxes = invoices.reduce((sum, inv) => {
    if (inv.status === "paid" || inv.status === "partially paid") {
      return sum + inv.tax_total;
    }
    return sum;
  }, 0);
  const activeMRR = recurringInvoices.reduce((sum, rec) => {
    if (rec.status === "active") {
      const cycle = rec.billing_cycle;
      const total = rec.template_data.total;
      if (cycle === "weekly") return sum + total * 4.33;
      if (cycle === "monthly") return sum + total;
      if (cycle === "quarterly") return sum + total / 3;
      if (cycle === "yearly") return sum + total / 12;
    }
    return sum;
  }, 0);

  // Form Handlers
  const handleCreateInvoice = (e: React.FormEvent) => {
    e.preventDefault();
    const subtotal = newInvoice.unit_price * newInvoice.quantity;
    const rate = taxRates.find(r => r.id === newInvoice.tax_rate_id)?.rate || 0;
    const tax_amount = subtotal * (rate / 100);
    const total = subtotal + tax_amount;

    const invoiceNum = `INV-2026-${String(invoices.length + 1).padStart(4, "0")}`;
    const invoiceId = invoiceNum;

    const invoiceItem: InvoiceItem = {
      id: `item-${Date.now()}`,
      description: newInvoice.description,
      quantity: newInvoice.quantity,
      unit_price: newInvoice.unit_price,
      tax_rate_id: newInvoice.tax_rate_id,
      subtotal,
      tax_amount,
      total
    };

    const created: Invoice = {
      id: invoiceId,
      invoice_number: invoiceNum,
      client_id: null,
      client_name: newInvoice.client_name,
      client_email: newInvoice.client_email,
      status: "sent",
      subtotal,
      tax_total: tax_amount,
      total,
      amount_paid: 0.0,
      amount_remaining: total,
      invoice_date: newInvoice.invoice_date,
      due_date: newInvoice.due_date,
      estimate_id: null,
      project_id: null,
      recurring_invoice_id: null,
      payment_link: `https://juanet.cloud/pay/invoice/${invoiceNum}`,
      items: [invoiceItem]
    };

    // Add to state
    setInvoices([created, ...invoices]);

    // Immutable Ledger Entry (accounts receivable debited)
    const ledgerEntry: LedgerEntry = {
      id: `TX-${Date.now()}`,
      ledgerable_type: "Invoice",
      ledgerable_id: invoiceId,
      type: "credit",
      amount: total,
      transaction_date: newInvoice.invoice_date,
      description: `Invoice #${invoiceNum} issued to ${newInvoice.client_name}`,
      reference_number: invoiceNum
    };
    setLedger([ledgerEntry, ...ledger]);

    setIsInvoiceModalOpen(false);
    showSimulatorNotification(`✅ Invoice ${invoiceNum} created successfully. Dispatching 'finance.invoice.sent' event & generated ledger entry!`);
  };

  const handleCreateExpense = (e: React.FormEvent) => {
    e.preventDefault();
    const expenseNum = `EXP-${Date.now()}`;
    const created: Expense = {
      id: expenseNum,
      category: newExpense.category,
      amount: newExpense.amount,
      expense_date: new Date().toISOString().split("T")[0],
      merchant: newExpense.merchant,
      description: newExpense.description,
      reference_number: newExpense.reference_number || `EXP-REF-${rand()}`,
      status: "paid"
    };

    setExpenses([created, ...expenses]);

    // Ledger Debit Entry
    const ledgerEntry: LedgerEntry = {
      id: `TX-${Date.now()}`,
      ledgerable_type: "Expense",
      ledgerable_id: expenseNum,
      type: "debit",
      amount: newExpense.amount,
      transaction_date: created.expense_date,
      description: `Expense recorded under ${newExpense.category} - Merchant: ${newExpense.merchant}`,
      reference_number: created.reference_number
    };
    setLedger([ledgerEntry, ...ledger]);

    setIsExpenseModalOpen(false);
    showSimulatorNotification(`✅ Expense recorded. Immutable Ledger updated with DR ${newExpense.amount} KES.`);
  };

  // Convert Estimate to Invoice
  const convertEstimate = (estId: string) => {
    const estimate = estimates.find(e => e.id === estId);
    if (!estimate) return;

    // Update status to approved
    setEstimates(estimates.map(e => e.id === estId ? { ...e, status: "approved" } : e));

    const invoiceNum = `INV-2026-${String(invoices.length + 1).padStart(4, "0")}`;
    const invoiceId = invoiceNum;

    const invoiceItems: InvoiceItem[] = estimate.items.map(item => ({
      id: `item-${Date.now()}-${item.id}`,
      description: item.description,
      quantity: item.quantity,
      unit_price: item.unit_price,
      tax_rate_id: item.tax_rate_id,
      subtotal: item.subtotal,
      tax_amount: item.tax_amount,
      total: item.total
    }));

    const created: Invoice = {
      id: invoiceId,
      invoice_number: invoiceNum,
      client_id: estimate.client_id,
      client_name: estimate.client_name,
      client_email: estimate.client_email,
      status: "sent",
      subtotal: estimate.subtotal,
      tax_total: estimate.tax_total,
      total: estimate.total,
      amount_paid: 0.0,
      amount_remaining: estimate.total,
      invoice_date: new Date().toISOString().split("T")[0],
      due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split("T")[0],
      estimate_id: estimate.id,
      project_id: null,
      recurring_invoice_id: null,
      payment_link: `https://juanet.cloud/pay/invoice/${invoiceNum}`,
      items: invoiceItems
    };

    setInvoices([created, ...invoices]);

    // Ledger entry
    const ledgerEntry: LedgerEntry = {
      id: `TX-${Date.now()}`,
      ledgerable_type: "Invoice",
      ledgerable_id: invoiceId,
      type: "credit",
      amount: estimate.total,
      transaction_date: created.invoice_date,
      description: `Invoice #${invoiceNum} generated from Estimate ${estimate.estimate_number}`,
      reference_number: invoiceNum
    };
    setLedger([ledgerEntry, ...ledger]);

    showSimulatorNotification(`🚀 Convert Complete! Estimate ${estimate.estimate_number} converted to Invoice ${invoiceNum}. Created ledger entries & dispatched domain events.`);
  };

  // Pay Invoice Simulator
  const payInvoice = (invId: string, method: any) => {
    const inv = invoices.find(i => i.id === invId);
    if (!inv) return;

    const payAmount = inv.amount_remaining;
    if (payAmount <= 0) return;

    const payNum = `PAY-${Date.now()}`;
    const txRef = `TXREF${rand()}`;

    const newPayment: Payment = {
      id: payNum,
      invoice_id: inv.id,
      invoice_number: inv.invoice_number,
      payment_method: method,
      amount: payAmount,
      payment_date: new Date().toISOString().split("T")[0],
      transaction_reference: txRef,
      status: "completed",
      notes: `Online portal payment simulation via ${method}`
    };

    setPayments([newPayment, ...payments]);

    setInvoices(invoices.map(i => {
      if (i.id === invId) {
        return {
          ...i,
          amount_paid: i.total,
          amount_remaining: 0,
          status: "paid"
        };
      }
      return i;
    }));

    // Ledger Entry (credit cash/bank)
    const ledgerEntry: LedgerEntry = {
      id: `TX-${Date.now()}`,
      ledgerable_type: "Payment",
      ledgerable_id: payNum,
      type: "credit",
      amount: payAmount,
      transaction_date: newPayment.payment_date,
      description: `Payment of ${payAmount} KES received for Invoice #${inv.invoice_number}`,
      reference_number: txRef
    };
    setLedger([ledgerEntry, ...ledger]);

    showSimulatorNotification(`💳 Payment Successful! Paid ${payAmount} KES via ${method}. Status set to PAID. Dispatched 'finance.invoice.paid'.`);
  };

  // Trigger Recurring Generator Simulation
  const triggerRecurringSimulation = () => {
    // Generate Invoice from active template
    const activeTemplates = recurringInvoices.filter(t => t.status === "active");
    if (activeTemplates.length === 0) return;

    activeTemplates.forEach(template => {
      const invoiceNum = `INV-REC-${Date.now().toString().slice(-4)}`;
      const invoiceId = invoiceNum;

      const invoiceItems = template.template_data.items.map((item, idx) => ({
        id: `rec-item-${idx}-${Date.now()}`,
        description: item.description,
        quantity: item.quantity,
        unit_price: item.unit_price,
        tax_rate_id: item.tax_rate_id,
        subtotal: item.quantity * item.unit_price,
        tax_amount: (item.quantity * item.unit_price) * 0.16,
        total: (item.quantity * item.unit_price) * 1.16
      }));

      const created: Invoice = {
        id: invoiceId,
        invoice_number: invoiceNum,
        client_id: null,
        client_name: template.client_name,
        client_email: template.client_email,
        status: "sent",
        subtotal: template.template_data.subtotal,
        tax_total: template.template_data.tax_total,
        total: template.template_data.total,
        amount_paid: 0,
        amount_remaining: template.template_data.total,
        invoice_date: new Date().toISOString().split("T")[0],
        due_date: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split("T")[0],
        estimate_id: null,
        project_id: null,
        recurring_invoice_id: template.id,
        payment_link: `https://juanet.cloud/pay/invoice/${invoiceNum}`,
        items: invoiceItems
      };

      setInvoices([created, ...invoices]);

      const ledgerEntry: LedgerEntry = {
        id: `TX-${Date.now()}`,
        ledgerable_type: "Invoice",
        ledgerable_id: invoiceId,
        type: "credit",
        amount: template.template_data.total,
        transaction_date: created.invoice_date,
        description: `Recurring billing generation for ${template.client_name} - Invoice #${invoiceNum}`,
        reference_number: invoiceNum
      };
      setLedger([ledgerEntry, ...ledger]);

      // Update template last run
      setRecurringInvoices(recurringInvoices.map(r => r.id === template.id ? { ...r, last_generated_at: new Date().toISOString().split("T")[0] } : r));
    });

    showSimulatorNotification(`⚡ Recurring Billing Engine Processed! Generated active subscription invoices & synced immutable double-entry ledger.`);
  };

  // Integration Simulators
  const runMilestoneIntegration = () => {
    // Simulate converting a fixed price milestone to invoice
    const milestoneAmount = 185000.00;
    const invoiceNum = `INV-PROJ-${Date.now().toString().slice(-4)}`;
    const invoiceId = invoiceNum;

    const created: Invoice = {
      id: invoiceId,
      invoice_number: invoiceNum,
      client_id: null,
      client_name: "Carrefour East Africa",
      client_email: "billing@carrefour.co.ke",
      status: "pending",
      subtotal: milestoneAmount,
      tax_total: milestoneAmount * 0.16,
      total: milestoneAmount * 1.16,
      amount_paid: 0,
      amount_remaining: milestoneAmount * 1.16,
      invoice_date: new Date().toISOString().split("T")[0],
      due_date: new Date(Date.now() + 15 * 24 * 60 * 60 * 1000).toISOString().split("T")[0],
      estimate_id: null,
      project_id: "102", // simulated project ID
      recurring_invoice_id: null,
      payment_link: `https://juanet.cloud/pay/invoice/${invoiceNum}`,
      items: [
        {
          id: `item-${Date.now()}`,
          description: "Project Milestone 3: Database Clustering & Load Balancer Verification",
          quantity: 1,
          unit_price: milestoneAmount,
          tax_rate_id: "tax-16",
          subtotal: milestoneAmount,
          tax_amount: milestoneAmount * 0.16,
          total: milestoneAmount * 1.16
        }
      ]
    };

    setInvoices([created, ...invoices]);

    const ledgerEntry: LedgerEntry = {
      id: `TX-${Date.now()}`,
      ledgerable_type: "Invoice",
      ledgerable_id: invoiceId,
      type: "credit",
      amount: milestoneAmount * 1.16,
      transaction_date: created.invoice_date,
      description: `Project milestone invoice #${invoiceNum} generated (Carrefour East Africa)`,
      reference_number: invoiceNum
    };
    setLedger([ledgerEntry, ...ledger]);

    showSimulatorNotification(`🏢 Project Milestone Integrated! Milestone converted to Invoice ${invoiceNum} with 16% VAT & posted to Ledger.`);
  };

  const runWorkforceIntegration = () => {
    // Billable time entries to invoice item
    const hours = 45;
    const rate = 3500; // Hourly rate
    const billableAmount = hours * rate; // 157,500
    const invoiceNum = `INV-WORK-${Date.now().toString().slice(-4)}`;
    const invoiceId = invoiceNum;

    const created: Invoice = {
      id: invoiceId,
      invoice_number: invoiceNum,
      client_id: null,
      client_name: "Airtel Kenya PLC",
      client_email: "finance@ke.airtel.com",
      status: "pending",
      subtotal: billableAmount,
      tax_total: billableAmount * 0.16,
      total: billableAmount * 1.16,
      amount_paid: 0,
      amount_remaining: billableAmount * 1.16,
      invoice_date: new Date().toISOString().split("T")[0],
      due_date: new Date(Date.now() + 15 * 24 * 60 * 60 * 1000).toISOString().split("T")[0],
      estimate_id: null,
      project_id: null,
      recurring_invoice_id: null,
      payment_link: `https://juanet.cloud/pay/invoice/${invoiceNum}`,
      items: [
        {
          id: `item-${Date.now()}`,
          description: `Workforce billable hours - 45 hours logged by Senior Engineers @ KES ${rate}/hr`,
          quantity: hours,
          unit_price: rate,
          tax_rate_id: "tax-16",
          subtotal: billableAmount,
          tax_amount: billableAmount * 0.16,
          total: billableAmount * 1.16
        }
      ]
    };

    setInvoices([created, ...invoices]);

    const ledgerEntry: LedgerEntry = {
      id: `TX-${Date.now()}`,
      ledgerable_type: "Invoice",
      ledgerable_id: invoiceId,
      type: "credit",
      amount: billableAmount * 1.16,
      transaction_date: created.invoice_date,
      description: `Workforce billable time invoice #${invoiceNum} generated (Airtel Kenya PLC)`,
      reference_number: invoiceNum
    };
    setLedger([ledgerEntry, ...ledger]);

    showSimulatorNotification(`🕒 Workforce Time Tracker Integrated! 45 billable hours mapped to Invoice ${invoiceNum} and logged in ledger entries.`);
  };

  const runMarketplaceIntegration = () => {
    // Marketplace purchase automatically generates Invoice, Payment, Ledger Entries
    const purchaseAmount = 35000.00; // e.g. software license purchase
    const invoiceNum = `INV-MARK-${Date.now().toString().slice(-4)}`;
    const invoiceId = invoiceNum;
    const payNum = `PAY-MARK-${Date.now()}`;
    const txRef = `MP-REF-${rand()}`;

    // 1. Create Invoice
    const createdInvoice: Invoice = {
      id: invoiceId,
      invoice_number: invoiceNum,
      client_id: null,
      client_name: "Marketplace Visitor (Mary Wambui)",
      client_email: "mary@telecom.co.ke",
      status: "paid",
      subtotal: purchaseAmount,
      tax_total: purchaseAmount * 0.16,
      total: purchaseAmount * 1.16,
      amount_paid: purchaseAmount * 1.16,
      amount_remaining: 0,
      invoice_date: new Date().toISOString().split("T")[0],
      due_date: new Date().toISOString().split("T")[0],
      estimate_id: null,
      project_id: null,
      recurring_invoice_id: null,
      payment_link: `https://juanet.cloud/pay/invoice/${invoiceNum}`,
      items: [
        {
          id: `item-${Date.now()}`,
          description: "Marketplace Plugin: Kenya M-PESA B2B Enterprise Gateway Connector",
          quantity: 1,
          unit_price: purchaseAmount,
          tax_rate_id: "tax-16",
          subtotal: purchaseAmount,
          tax_amount: purchaseAmount * 0.16,
          total: purchaseAmount * 1.16
        }
      ]
    };

    // 2. Create instant Payment
    const createdPayment: Payment = {
      id: payNum,
      invoice_id: invoiceId,
      invoice_number: invoiceNum,
      payment_method: "M-PESA",
      amount: purchaseAmount * 1.16,
      payment_date: createdInvoice.invoice_date,
      transaction_reference: txRef,
      status: "completed",
      notes: "Automatic marketplace instant checkout payment"
    };

    // 3. Post to state
    setInvoices([createdInvoice, ...invoices]);
    setPayments([createdPayment, ...payments]);

    // 4. Ledger Entries (Debit receivables then Credit immediately upon checkout)
    const ledgerEntry1: LedgerEntry = {
      id: `TX-M1-${Date.now()}`,
      ledgerable_type: "Invoice",
      ledgerable_id: invoiceId,
      type: "credit",
      amount: purchaseAmount * 1.16,
      transaction_date: createdInvoice.invoice_date,
      description: `Marketplace purchase Invoice #${invoiceNum} issued`,
      reference_number: invoiceNum
    };

    const ledgerEntry2: LedgerEntry = {
      id: `TX-M2-${Date.now()}`,
      ledgerable_type: "Payment",
      ledgerable_id: payNum,
      type: "credit",
      amount: purchaseAmount * 1.16,
      transaction_date: createdInvoice.invoice_date,
      description: `Marketplace purchase payment received via M-PESA`,
      reference_number: txRef
    };

    setLedger([ledgerEntry2, ledgerEntry1, ...ledger]);

    showSimulatorNotification(`🛍️ Marketplace Checkout Triggered! Automatic invoice, M-PESA payment, and debit-credit ledger entries posted concurrently.`);
  };

  // Helper
  const rand = () => Math.random().toString(36).substring(2, 8).toUpperCase();
  const showSimulatorNotification = (msg: string) => {
    setSimulatorMessage(msg);
    setTimeout(() => setSimulatorMessage(null), 5000);
  };

  return (
    <div className="space-y-6">
      {/* Tab Header & Quick Info */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/60 border border-slate-800/80 p-6 rounded-2xl backdrop-blur-sm">
        <div>
          <div className="flex items-center gap-2 text-indigo-400 font-mono text-xs uppercase tracking-wider mb-1">
            <Sparkles size={14} />
            Finance & Billing Context
          </div>
          <h2 className="text-2xl font-bold text-slate-100 tracking-tight">Enterprise Treasury Hub</h2>
          <p className="text-slate-400 text-sm mt-1 max-w-2xl">
            Immutable Double-Entry Ledger, automated tax compliance (Kenya VAT), multi-gateway billing integration, and automated recurring cycles.
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          <button
            onClick={() => setIsInvoiceModalOpen(true)}
            className="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-xs transition-all shadow-md shadow-indigo-600/10 cursor-pointer"
          >
            <Plus size={14} /> New Invoice
          </button>
          <button
            onClick={() => setIsExpenseModalOpen(true)}
            className="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700/80 font-medium text-xs transition-all cursor-pointer"
          >
            <Plus size={14} /> Log Expense
          </button>
        </div>
      </div>

      {/* Simulator Notification Toast */}
      {simulatorMessage && (
        <div className="bg-slate-950 border-l-4 border-indigo-500 text-indigo-300 p-4 rounded-r-xl flex items-center justify-between gap-4 shadow-xl shadow-slate-950/50 animate-bounce">
          <div className="flex items-center gap-2 text-xs font-mono">
            <Sparkles size={16} className="text-indigo-400 shrink-0" />
            <span>{simulatorMessage}</span>
          </div>
          <button onClick={() => setSimulatorMessage(null)} className="text-slate-500 hover:text-slate-200 text-xs">✕</button>
        </div>
      )}

      {/* Sub Tabs Selection */}
      <div className="flex flex-wrap gap-1 border-b border-slate-800/80 pb-px">
        {[
          { id: "dashboard", label: "Dashboard overview", icon: <TrendingUp size={14} /> },
          { id: "invoices", label: "Invoices & billing", icon: <FileText size={14} /> },
          { id: "estimates", label: "Estimates workflow", icon: <FileSpreadsheet size={14} /> },
          { id: "expenses", label: "Expense tracker", icon: <ArrowDownLeft size={14} /> },
          { id: "ledger", label: "Immutable ledger", icon: <Layers size={14} /> },
          { id: "recurring", label: "Recurring subscriptions", icon: <Repeat size={14} /> },
          { id: "integrations", label: "Cross-domain sandbox", icon: <Building size={14} /> },
          { id: "reports", label: "Financial reports", icon: <Printer size={14} /> }
        ].map(sub => (
          <button
            key={sub.id}
            onClick={() => setActiveSubTab(sub.id as any)}
            className={`flex items-center gap-2 px-4 py-3 text-xs font-medium transition-all relative ${
              activeSubTab === sub.id
                ? "text-indigo-400 border-b-2 border-indigo-500 font-semibold"
                : "text-slate-400 hover:text-slate-200"
            }`}
          >
            {sub.icon}
            {sub.label}
          </button>
        ))}
      </div>

      {/* RENDER ACTIVE TAB */}
      {activeSubTab === "dashboard" && (
        <div className="space-y-6">
          {/* Key Metrics Grid */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="bg-slate-900/40 border border-slate-800/80 p-5 rounded-xl space-y-2">
              <div className="flex items-center justify-between text-slate-500">
                <span className="text-xs uppercase tracking-wider font-mono">Total Revenue (Cash in)</span>
                <ArrowUpRight size={16} className="text-emerald-400" />
              </div>
              <h3 className="text-2xl font-bold text-slate-100 font-mono">KES {totalRevenue.toLocaleString()}</h3>
              <p className="text-[11px] text-emerald-400 flex items-center gap-1">
                <span>+12.4% vs last month</span>
              </p>
            </div>

            <div className="bg-slate-900/40 border border-slate-800/80 p-5 rounded-xl space-y-2">
              <div className="flex items-center justify-between text-slate-500">
                <span className="text-xs uppercase tracking-wider font-mono">Operating Expenses</span>
                <ArrowDownLeft size={16} className="text-rose-400" />
              </div>
              <h3 className="text-2xl font-bold text-slate-100 font-mono">KES {totalExpenses.toLocaleString()}</h3>
              <p className="text-[11px] text-rose-400 flex items-center gap-1">
                <span>+4.2% cloud scale expansion</span>
              </p>
            </div>

            <div className="bg-slate-900/40 border border-slate-800/80 p-5 rounded-xl space-y-2">
              <div className="flex items-center justify-between text-slate-500">
                <span className="text-xs uppercase tracking-wider font-mono">Net Profit Margins</span>
                <DollarSign size={16} className="text-indigo-400" />
              </div>
              <h3 className="text-2xl font-bold text-indigo-300 font-mono">KES {totalProfit.toLocaleString()}</h3>
              <p className="text-[11px] text-indigo-400 flex items-center gap-1">
                <span>Healthy 72% Margin</span>
              </p>
            </div>

            <div className="bg-slate-900/40 border border-slate-800/80 p-5 rounded-xl space-y-2">
              <div className="flex items-center justify-between text-slate-500">
                <span className="text-xs uppercase tracking-wider font-mono">Receivables / Outstanding</span>
                <Clock size={16} className="text-amber-400" />
              </div>
              <h3 className="text-2xl font-bold text-amber-300 font-mono">KES {outstandingInvoicesValue.toLocaleString()}</h3>
              <p className="text-[11px] text-amber-400 flex items-center gap-1">
                <span>Invoices pending payment</span>
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div className="bg-slate-900/20 border border-slate-800/60 p-4 rounded-xl flex items-center gap-3">
              <div className="p-3 rounded-lg bg-indigo-500/10 text-indigo-400">
                <Repeat size={16} />
              </div>
              <div>
                <div className="text-[10px] text-slate-500 uppercase tracking-wider font-mono">Contract MRR</div>
                <div className="text-lg font-bold text-slate-200 font-mono">KES {activeMRR.toLocaleString()}</div>
              </div>
            </div>

            <div className="bg-slate-900/20 border border-slate-800/60 p-4 rounded-xl flex items-center gap-3">
              <div className="p-3 rounded-lg bg-indigo-500/10 text-indigo-400">
                <TrendingUp size={16} />
              </div>
              <div>
                <div className="text-[10px] text-slate-500 uppercase tracking-wider font-mono">Annualized ARR</div>
                <div className="text-lg font-bold text-slate-200 font-mono">KES {(activeMRR * 12).toLocaleString()}</div>
              </div>
            </div>

            <div className="bg-slate-900/20 border border-slate-800/60 p-4 rounded-xl flex items-center gap-3">
              <div className="p-3 rounded-lg bg-indigo-500/10 text-indigo-400">
                <Percent size={16} />
              </div>
              <div>
                <div className="text-[10px] text-slate-500 uppercase tracking-wider font-mono">Withholding & VAT (16%)</div>
                <div className="text-lg font-bold text-slate-200 font-mono">KES {totalTaxes.toLocaleString()}</div>
              </div>
            </div>
          </div>

          {/* Chart Section */}
          <div className="bg-slate-900/40 border border-slate-800/80 p-6 rounded-2xl space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="font-bold text-slate-200 text-sm">Treasury Cash Flow Timeline</h3>
                <p className="text-xs text-slate-500">Immutable credits (revenue) vs debits (expenses)</p>
              </div>
              <div className="flex items-center gap-4 text-xs font-mono">
                <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 bg-emerald-500 rounded-sm inline-block"></span> Credits</span>
                <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 bg-rose-500 rounded-sm inline-block"></span> Debits</span>
              </div>
            </div>

            {/* Premium custom SVG chart representing actual data */}
            <div className="h-64 w-full flex items-end justify-between gap-2 pt-6">
              {[
                { label: "Jul 01", credit: 174000, debit: 14500 },
                { label: "Jul 02", credit: 150000, debit: 0 },
                { label: "Jul 03", credit: 0, debit: 4200 },
                { label: "Jul 04", credit: 0, debit: 0 },
                { label: "Jul 05", credit: 522000, debit: 120000 },
                { label: "Jul 06", credit: 200000, debit: 0 },
                { label: "Jul 07", credit: 0, debit: 0 },
                { label: "Jul 08", credit: 92800, creditPending: true, debit: 0 }
              ].map((bar, idx) => {
                const max = 600000;
                const creditHeight = `${(bar.credit / max) * 100}%`;
                const debitHeight = `${(bar.debit / max) * 100}%`;

                return (
                  <div key={idx} className="flex-1 flex flex-col items-center justify-end h-full group">
                    <div className="w-full flex justify-center items-end gap-1 h-full pb-2">
                      {/* Credit Bar */}
                      {bar.credit > 0 && (
                        <div
                          style={{ height: creditHeight }}
                          className={`w-3.5 sm:w-5 rounded-t-sm transition-all duration-300 relative ${
                            bar.creditPending ? "bg-emerald-500/40 border-t border-emerald-400 border-dashed" : "bg-emerald-500 group-hover:bg-emerald-400"
                          }`}
                        >
                          <div className="opacity-0 group-hover:opacity-100 absolute bottom-full left-1/2 -translate-x-1/2 mb-1 bg-slate-950 border border-slate-800 text-[10px] font-mono text-slate-200 px-2 py-1 rounded shadow-xl whitespace-nowrap z-30">
                            Credit: KES {bar.credit.toLocaleString()}
                          </div>
                        </div>
                      )}

                      {/* Debit Bar */}
                      {bar.debit > 0 && (
                        <div
                          style={{ height: debitHeight }}
                          className="w-3.5 sm:w-5 bg-rose-500 group-hover:bg-rose-400 rounded-t-sm transition-all duration-300 relative"
                        >
                          <div className="opacity-0 group-hover:opacity-100 absolute bottom-full left-1/2 -translate-x-1/2 mb-1 bg-slate-950 border border-slate-800 text-[10px] font-mono text-rose-400 px-2 py-1 rounded shadow-xl whitespace-nowrap z-30">
                            Debit: KES {bar.debit.toLocaleString()}
                          </div>
                        </div>
                      )}
                    </div>
                    <span className="text-[10px] text-slate-500 font-mono mt-2">{bar.label}</span>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      )}

      {activeSubTab === "invoices" && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row justify-between gap-3">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-500" />
              <input
                type="text"
                placeholder="Filter by invoice #, client name..."
                className="w-full pl-9 pr-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
              />
            </div>
            <div className="flex gap-2">
              <button className="px-3 py-2 bg-slate-900 border border-slate-800 text-slate-300 rounded-lg text-xs flex items-center gap-1.5">
                <Filter size={12} /> Status
              </button>
            </div>
          </div>

          <div className="bg-slate-900/30 border border-slate-800/80 rounded-xl overflow-x-auto">
            <table className="w-full text-left border-collapse min-w-[700px]">
              <thead>
                <tr className="border-b border-slate-800/60 bg-slate-900/50 text-[11px] uppercase tracking-wider font-mono text-slate-500">
                  <th className="py-3 px-4">Invoice #</th>
                  <th className="py-3 px-4">Client</th>
                  <th className="py-3 px-4">Invoice Date</th>
                  <th className="py-3 px-4 text-right">Total Amount</th>
                  <th className="py-3 px-4 text-right">Balance Due</th>
                  <th className="py-3 px-4 text-center">Status</th>
                  <th className="py-3 px-4 text-center">Quick Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/40 text-xs text-slate-300">
                {invoices.map(inv => (
                  <tr key={inv.id} className="hover:bg-slate-900/30 transition-all">
                    <td className="py-3.5 px-4 font-mono font-semibold text-slate-200">{inv.invoice_number}</td>
                    <td className="py-3.5 px-4">
                      <div className="font-medium text-slate-200">{inv.client_name}</div>
                      <div className="text-[10px] text-slate-500">{inv.client_email}</div>
                    </td>
                    <td className="py-3.5 px-4 font-mono text-slate-400">{inv.invoice_date}</td>
                    <td className="py-3.5 px-4 text-right font-mono text-slate-200">KES {inv.total.toLocaleString()}</td>
                    <td className="py-3.5 px-4 text-right font-mono text-slate-400">KES {inv.amount_remaining.toLocaleString()}</td>
                    <td className="py-3.5 px-4 text-center">
                      <span className={`px-2.5 py-1 rounded-full text-[10px] uppercase font-mono font-medium tracking-wide ${
                        inv.status === "paid" ? "bg-emerald-950 text-emerald-400 border border-emerald-900/50" :
                        inv.status === "partially paid" ? "bg-indigo-950 text-indigo-400 border border-indigo-900/50" :
                        inv.status === "overdue" ? "bg-rose-950 text-rose-400 border border-rose-900/50" :
                        "bg-slate-950 text-slate-400 border border-slate-800"
                      }`}>
                        {inv.status}
                      </span>
                    </td>
                    <td className="py-3.5 px-4 text-center">
                      <div className="flex justify-center gap-1.5">
                        {inv.amount_remaining > 0 && (
                          <button
                            onClick={() => payInvoice(inv.id, "M-PESA")}
                            className="px-2 py-1 bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-400 border border-indigo-500/20 rounded font-medium text-[10px] transition-all cursor-pointer"
                          >
                            Pay M-PESA
                          </button>
                        )}
                        <a
                          href={inv.payment_link}
                          target="_blank"
                          rel="noreferrer"
                          className="p-1 text-slate-400 hover:text-slate-200 bg-slate-800 rounded border border-slate-700/60"
                          title="Open Payment Link"
                        >
                          <CreditCard size={12} />
                        </a>
                        <button
                          className="p-1 text-slate-400 hover:text-slate-200 bg-slate-800 rounded border border-slate-700/60"
                          title="Download PDF"
                          onClick={() => showSimulatorNotification(`📥 Generating secure cryptographic invoice PDF for ${inv.invoice_number}...`)}
                        >
                          <Download size={12} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {activeSubTab === "estimates" && (
        <div className="space-y-4">
          <div className="bg-slate-900/30 border border-slate-800/80 rounded-xl overflow-x-auto">
            <table className="w-full text-left border-collapse min-w-[700px]">
              <thead>
                <tr className="border-b border-slate-800/60 bg-slate-900/50 text-[11px] uppercase tracking-wider font-mono text-slate-500">
                  <th className="py-3 px-4">Estimate #</th>
                  <th className="py-3 px-4">Client</th>
                  <th className="py-3 px-4">Date Issued</th>
                  <th className="py-3 px-4 text-right">Subtotal</th>
                  <th className="py-3 px-4 text-right">Total (Inc Tax)</th>
                  <th className="py-3 px-4 text-center">Approval Status</th>
                  <th className="py-3 px-4 text-center">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/40 text-xs text-slate-300">
                {estimates.map(est => (
                  <tr key={est.id} className="hover:bg-slate-900/30 transition-all">
                    <td className="py-3.5 px-4 font-mono font-semibold text-slate-200">{est.estimate_number}</td>
                    <td className="py-3.5 px-4">
                      <div className="font-medium text-slate-200">{est.client_name}</div>
                      <div className="text-[10px] text-slate-500">{est.client_email}</div>
                    </td>
                    <td className="py-3.5 px-4 font-mono text-slate-400">{est.estimate_date}</td>
                    <td className="py-3.5 px-4 text-right font-mono text-slate-200">KES {est.subtotal.toLocaleString()}</td>
                    <td className="py-3.5 px-4 text-right font-mono text-indigo-300 font-semibold">KES {est.total.toLocaleString()}</td>
                    <td className="py-3.5 px-4 text-center">
                      <span className={`px-2.5 py-1 rounded-full text-[10px] uppercase font-mono font-medium tracking-wide ${
                        est.status === "approved" ? "bg-emerald-950 text-emerald-400 border border-emerald-900/50" :
                        est.status === "pending" ? "bg-amber-950 text-amber-400 border border-amber-900/50" :
                        "bg-slate-950 text-slate-400 border border-slate-800"
                      }`}>
                        {est.status}
                      </span>
                    </td>
                    <td className="py-3.5 px-4 text-center">
                      {est.status === "pending" ? (
                        <button
                          onClick={() => convertEstimate(est.id)}
                          className="px-2.5 py-1 bg-emerald-600/15 hover:bg-emerald-600/25 text-emerald-400 border border-emerald-500/25 rounded font-semibold text-[10px] transition-all cursor-pointer"
                        >
                          Approve & Convert
                        </button>
                      ) : (
                        <span className="text-[10px] text-slate-500 font-mono">Completed</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {activeSubTab === "expenses" && (
        <div className="space-y-4">
          <div className="bg-slate-900/30 border border-slate-800/80 rounded-xl overflow-x-auto">
            <table className="w-full text-left border-collapse min-w-[700px]">
              <thead>
                <tr className="border-b border-slate-800/60 bg-slate-900/50 text-[11px] uppercase tracking-wider font-mono text-slate-500">
                  <th className="py-3 px-4">Merchant</th>
                  <th className="py-3 px-4">Category</th>
                  <th className="py-3 px-4">Date</th>
                  <th className="py-3 px-4">Description</th>
                  <th className="py-3 px-4">Ref Number</th>
                  <th className="py-3 px-4 text-right">Amount</th>
                  <th className="py-3 px-4 text-center">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/40 text-xs text-slate-300">
                {expenses.map(exp => (
                  <tr key={exp.id} className="hover:bg-slate-900/30 transition-all">
                    <td className="py-3.5 px-4 font-semibold text-slate-200">{exp.merchant}</td>
                    <td className="py-3.5 px-4">
                      <span className="px-2 py-0.5 rounded bg-slate-800 text-slate-300 text-[10px] font-mono border border-slate-700/60">
                        {exp.category}
                      </span>
                    </td>
                    <td className="py-3.5 px-4 font-mono text-slate-400">{exp.expense_date}</td>
                    <td className="py-3.5 px-4 text-slate-400 max-w-[200px] truncate">{exp.description}</td>
                    <td className="py-3.5 px-4 font-mono text-slate-500">{exp.reference_number}</td>
                    <td className="py-3.5 px-4 text-right font-mono text-rose-300">KES {exp.amount.toLocaleString()}</td>
                    <td className="py-3.5 px-4 text-center">
                      <span className="px-2 py-0.5 rounded-full bg-emerald-950 text-emerald-400 border border-emerald-900/30 text-[9px] uppercase font-mono">
                        {exp.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {activeSubTab === "ledger" && (
        <div className="space-y-4">
          <div className="bg-slate-950 border border-slate-800/80 p-4 rounded-xl text-xs text-slate-400 leading-relaxed font-mono">
            ⚠️ <span className="text-indigo-400 font-bold">MUTABILITY PROTOCOL RULE</span>: This ledger represents the central, unalterable double-entry log of JUANET. In accordance with hexagonal specifications, every invoice issuance, M-PESA webhook clearance, or operating expense posting is permanently recorded here.
          </div>

          <div className="bg-slate-900/30 border border-slate-800/80 rounded-xl overflow-x-auto">
            <table className="w-full text-left border-collapse min-w-[700px]">
              <thead>
                <tr className="border-b border-slate-800/60 bg-slate-900/50 text-[11px] uppercase tracking-wider font-mono text-slate-500">
                  <th className="py-3 px-4">Transaction ID</th>
                  <th className="py-3 px-4">Post Date</th>
                  <th className="py-3 px-4">Ledger Category</th>
                  <th className="py-3 px-4">Description</th>
                  <th className="py-3 px-4">Reference</th>
                  <th className="py-3 px-4 text-right">Debit (Dr)</th>
                  <th className="py-3 px-4 text-right">Credit (Cr)</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/40 text-xs text-slate-300">
                {ledger.map(tx => (
                  <tr key={tx.id} className="hover:bg-slate-900/30 transition-all font-mono">
                    <td className="py-3.5 px-4 font-semibold text-slate-400">{tx.id}</td>
                    <td className="py-3.5 px-4 text-slate-400">{tx.transaction_date}</td>
                    <td className="py-3.5 px-4 text-[10px] text-slate-500 uppercase">{tx.ledgerable_type}</td>
                    <td className="py-3.5 px-4 text-slate-200">{tx.description}</td>
                    <td className="py-3.5 px-4 text-slate-500">{tx.reference_number || "-"}</td>
                    <td className="py-3.5 px-4 text-right text-rose-400">
                      {tx.type === "debit" ? `KES ${tx.amount.toLocaleString()}` : "-"}
                    </td>
                    <td className="py-3.5 px-4 text-right text-emerald-400">
                      {tx.type === "credit" ? `KES ${tx.amount.toLocaleString()}` : "-"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {activeSubTab === "recurring" && (
        <div className="space-y-4">
          <div className="flex justify-between items-center bg-slate-900/30 border border-slate-800/60 p-4 rounded-xl">
            <div>
              <h3 className="text-sm font-bold text-slate-200">Recurring Billing Sandbox</h3>
              <p className="text-xs text-slate-500">Simulate weekly, monthly, quarterly, and yearly automated generation cron jobs.</p>
            </div>
            <button
              onClick={triggerRecurringSimulation}
              className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold flex items-center gap-2 cursor-pointer"
            >
              <Repeat size={14} /> Run Billing Job Now
            </button>
          </div>

          <div className="bg-slate-900/30 border border-slate-800/80 rounded-xl overflow-x-auto">
            <table className="w-full text-left border-collapse min-w-[700px]">
              <thead>
                <tr className="border-b border-slate-800/60 bg-slate-900/50 text-[11px] uppercase tracking-wider font-mono text-slate-500">
                  <th className="py-3 px-4">Template ID</th>
                  <th className="py-3 px-4">Client Name</th>
                  <th className="py-3 px-4">Billing frequency</th>
                  <th className="py-3 px-4">Start Date</th>
                  <th className="py-3 px-4">Last Generated</th>
                  <th className="py-3 px-4 text-right">Template Value</th>
                  <th className="py-3 px-4 text-center">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/40 text-xs text-slate-300">
                {recurringInvoices.map(rec => (
                  <tr key={rec.id} className="hover:bg-slate-900/30 transition-all">
                    <td className="py-3.5 px-4 font-mono font-semibold text-slate-400">{rec.id}</td>
                    <td className="py-3.5 px-4">
                      <div className="font-semibold text-slate-200">{rec.client_name}</div>
                      <div className="text-[10px] text-slate-500">{rec.client_email}</div>
                    </td>
                    <td className="py-3.5 px-4 uppercase font-mono text-indigo-400">{rec.billing_cycle}</td>
                    <td className="py-3.5 px-4 font-mono text-slate-400">{rec.start_date}</td>
                    <td className="py-3.5 px-4 font-mono text-slate-500">{rec.last_generated_at || "Never"}</td>
                    <td className="py-3.5 px-4 text-right font-mono text-slate-200">KES {rec.template_data.total.toLocaleString()}</td>
                    <td className="py-3.5 px-4 text-center">
                      <span className="px-2 py-0.5 rounded-full bg-emerald-950 text-emerald-400 border border-emerald-900/40 text-[9px] uppercase font-mono">
                        {rec.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {activeSubTab === "integrations" && (
        <div className="space-y-6">
          <div className="bg-slate-950 border border-slate-800 p-5 rounded-2xl">
            <h3 className="font-bold text-slate-200 text-sm mb-2">Platform Domain Integrations Sandbox</h3>
            <p className="text-xs text-slate-500 mb-6">
              Simulate cross-boundary transactions. The DDD hexagonal architecture allows secondary domains to trigger finance events seamlessly.
            </p>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="bg-slate-900/50 border border-slate-800 p-5 rounded-xl space-y-4">
                <div className="flex items-center gap-2 text-indigo-400">
                  <Briefcase size={18} />
                  <span className="font-semibold text-xs uppercase tracking-wider font-mono">Project Milestones</span>
                </div>
                <p className="text-xs text-slate-400">
                  When a project milestone is flagged completed, automatic triggers query active pricing rules and draft contract schedules to issue billable invoices.
                </p>
                <button
                  onClick={runMilestoneIntegration}
                  className="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-medium text-xs rounded transition-all cursor-pointer"
                >
                  Generate Invoice from Milestone
                </button>
              </div>

              <div className="bg-slate-900/50 border border-slate-800 p-5 rounded-xl space-y-4">
                <div className="flex items-center gap-2 text-indigo-400">
                  <Clock size={18} />
                  <span className="font-semibold text-xs uppercase tracking-wider font-mono">Workforce Time Tracker</span>
                </div>
                <p className="text-xs text-slate-400">
                  Convert logged, billable engineering times from the Workforce Tracker module directly into line items on customer client invoices.
                </p>
                <button
                  onClick={runWorkforceIntegration}
                  className="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-medium text-xs rounded transition-all cursor-pointer"
                >
                  Bill Hours logged on Workforce
                </button>
              </div>

              <div className="bg-slate-900/50 border border-slate-800 p-5 rounded-xl space-y-4">
                <div className="flex items-center gap-2 text-indigo-400">
                  <ShoppingCart size={18} />
                  <span className="font-semibold text-xs uppercase tracking-wider font-mono">Marketplace Checkout</span>
                </div>
                <p className="text-xs text-slate-400">
                  An unauthenticated visitor checks out on the marketplace. Instantly posts an invoice, matches payment callback, and enters credits in the Ledger.
                </p>
                <button
                  onClick={runMarketplaceIntegration}
                  className="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-medium text-xs rounded transition-all cursor-pointer"
                >
                  Simulate Purchase Checkout
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {activeSubTab === "reports" && (
        <div className="bg-slate-900/30 border border-slate-800 p-6 rounded-2xl space-y-6">
          <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
              <h3 className="font-bold text-slate-200 text-sm">JUANET Treasury & Financial Reporting</h3>
              <p className="text-xs text-slate-500">Live generated reports with full Kenyan fiscal and company audit compliance.</p>
            </div>
            <button
              onClick={() => showSimulatorNotification("🖨️ Printing report in compliance with company audit guidelines...")}
              className="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 rounded-lg text-xs font-semibold flex items-center gap-2 cursor-pointer"
            >
              <Printer size={12} /> Print Statement
            </button>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="bg-slate-950 border border-slate-800 p-5 rounded-xl space-y-4">
              <h4 className="text-xs font-mono uppercase tracking-wider text-indigo-400 font-bold border-b border-slate-800 pb-2">
                Profit & Loss Statement
              </h4>
              <div className="space-y-3 font-mono text-xs">
                <div className="flex justify-between">
                  <span className="text-slate-400">Operating Revenue (Payments)</span>
                  <span className="text-emerald-400">KES {totalRevenue.toLocaleString()}</span>
                </div>
                <div className="flex justify-between border-b border-slate-800 pb-1.5">
                  <span className="text-slate-400">Less Cost of Services (Expenses)</span>
                  <span className="text-rose-400">KES {totalExpenses.toLocaleString()}</span>
                </div>
                <div className="flex justify-between font-bold text-sm pt-1">
                  <span className="text-slate-200">NET OPERATING PROFIT</span>
                  <span className="text-indigo-300">KES {totalProfit.toLocaleString()}</span>
                </div>
              </div>
            </div>

            <div className="bg-slate-950 border border-slate-800 p-5 rounded-xl space-y-4">
              <h4 className="text-xs font-mono uppercase tracking-wider text-indigo-400 font-bold border-b border-slate-800 pb-2">
                Accounts Receivable & Taxes
              </h4>
              <div className="space-y-3 font-mono text-xs">
                <div className="flex justify-between">
                  <span className="text-slate-400">Total Receivables Value</span>
                  <span className="text-slate-200">KES {outstandingInvoicesValue.toLocaleString()}</span>
                </div>
                <div className="flex justify-between border-b border-slate-800 pb-1.5">
                  <span className="text-slate-400">Kenya VAT Tax Liability (16%)</span>
                  <span className="text-slate-200">KES {totalTaxes.toLocaleString()}</span>
                </div>
                <div className="flex justify-between font-bold text-sm pt-1">
                  <span className="text-slate-200">TOTAL CAPITAL EXPOSURE</span>
                  <span className="text-amber-300">KES {outstandingInvoicesValue.toLocaleString()}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Invoice Creation Modal */}
      {isInvoiceModalOpen && (
        <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-md flex items-center justify-center z-50 p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
            <div className="p-6 border-b border-slate-800 flex justify-between items-center">
              <h3 className="font-bold text-slate-100 text-sm">Create New Custom Invoice</h3>
              <button onClick={() => setIsInvoiceModalOpen(false)} className="text-slate-500 hover:text-slate-200">✕</button>
            </div>
            <form onSubmit={handleCreateInvoice} className="p-6 space-y-4 text-xs">
              <div className="space-y-1">
                <label className="text-slate-400 block font-medium">Client Name</label>
                <input
                  required
                  type="text"
                  placeholder="e.g. Safaricom PLC"
                  value={newInvoice.client_name}
                  onChange={e => setNewInvoice({ ...newInvoice, client_name: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
              </div>

              <div className="space-y-1">
                <label className="text-slate-400 block font-medium">Client Email</label>
                <input
                  required
                  type="email"
                  placeholder="e.g. finance@client.co.ke"
                  value={newInvoice.client_email}
                  onChange={e => setNewInvoice({ ...newInvoice, client_email: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-slate-400 block font-medium">Invoice Date</label>
                  <input
                    type="date"
                    value={newInvoice.invoice_date}
                    onChange={e => setNewInvoice({ ...newInvoice, invoice_date: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-slate-400 block font-medium">Due Date</label>
                  <input
                    type="date"
                    value={newInvoice.due_date}
                    onChange={e => setNewInvoice({ ...newInvoice, due_date: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-slate-400 block font-medium">Line Item Description</label>
                <input
                  required
                  type="text"
                  placeholder="Scoping and API documentation development"
                  value={newInvoice.description}
                  onChange={e => setNewInvoice({ ...newInvoice, description: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
              </div>

              <div className="grid grid-cols-3 gap-4">
                <div className="space-y-1 col-span-2">
                  <label className="text-slate-400 block font-medium">Unit Price (KES)</label>
                  <input
                    required
                    type="number"
                    placeholder="85000"
                    value={newInvoice.unit_price || ""}
                    onChange={e => setNewInvoice({ ...newInvoice, unit_price: Number(e.target.value) })}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-slate-400 block font-medium">Quantity</label>
                  <input
                    required
                    type="number"
                    value={newInvoice.quantity}
                    onChange={e => setNewInvoice({ ...newInvoice, quantity: Number(e.target.value) })}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  />
                </div>
              </div>

              <div className="pt-4 flex justify-end gap-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsInvoiceModalOpen(false)}
                  className="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700 cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 cursor-pointer"
                >
                  Create Invoice
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Expense Logging Modal */}
      {isExpenseModalOpen && (
        <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-md flex items-center justify-center z-50 p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
            <div className="p-6 border-b border-slate-800 flex justify-between items-center">
              <h3 className="font-bold text-slate-100 text-sm">Log Operating Expense</h3>
              <button onClick={() => setIsExpenseModalOpen(false)} className="text-slate-500 hover:text-slate-200">✕</button>
            </div>
            <form onSubmit={handleCreateExpense} className="p-6 space-y-4 text-xs">
              <div className="space-y-1">
                <label className="text-slate-400 block font-medium">Expense Category</label>
                <select
                  value={newExpense.category}
                  onChange={e => setNewExpense({ ...newExpense, category: e.target.value as any })}
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                >
                  {["Software", "Hosting", "Domains", "Marketing", "Payroll", "Equipment", "Travel", "Miscellaneous"].map(cat => (
                    <option key={cat} value={cat}>{cat}</option>
                  ))}
                </select>
              </div>

              <div className="space-y-1">
                <label className="text-slate-400 block font-medium">Merchant / Vendor</label>
                <input
                  required
                  type="text"
                  placeholder="e.g. Amazon Web Services"
                  value={newExpense.merchant}
                  onChange={e => setNewExpense({ ...newExpense, merchant: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
              </div>

              <div className="space-y-1">
                <label className="text-slate-400 block font-medium">Total Amount (KES)</label>
                <input
                  required
                  type="number"
                  placeholder="e.g. 14500"
                  value={newExpense.amount || ""}
                  onChange={e => setNewExpense({ ...newExpense, amount: Number(e.target.value) })}
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
              </div>

              <div className="space-y-1">
                <label className="text-slate-400 block font-medium">Reference Number</label>
                <input
                  type="text"
                  placeholder="e.g. AWS-INV-99201"
                  value={newExpense.reference_number}
                  onChange={e => setNewExpense({ ...newExpense, reference_number: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
              </div>

              <div className="space-y-1">
                <label className="text-slate-400 block font-medium">Brief Description</label>
                <textarea
                  placeholder="AWS Cloud computing infrastructure resources billing"
                  value={newExpense.description}
                  onChange={e => setNewExpense({ ...newExpense, description: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-200 placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-indigo-500 h-16"
                />
              </div>

              <div className="pt-4 flex justify-end gap-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsExpenseModalOpen(false)}
                  className="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700 cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 cursor-pointer"
                >
                  Save Expense
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
