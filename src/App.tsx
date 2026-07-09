import React, { useState, useEffect, useRef } from "react";
import { motion, AnimatePresence } from "motion/react";
import {
  Globe,
  Layers,
  Code,
  Laptop,
  Cpu,
  Palette,
  Share2,
  ShieldCheck,
  HelpCircle,
  LayoutGrid,
  Lightbulb,
  Wifi,
  Server,
  Users,
  ShoppingCart,
  Terminal,
  Send,
  Database,
  Lock,
  Activity,
  CreditCard,
  ArrowRight,
  CheckCircle2,
  Folder,
  FolderOpen,
  FileCode,
  ExternalLink,
  Copy,
  Check,
  Settings,
  AlertCircle,
  Info,
  RefreshCw,
  Play,
  GitBranch,
  Sparkles,
  Clock,
  Bot,
  ChevronRight,
  ChevronDown,
  MessageSquare,
  BookOpen,
  Eye,
  Key,
  Plus,
  Trash2,
  ShieldAlert,
  CheckSquare,
  Bell,
  Mail,
  Download,
  Tag,
  FileText,
  FileSpreadsheet,
  UserCheck,
  Phone,
  Video,
  Calendar,
  Filter,
  Search,
  Paperclip,
  History,
  MessageCircle,
  X
} from "lucide-react";

import {
  DB_SCHEMAS,
  API_ENDPOINTS,
  SERVICE_ITEMS,
  MONOREPO_STRUCTURE,
  TableSchema,
  ApiEndpoint
} from "./data/architectureData";

import WorkforceTab from "./components/WorkforceTab";
import FinanceTab from "./components/FinanceTab";

export default function App() {
  const [activeTab, setActiveTab] = useState<string>("dashboard");
  const [copiedText, setCopiedText] = useState<string | null>(null);

  // Global State for Simulations
  const [projectRequests, setProjectRequests] = useState<any[]>([
    {
      id: "REQ-001",
      title: "Corporate SaaS Portal & Multi-tenant DB",
      description: "Require secure tenant partitioning, RBAC roles, and integrated billing flow.",
      budget: "KES 650,000",
      status: "pending",
      created_at: "Just now"
    }
  ]);
  const [contactSubmissions, setContactSubmissions] = useState<any[]>([
    {
      id: "CON-001",
      fullName: "Mary Kamau",
      email: "mary@telecom.co.ke",
      subject: "WiFi Office Survey Request",
      message: "Need commercial cabling for a 3-floor office complex.",
      status: "unread",
      created_at: "10 mins ago"
    }
  ]);
  const [comments, setComments] = useState<any[]>([
    { id: "C-1", postId: "post-1", author: "Caleb Kirui", text: "MPESA Daraja's asynchronous callback model is so much more resilient than simple polling. Great writeup!", date: "2 hrs ago" }
  ]);
  const [blogPosts, setBlogPosts] = useState<any[]>([
    {
      id: "post-1",
      title: "Building Resilient Financial Audits with MPESA Daraja API & Postgres Ledger",
      slug: "mpesa-daraja-api-audit",
      excerpt: "Explore deep integration strategies for Safaricom Paybill callbacks, validating request structures, and maintaining transaction isolation.",
      category: "Cloud Engineering",
      author: "Juan",
      date: "June 25, 2026",
      content: `When implementing asynchronous transaction checkouts (Lipa Na M-PESA online), your API router must receive callbacks at \`/api/payments/mpesa-callback\` which Safaricom triggers as POST requests. The primary danger of transactions is double-spending or fake signature injections.

By enforcing composite key constraints (\`CheckoutRequestID\`) inside the payments ledger table and verifying incoming payload checksums, the platform completely immunizes our accounting columns from external manipulations.`,
      status: "published",
      metaDescription: "Learn how to build secure audits with Safaricom Daraja MPESA API callback routes and PostgreSQL databases.",
      targetKeyword: "MPESA Daraja API"
    },
    {
      id: "post-2",
      title: "Optimizing PostgreSQL Multi-Tenant Database Architecture",
      slug: "optimizing-postgres-multi-tenant-db",
      excerpt: "Master tenant partitioning, Row-Level Security policies, and performance tuning for high-throughput SaaS applications.",
      category: "Database Architecture",
      author: "Mary Kamau",
      date: "June 28, 2026",
      content: `Database isolation is the foundation of any secure SaaS multi-tenant system. In this article, we dive into comparing physical schema separation with single-database Row-Level Security (RLS) configurations.

We show how the application of clean RLS policies tied to JWT auth claims simplifies development and guarantees robust tenant-level isolation without database configuration overhead.`,
      status: "published",
      metaDescription: "Step-by-step guide to tenant partitioning, RLS, and tuning in PostgreSQL databases for SaaS.",
      targetKeyword: "PostgreSQL Multi-Tenant"
    }
  ]);
  const [projectFiles, setProjectFiles] = useState<any[]>([
    { id: "F-1", name: "system_architecture_spec.pdf", size: "2.4 MB", type: "application/pdf", date: "June 24, 2026" },
    { id: "F-2", name: "database_schema_v2.sql", size: "450 KB", type: "application/sql", date: "June 25, 2026" }
  ]);
  const [projectUpdates, setProjectUpdates] = useState<any[]>([
    { id: "U-1", text: "Successfully provisioned PostgreSQL schema and applied auth row-level policies.", date: "9:00 AM Today" },
    { id: "U-2", text: "Configured Safaricom Daraja Sandbox credentials and tested successful callback loops.", date: "Yesterday" }
  ]);

  // Selected state for invoice payment simulation
  const [prefilledPhone, setPrefilledPhone] = useState<string>("");
  const [prefilledAmount, setPrefilledAmount] = useState<string>("");
  const [prefilledInvoiceId, setPrefilledInvoiceId] = useState<string>("");

  const handleCopy = (text: string, label: string) => {
    navigator.clipboard.writeText(text);
    setCopiedText(label);
    setTimeout(() => setCopiedText(null), 2000);
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-indigo-500 selection:text-white">
      {/* Top Professional Header */}
      <header className="border-b border-slate-800 bg-slate-900/80 backdrop-blur-md sticky top-0 z-50 px-6 py-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div className="flex items-center gap-3">
          <div className="bg-indigo-600 text-white p-2.5 rounded-lg font-display font-extrabold text-xl tracking-wider shadow-lg shadow-indigo-600/20">
            JN
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-display font-bold tracking-tight text-white">JUANET</h1>
              <span className="bg-indigo-500/10 text-indigo-400 text-[10px] font-mono px-2.5 py-0.5 rounded-full border border-indigo-500/20 flex items-center gap-1">
                <span className="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse" />
                ENTERPRISE SYSTEM SPEC
              </span>
            </div>
            <p className="text-xs text-slate-400">Project Management & Client Services Agency Platform</p>
          </div>
        </div>

        <div className="flex items-center gap-3 text-xs font-mono bg-slate-950 px-3 py-2 rounded-lg border border-slate-800">
          <span className="text-slate-500">SYSTEM ARCHITECT VER:</span>
          <span className="text-indigo-400 font-semibold">v4.0.0-PRO</span>
        </div>
      </header>

      <div className="flex flex-col lg:flex-row min-h-[calc(100vh-73px)]">
        {/* Navigation Sidebar */}
        <aside className="w-full lg:w-64 border-r border-slate-800 bg-slate-900/40 p-4 space-y-2 lg:block shrink-0">
          <div className="text-[10px] font-mono text-slate-500 uppercase tracking-wider px-3 mb-2 font-bold">
            Core Modules & Playgrounds
          </div>
          <nav className="space-y-1">
            <SidebarButton active={activeTab === "dashboard"} icon={<LayoutGrid size={16} />} label="Overview & Marketplace" onClick={() => setActiveTab("dashboard")} />
            <SidebarButton active={activeTab === "system"} icon={<Layers size={16} />} label="System Architecture" onClick={() => setActiveTab("system")} />
            <SidebarButton active={activeTab === "database"} icon={<Database size={16} />} label="DB Schema & ERD (34)" onClick={() => setActiveTab("database")} />
            <SidebarButton active={activeTab === "api"} icon={<Code size={16} />} label="Express API Router" onClick={() => setActiveTab("api")} />
            <SidebarButton active={activeTab === "auth"} icon={<Lock size={16} />} label="RBAC Staff Security" onClick={() => setActiveTab("auth")} />
            <SidebarButton active={activeTab === "crm"} icon={<Activity size={16} />} label="CRM Activities & Timeline" onClick={() => setActiveTab("crm")} />
            <SidebarButton active={activeTab === "workforce"} icon={<Users size={16} />} label="Workforce & Collaboration" onClick={() => setActiveTab("workforce")} />
            <SidebarButton active={activeTab === "messaging"} icon={<MessageSquare size={16} />} label="Messaging & File Vault" onClick={() => setActiveTab("messaging")} />
            <SidebarButton active={activeTab === "payments"} icon={<CreditCard size={16} />} label="Enterprise Payments Hub" onClick={() => setActiveTab("payments")} />
            <SidebarButton active={activeTab === "finance"} icon={<FileSpreadsheet size={16} />} label="Enterprise Finance Core" onClick={() => setActiveTab("finance")} />
            <SidebarButton active={activeTab === "blog"} icon={<BookOpen size={16} />} label="SEO Blog CMS" onClick={() => setActiveTab("blog")} />
            <SidebarButton active={activeTab === "deployment"} icon={<Settings size={16} />} label="Admin Integrations" onClick={() => setActiveTab("deployment")} />
            <SidebarButton active={activeTab === "copilot"} icon={<Bot size={16} />} label="SaaS Architect Co-Pilot" onClick={() => setActiveTab("copilot")} />
            <SidebarButton active={activeTab === "specs"} icon={<FileText size={16} />} label="SaaS Specs Explorer" onClick={() => setActiveTab("specs")} />
          </nav>

          <div className="pt-6 border-t border-slate-800/60 mt-6 text-xs text-slate-500 px-3">
            <div className="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mb-1">
              <Sparkles size={12} className="text-indigo-400" />
              Unified Multi-Gateway Hub
            </div>
            Agnostic routing across Safaricom M-PESA, PayHero, Pesapal, Stripe, and PayPal.
          </div>
        </aside>

        {/* Main Content Workspace */}
        <main className="flex-1 p-6 lg:p-8 overflow-x-hidden">
          <AnimatePresence mode="wait">
            <motion.div
              key={activeTab}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              transition={{ duration: 0.2 }}
              className="space-y-8"
            >
              {activeTab === "dashboard" && (
                <OverviewTab
                  copiedText={copiedText}
                  handleCopy={handleCopy}
                  projectRequests={projectRequests}
                  setProjectRequests={setProjectRequests}
                  contactSubmissions={contactSubmissions}
                  setContactSubmissions={setContactSubmissions}
                  triggerPayment={(phone, amt, inv) => {
                    setPrefilledPhone(phone);
                    setPrefilledAmount(amt);
                    setPrefilledInvoiceId(inv);
                    setActiveTab("payments");
                  }}
                />
              )}
              {activeTab === "system" && <SystemArchTab />}
              {activeTab === "database" && <DatabaseTab copiedText={copiedText} handleCopy={handleCopy} />}
              {activeTab === "api" && <ApiTab copiedText={copiedText} handleCopy={handleCopy} />}
              {activeTab === "auth" && <AuthTab />}
              {activeTab === "crm" && <CrmActivitiesTab />}
              {activeTab === "workforce" && <WorkforceTab />}
              {activeTab === "messaging" && (
                <MessagingTab
                  projectFiles={projectFiles}
                  setProjectFiles={setProjectFiles}
                  projectUpdates={projectUpdates}
                  setProjectUpdates={setProjectUpdates}
                />
              )}
              {activeTab === "payments" && (
                <PaymentsTab
                  copiedText={copiedText}
                  handleCopy={handleCopy}
                  prefilledPhone={prefilledPhone}
                  prefilledAmount={prefilledAmount}
                  prefilledInvoiceId={prefilledInvoiceId}
                  clearPrefills={() => {
                    setPrefilledPhone("");
                    setPrefilledAmount("");
                    setPrefilledInvoiceId("");
                  }}
                />
              )}
              {activeTab === "finance" && (
                <FinanceTab />
              )}
              {activeTab === "blog" && (
                <BlogTab 
                  comments={comments} 
                  setComments={setComments} 
                  blogPosts={blogPosts}
                  setBlogPosts={setBlogPosts}
                />
              )}
              {activeTab === "deployment" && <DeploymentTab />}
              {activeTab === "copilot" && <CopilotTab />}
              {activeTab === "specs" && <SpecsExplorerTab />}
            </motion.div>
          </AnimatePresence>
        </main>
      </div>
    </div>
  );
}

// Reusable Sidebar button
function SidebarButton({ active, icon, label, onClick }: { active: boolean; icon: React.ReactNode; label: string; onClick: () => void }) {
  return (
    <button
      onClick={onClick}
      className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium transition-all ${
        active
          ? "bg-indigo-600/15 text-indigo-300 border border-indigo-500/25 shadow-sm"
          : "text-slate-400 hover:text-slate-200 hover:bg-slate-900/60 border border-transparent"
      }`}
    >
      <span className={active ? "text-indigo-400" : "text-slate-500"}>{icon}</span>
      <span>{label}</span>
    </button>
  );
}

// 1. Overview Tab (With Project Request Submission, Product Store, Contact Submissions)
function OverviewTab({
  copiedText,
  handleCopy,
  projectRequests,
  setProjectRequests,
  contactSubmissions,
  setContactSubmissions,
  triggerPayment
}: {
  copiedText: string | null;
  handleCopy: (text: string, label: string) => void;
  projectRequests: any[];
  setProjectRequests: React.Dispatch<React.SetStateAction<any[]>>;
  contactSubmissions: any[];
  setContactSubmissions: React.Dispatch<React.SetStateAction<any[]>>;
  triggerPayment: (phone: string, amt: string, inv: string) => void;
}) {
  const [reqTitle, setReqTitle] = useState("");
  const [reqBudget, setReqBudget] = useState("150000");
  const [reqDesc, setReqDesc] = useState("");
  
  const [leadName, setLeadName] = useState("");
  const [leadEmail, setLeadEmail] = useState("");
  const [leadSubject, setLeadSubject] = useState("");
  const [leadMessage, setLeadMessage] = useState("");

  const [activeStoreCategory, setActiveStoreCategory] = useState("All");

  const digitalProducts = [
    { id: "P-1", name: "SaaS Multi-tenant Postgres Boilerplate", price: "5500", desc: "React + Fastify framework setup including partition scripts & RLS rules.", category: "Boilerplates" },
    { id: "P-2", name: "Lipa Na M-PESA Callback Validator", price: "1850", desc: "Express middleware parsing, decrypting & checking Safaricom Daraja payloads.", category: "SDKs" },
    { id: "P-3", name: "Cisco & Ubiquiti Network Config Shells", price: "3200", desc: "Ready router deployment shell files for high-traffic office cabling.", category: "IT Scripts" }
  ];

  const handleRequestSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!reqTitle.trim() || !reqDesc.trim()) return;
    const newReq = {
      id: `REQ-${Math.floor(100 + Math.random() * 900)}`,
      title: reqTitle,
      description: reqDesc,
      budget: `KES ${Number(reqBudget).toLocaleString()}`,
      status: "pending",
      created_at: "Just now"
    };
    setProjectRequests([newReq, ...projectRequests]);
    setReqTitle("");
    setReqDesc("");
  };

  const handleLeadSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!leadName.trim() || !leadMessage.trim()) return;
    const newSubmission = {
      id: `CON-${Math.floor(100 + Math.random() * 900)}`,
      fullName: leadName,
      email: leadEmail,
      subject: leadSubject,
      message: leadMessage,
      status: "unread",
      created_at: "Just now"
    };
    setContactSubmissions([newSubmission, ...contactSubmissions]);
    setLeadName("");
    setLeadEmail("");
    setLeadSubject("");
    setLeadMessage("");
  };

  const filteredProducts = activeStoreCategory === "All" 
    ? digitalProducts 
    : digitalProducts.filter(p => p.category === activeStoreCategory);

  return (
    <div className="space-y-8">
      {/* Hero */}
      <div className="p-8 rounded-2xl border border-slate-800 bg-gradient-to-br from-indigo-950/20 via-slate-900/40 to-slate-950 relative overflow-hidden">
        <div className="absolute top-0 right-0 p-8 opacity-5 pointer-events-none">
          <Terminal size={180} />
        </div>
        <div className="max-w-3xl space-y-3">
          <span className="text-[10px] font-mono text-indigo-400 uppercase font-extrabold tracking-widest px-2.5 py-1 rounded bg-indigo-500/10 border border-indigo-500/20">
            Enterprise Client Services Portal
          </span>
          <h2 className="text-3xl font-display font-bold tracking-tight text-white">
            Transforming software development & IT consulting into modular precision.
          </h2>
          <p className="text-sm text-slate-400 leading-relaxed">
            JUANET centralizes technical project scoping, employee RBAC mappings, instant M-PESA invoice clearing, and Headless CMS distributions. Click through the playgrounds to visualize our relational double-entry ledger database structure in real-time.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {/* Project Scoper & Lead Form */}
        <div className="xl:col-span-2 space-y-6">
          <div className="p-6 rounded-xl border border-slate-800 bg-slate-900/20 space-y-4">
            <h3 className="text-lg font-bold text-white flex items-center gap-2">
              <Sparkles size={18} className="text-indigo-400" />
              Submit Project Request (Client Portal Simulation)
            </h3>
            <form onSubmit={handleRequestSubmit} className="space-y-3">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-[10px] font-mono text-slate-400">PROJECT TITLE</label>
                  <input
                    type="text"
                    value={reqTitle}
                    onChange={(e) => setReqTitle(e.target.value)}
                    placeholder="e.g. Office LAN & Client Dashboard"
                    className="w-full bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded px-3 py-2 text-xs"
                    required
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] font-mono text-slate-400">ESTIMATED BUDGET (KES)</label>
                  <select
                    value={reqBudget}
                    onChange={(e) => setReqBudget(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded px-3 py-2 text-xs"
                  >
                    <option value="45000">KES 45,000 (Small Design / Consultation)</option>
                    <option value="150000">KES 150,000 (Standard Web / WiFi setup)</option>
                    <option value="650000">KES 650,000 (Full-stack SaaS platform)</option>
                    <option value="1500000">KES 1,500,000+ (Enterprise Softwares)</option>
                  </select>
                </div>
              </div>
              <div className="space-y-1">
                <label className="text-[10px] font-mono text-slate-400">SCOPE OF WORK SPECIFICATIONS</label>
                <textarea
                  value={reqDesc}
                  onChange={(e) => setReqDesc(e.target.value)}
                  placeholder="Detail your requirements here..."
                  rows={3}
                  className="w-full bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded px-3 py-2 text-xs"
                  required
                />
              </div>
              <button
                type="submit"
                className="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-semibold transition-colors flex items-center justify-center gap-1.5"
              >
                Create `project_requests` Record <ArrowRight size={14} />
              </button>
            </form>

            {/* Active Request Log */}
            <div className="space-y-2 mt-4 pt-4 border-t border-slate-800/60">
              <span className="text-[10px] font-mono text-indigo-400 uppercase font-bold tracking-wider">PROJECT REQUESTS SCHEMA LOG (`project_requests`)</span>
              <div className="space-y-2">
                {projectRequests.map((req) => (
                  <div key={req.id} className="p-3 rounded bg-slate-950/60 border border-slate-900 flex justify-between items-start gap-4">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className="text-xs font-bold text-slate-200">{req.title}</span>
                        <span className="text-[9px] font-mono text-slate-500">{req.id}</span>
                      </div>
                      <p className="text-[11px] text-slate-400 line-clamp-2">{req.description}</p>
                      <div className="flex items-center gap-2 text-[10px] text-slate-500 pt-1">
                        <span>Budget: <strong className="text-slate-300">{req.budget}</strong></span>
                        <span>&bull;</span>
                        <span>{req.created_at}</span>
                      </div>
                    </div>
                    <span className="bg-yellow-500/10 text-yellow-400 border border-yellow-500/20 px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase">
                      {req.status}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Service Grid Catalog */}
          <div className="p-6 rounded-xl border border-slate-800 bg-slate-900/20 space-y-4">
            <h3 className="text-lg font-bold text-white flex items-center gap-2">
              <Globe size={18} className="text-indigo-400" />
              JUANET Core Agency Services Catalog
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {SERVICE_ITEMS.slice(0, 6).map((srv, idx) => (
                <div key={idx} className="p-4 bg-slate-950/60 border border-slate-900 rounded-lg hover:border-indigo-500/30 transition-all flex flex-col justify-between">
                  <div>
                    <span className="text-[9px] font-mono text-indigo-400 uppercase">{srv.category}</span>
                    <h4 className="text-xs font-bold text-slate-200 mt-1">{srv.name}</h4>
                  </div>
                  <div className="text-[11px] font-mono text-slate-400 pt-2 border-t border-slate-900 mt-3 flex justify-between items-center">
                    <span>Est:</span>
                    <span className="text-indigo-300 font-semibold">{srv.rate}</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Digital Products Marketplace & Contact Submit */}
        <div className="space-y-6">
          {/* Marketplace Catalog */}
          <div className="p-6 rounded-xl border border-slate-800 bg-slate-900/20 space-y-4">
            <div className="flex justify-between items-center">
              <h3 className="text-sm font-bold text-white flex items-center gap-1.5">
                <ShoppingCart size={16} className="text-indigo-400" />
                Digital Products Store
              </h3>
              <div className="flex gap-1">
                {["All", "SDKs", "Boilerplates"].map(cat => (
                  <button
                    key={cat}
                    onClick={() => setActiveStoreCategory(cat)}
                    className={`text-[9px] font-mono px-2 py-0.5 rounded transition-all ${
                      activeStoreCategory === cat 
                        ? "bg-indigo-600 text-white" 
                        : "bg-slate-950 text-slate-400 hover:text-slate-200"
                    }`}
                  >
                    {cat}
                  </button>
                ))}
              </div>
            </div>

            <div className="space-y-3">
              {filteredProducts.map(p => (
                <div key={p.id} className="p-3.5 bg-slate-950/60 border border-slate-900 rounded-lg flex flex-col justify-between gap-2.5">
                  <div>
                    <div className="flex justify-between items-start gap-2">
                      <span className="text-xs font-bold text-slate-200">{p.name}</span>
                      <span className="text-[11px] font-mono text-emerald-400 font-bold">KES {Number(p.price).toLocaleString()}</span>
                    </div>
                    <p className="text-[11px] text-slate-400 mt-1">{p.desc}</p>
                  </div>
                  <button
                    onClick={() => triggerPayment("254712345678", p.price, `INV-STORE-${p.id}`)}
                    className="w-full py-1.5 bg-emerald-600/10 hover:bg-emerald-600/25 border border-emerald-500/20 hover:border-emerald-500/40 text-emerald-400 rounded text-[10px] font-mono font-bold uppercase transition-all flex items-center justify-center gap-1"
                  >
                    <CreditCard size={12} /> Buy with M-PESA
                  </button>
                </div>
              ))}
            </div>
          </div>

          {/* Support desk Lead submitted forms */}
          <div className="p-6 rounded-xl border border-slate-800 bg-slate-900/20 space-y-4">
            <h3 className="text-sm font-bold text-white flex items-center gap-1.5">
              <Mail size={16} className="text-indigo-400" />
              Contact Form / Leads
            </h3>
            <form onSubmit={handleLeadSubmit} className="space-y-2">
              <div className="grid grid-cols-2 gap-2">
                <input
                  type="text"
                  placeholder="Full Name"
                  value={leadName}
                  onChange={(e) => setLeadName(e.target.value)}
                  className="bg-slate-950 border border-slate-800 rounded px-2.5 py-1.5 text-[11px] focus:outline-none focus:border-indigo-500 text-slate-300"
                  required
                />
                <input
                  type="email"
                  placeholder="Email"
                  value={leadEmail}
                  onChange={(e) => setLeadEmail(e.target.value)}
                  className="bg-slate-950 border border-slate-800 rounded px-2.5 py-1.5 text-[11px] focus:outline-none focus:border-indigo-500 text-slate-300"
                  required
                />
              </div>
              <input
                type="text"
                placeholder="Subject"
                value={leadSubject}
                onChange={(e) => setLeadSubject(e.target.value)}
                className="w-full bg-slate-950 border border-slate-800 rounded px-2.5 py-1.5 text-[11px] focus:outline-none focus:border-indigo-500 text-slate-300"
                required
              />
              <textarea
                placeholder="Your message..."
                value={leadMessage}
                onChange={(e) => setLeadMessage(e.target.value)}
                rows={2}
                className="w-full bg-slate-950 border border-slate-800 rounded px-2.5 py-1.5 text-[11px] focus:outline-none focus:border-indigo-500 text-slate-300"
                required
              />
              <button
                type="submit"
                className="w-full py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[10px] font-bold uppercase transition-colors"
              >
                Submit Lead Subscriptions
              </button>
            </form>

            <div className="pt-2">
              <span className="text-[9px] font-mono text-slate-500 uppercase block mb-1">Submissions Queue (`contact_submissions`):</span>
              <div className="space-y-1.5">
                {contactSubmissions.map(lead => (
                  <div key={lead.id} className="p-2 rounded bg-slate-950 text-[10px] border border-slate-900 text-slate-400">
                    <div className="flex justify-between font-mono text-indigo-400 mb-0.5">
                      <span>{lead.fullName} &bull; {lead.email}</span>
                      <span>{lead.created_at}</span>
                    </div>
                    <p className="text-slate-300 italic">"{lead.message}"</p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

// 2. System Architecture Flow Diagram Tab
function SystemArchTab() {
  const [activeCycleStep, setActiveCycleStep] = useState(0);

  const steps = [
    { title: "Client Action", desc: "User logs request, initiates M-PESA Lipa Na M-PESA Online STK transaction." },
    { title: "Safaricom Daraja API", desc: "STK request processed by Safaricom network; Subscriber enters PIN on device." },
    { title: "Express Server Webhook", desc: "Express controller validates HMAC hash callback payload & registers ledger log." },
    { title: "Supabase DB update", desc: "Row level security rules checked; invoices marked paid; project files unlocked." }
  ];

  useEffect(() => {
    const timer = setInterval(() => {
      setActiveCycleStep(prev => (prev + 1) % steps.length);
    }, 4500);
    return () => clearInterval(timer);
  }, []);

  return (
    <div className="space-y-8">
      <div>
        <h3 className="text-xl font-display font-bold text-white flex items-center gap-2">
          <Layers size={22} className="text-indigo-400" />
          JUANET Real-time System Flow Chart
        </h3>
        <p className="text-xs text-slate-400">Visualization of the secure client payment and automatic project service dispatch flow.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-4">
        {steps.map((st, i) => (
          <div
            key={i}
            onClick={() => setActiveCycleStep(i)}
            className={`p-5 rounded-xl border transition-all cursor-pointer flex flex-col justify-between gap-4 h-40 ${
              activeCycleStep === i
                ? "bg-indigo-600/10 border-indigo-500 shadow-lg shadow-indigo-600/5 ring-1 ring-indigo-500/20"
                : "bg-slate-900/30 border-slate-800 hover:border-slate-700"
            }`}
          >
            <div className="flex justify-between items-start">
              <span className={`text-[10px] font-mono px-2 py-0.5 rounded ${activeCycleStep === i ? "bg-indigo-600 text-white" : "bg-slate-800 text-slate-400"}`}>
                STAGE 0{i + 1}
              </span>
              {activeCycleStep === i && (
                <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping" />
              )}
            </div>
            <div>
              <h4 className={`text-xs font-bold mb-1 ${activeCycleStep === i ? "text-indigo-300" : "text-slate-300"}`}>
                {st.title}
              </h4>
              <p className="text-[11px] text-slate-400 leading-normal">{st.desc}</p>
            </div>
          </div>
        ))}
      </div>

      {/* Visual Canvas Diagram */}
      <div className="p-8 rounded-2xl border border-slate-800 bg-slate-950 flex flex-col items-center justify-center space-y-6 relative overflow-hidden min-h-[300px]">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(99,102,241,0.03)_0%,transparent_70%)] pointer-events-none" />
        
        <div className="flex flex-col md:flex-row items-center justify-center gap-8 md:gap-16 relative z-10 w-full max-w-4xl">
          {/* Client Node */}
          <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/60 w-44 text-center space-y-2">
            <Laptop className="mx-auto text-indigo-400" size={28} />
            <div>
              <span className="text-xs font-bold text-slate-200 block">Next.js Marketing</span>
              <span className="text-[9px] font-mono text-slate-500 uppercase">Client Portal</span>
            </div>
          </div>

          <ChevronRight className="hidden md:block text-slate-700 rotate-90 md:rotate-0" />

          {/* Core Express API Node */}
          <div className="p-4 rounded-xl border border-indigo-500/30 bg-slate-900/60 w-44 text-center space-y-2 relative">
            <div className="absolute -top-2 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-[8px] font-mono px-1.5 py-0.2 rounded font-bold uppercase">
              Microservice
            </div>
            <Server className="mx-auto text-indigo-400 animate-pulse" size={28} />
            <div>
              <span className="text-xs font-bold text-slate-200 block">Express API Router</span>
              <span className="text-[9px] font-mono text-slate-500 uppercase">Cloud Run Engine</span>
            </div>
          </div>

          <ChevronRight className="hidden md:block text-slate-700 rotate-90 md:rotate-0" />

          {/* Safaricom Integration Node */}
          <div className="p-4 rounded-xl border border-emerald-500/20 bg-slate-900/60 w-44 text-center space-y-2">
            <Wifi className="mx-auto text-emerald-400" size={28} />
            <div>
              <span className="text-xs font-bold text-slate-200 block">Safaricom Daraja</span>
              <span className="text-[9px] font-mono text-emerald-500 uppercase font-bold">M-PESA Checkout</span>
            </div>
          </div>

          <ChevronRight className="hidden md:block text-slate-700 rotate-90 md:rotate-0" />

          {/* Database Node */}
          <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/60 w-44 text-center space-y-2">
            <Database className="mx-auto text-indigo-400" size={28} />
            <div>
              <span className="text-xs font-bold text-slate-200 block">PostgreSQL Database</span>
              <span className="text-[9px] font-mono text-slate-500 uppercase">Supabase RLS</span>
            </div>
          </div>
        </div>

        <div className="text-xs font-mono text-slate-500 bg-slate-900 px-4 py-2 rounded-lg border border-slate-900 flex items-center gap-2">
          <Activity size={12} className="text-indigo-400 animate-spin" />
          <span>Active Loop Simulation: <strong>{steps[activeCycleStep].title}</strong> is communicating.</span>
        </div>
      </div>
    </div>
  );
}

// 3. Database Schema and Interactive ERD Tab
function DatabaseTab({ copiedText, handleCopy }: { copiedText: string | null; handleCopy: (text: string, label: string) => void }) {
  const [selectedTable, setSelectedTable] = useState<TableSchema>(DB_SCHEMAS[0]);
  const [erdGroup, setErdGroup] = useState<string>("all");

  const getTableDomain = (tableName: string) => {
    if (["organizations", "organization_members", "profiles", "user_preferences", "user_sessions", "api_keys", "settings"].includes(tableName)) return "core";
    if (["folders", "files", "file_versions"].includes(tableName)) return "storage";
    if (["leads", "companies", "contacts", "lead_activities"].includes(tableName)) return "crm";
    if (["proposal_templates", "proposals", "proposal_items"].includes(tableName)) return "proposals";
    if (["project_requests", "projects", "project_milestones", "project_tasks", "project_subtasks", "project_time_logs", "project_updates"].includes(tableName)) return "projects";
    if (["support_tickets", "ticket_messages", "kb_categories", "kb_articles"].includes(tableName)) return "support";
    if (["financial_accounts", "financial_transactions", "expenses", "invoices", "invoice_items", "payment_requests", "payments"].includes(tableName)) return "finance";
    if (["product_categories", "products", "product_orders", "purchase_codes", "downloads"].includes(tableName)) return "marketplace";
    if (["cms_pages", "portfolio_items", "testimonials", "blog_categories", "blog_posts"].includes(tableName)) return "cms";
    if (["contracts", "signatures"].includes(tableName)) return "contracts";
    if (["workflows", "workflow_triggers", "workflow_actions", "workflow_executions"].includes(tableName)) return "workflows";
    if (["ai_conversations", "ai_messages", "ai_prompts"].includes(tableName)) return "ai";
    if (["job_queue", "timeline_events", "audit_logs"].includes(tableName)) return "platform";
    if (["employees", "roles", "permissions", "role_permissions", "user_roles", "integrations", "smtp_settings"].includes(tableName)) return "rbac";
    return "other";
  };

  const domainNames: Record<string, string> = {
    core: "Core & Tenancy",
    storage: "Global Storage",
    crm: "CRM Pipelines",
    proposals: "Proposals & Bids",
    projects: "Project Delivery",
    support: "Helpdesk & KB",
    finance: "Finance & Ledger",
    marketplace: "Marketplace Store",
    cms: "CMS & Blog Showcase",
    contracts: "Legal Contracts",
    workflows: "Workflows",
    ai: "AI Copilot",
    platform: "Background Platform",
    rbac: "RBAC & Staff"
  };

  const dynamicGroupings = Object.keys(domainNames).map(key => {
    const count = DB_SCHEMAS.filter(t => getTableDomain(t.name) === key).length;
    return { key, name: `${domainNames[key]} (${count})` };
  });

  const domainGroupings = [
    { key: "all", name: `All ${DB_SCHEMAS.length} Tables` },
    ...dynamicGroupings.filter(g => DB_SCHEMAS.some(t => getTableDomain(t.name) === g.key))
  ];

  const filteredTables = erdGroup === "all" 
    ? DB_SCHEMAS 
    : DB_SCHEMAS.filter(t => getTableDomain(t.name) === erdGroup);

  const generateDDL = (table: TableSchema) => {
    const cols = table.columns.map(c => `  ${c.name} ${c.type} ${c.constraints}`).join(",\n");
    return `CREATE TABLE public.${table.name} (\n${cols}\n);\n\n-- Indexes:\n${table.indexes.join("\n")}\n\n-- Row-Level Security Rules:\n${table.rlsPolicies.join("\n")}`;
  };

  return (
    <div className="space-y-8">
      <div>
        <h3 className="text-xl font-display font-bold text-white flex items-center gap-2">
          <Database size={22} className="text-indigo-400" />
          JUANET Enterprise SQL ERD & Relational Schemas
        </h3>
        <p className="text-xs text-slate-400">Interactive schema designer detailing columns, constraint keys, RLS security policies, and indexes.</p>
      </div>

      {/* Relational ERD Visual Blocks */}
      <div className="p-6 rounded-2xl border border-slate-800 bg-slate-900/10 space-y-6">
        <div className="flex flex-wrap justify-between items-center gap-4">
          <span className="text-[10px] font-mono text-indigo-400 uppercase font-extrabold tracking-wider">ENTERPRISE GRAPH RELATION VIEW (ERD)</span>
          <div className="flex flex-wrap gap-1.5">
            {domainGroupings.map(grp => (
              <button
                key={grp.key}
                onClick={() => {
                  setErdGroup(grp.key);
                  const firstOfGroup = DB_SCHEMAS.find(t => grp.key === "all" || getTableDomain(t.name) === grp.key);
                  if (firstOfGroup) setSelectedTable(firstOfGroup);
                }}
                className={`px-2.5 py-1 rounded text-xs transition-all ${
                  erdGroup === grp.key 
                    ? "bg-indigo-600 text-white font-semibold" 
                    : "bg-slate-950 text-slate-400 hover:text-slate-200 border border-slate-900"
                }`}
              >
                {grp.name}
              </button>
            ))}
          </div>
        </div>

        {/* Dynamic Connected Node blocks */}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5 pt-2">
          {filteredTables.map(t => {
            const domain = getTableDomain(t.name);
            const isSelected = selectedTable.name === t.name;
            return (
              <div
                key={t.name}
                onClick={() => setSelectedTable(t)}
                className={`p-3.5 rounded-xl border text-left cursor-pointer transition-all flex flex-col justify-between h-28 ${
                  isSelected
                    ? "bg-indigo-600/15 border-indigo-500 shadow-md ring-1 ring-indigo-500/20"
                    : "bg-slate-950/60 border-slate-900 hover:border-slate-800"
                }`}
              >
                <div>
                  <div className="flex justify-between items-center mb-1">
                    <span className="text-[8px] font-mono font-semibold uppercase text-indigo-400">{domain}</span>
                    <span className="text-[8px] font-mono text-slate-500">{t.columns.length} columns</span>
                  </div>
                  <h4 className="text-xs font-bold text-slate-200 truncate font-mono">{t.name}</h4>
                </div>
                <div className="text-[10px] text-slate-500 line-clamp-2 leading-relaxed">
                  {t.description}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Relational Table Spec and SQL generation panel */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Schema Columns layout */}
        <div className="p-6 rounded-xl border border-slate-800 bg-slate-900/20 space-y-4">
          <div>
            <div className="flex items-center gap-2">
              <span className="text-xs font-mono text-indigo-400 uppercase font-semibold">Table Schema Spec</span>
              <span className="text-xs text-slate-500 font-mono font-bold uppercase">&bull; {selectedTable.name}</span>
            </div>
            <p className="text-xs text-slate-400 mt-1">{selectedTable.description}</p>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs font-mono">
              <thead>
                <tr className="border-b border-slate-800 text-slate-500 uppercase text-[9px]">
                  <th className="py-2">Column Name</th>
                  <th className="py-2">Data Type</th>
                  <th className="py-2">Constraints</th>
                  <th className="py-2 text-right">Description</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-900">
                {selectedTable.columns.map((col, idx) => (
                  <tr key={idx} className="hover:bg-slate-900/20">
                    <td className="py-2 font-bold text-slate-200">{col.name}</td>
                    <td className="py-2 text-indigo-400">{col.type}</td>
                    <td className="py-2 text-slate-500 text-[10px]">{col.constraints}</td>
                    <td className="py-2 text-right text-slate-400 text-[10px]">{col.desc}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* RLS lists */}
          <div className="space-y-2 pt-4 border-t border-slate-900">
            <h5 className="text-[10px] font-mono text-slate-400 font-bold uppercase">Row-Level Security Policies (`RLS`):</h5>
            <div className="space-y-1">
              {selectedTable.rlsPolicies.map((pol, idx) => (
                <div key={idx} className="p-2 rounded bg-slate-950 border border-slate-900 text-[10px] text-indigo-300 leading-normal font-mono">
                  {pol}
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Code Generator block */}
        <div className="space-y-3">
          <div className="flex justify-between items-center">
            <span className="text-xs font-mono text-slate-400 font-bold uppercase">Generated Postgres Migration DDL script</span>
            <button
              onClick={() => handleCopy(generateDDL(selectedTable), "sql-copy")}
              className="px-3 py-1 bg-slate-900 hover:bg-slate-800 text-slate-300 rounded text-[10px] font-mono border border-slate-800 flex items-center gap-1 transition-all"
            >
              {copiedText === "sql-copy" ? <Check size={12} className="text-emerald-400" /> : <Copy size={12} />}
              <span>{copiedText === "sql-copy" ? "COPIED" : "COPY DDL"}</span>
            </button>
          </div>
          <div className="bg-slate-950 p-5 rounded-xl border border-slate-800 overflow-x-auto text-[11px] font-mono text-indigo-300 leading-relaxed max-h-[460px] overflow-y-auto">
            <pre>{generateDDL(selectedTable)}</pre>
          </div>
        </div>
      </div>
    </div>
  );
}

// 4. API Core and Express Routing playground Tab
function ApiTab({ copiedText, handleCopy }: { copiedText: string | null; handleCopy: (text: string, label: string) => void }) {
  const [selectedEndpoint, setSelectedEndpoint] = useState<ApiEndpoint>(API_ENDPOINTS[0]);
  const [apiConsoleOutput, setApiConsoleOutput] = useState<string>("Console ready. Select route and click 'Send Simulator Request' above.");
  const [isSimulatingApi, setIsSimulatingApi] = useState(false);

  const simulateApiCall = () => {
    setIsSimulatingApi(true);
    setApiConsoleOutput("POST /api/payments/stk-push HTTP/1.1\nHost: api.juanet.co\nAuthorization: Bearer <token>\nContent-Type: application/json\n\nRequest processing...");
    
    setTimeout(() => {
      setApiConsoleOutput(`HTTP/1.1 200 OK\nContent-Type: application/json\nDate: ${new Date().toUTCString()}\n\n${selectedEndpoint.responseBody}`);
      setIsSimulatingApi(false);
    }, 1500);
  };

  const generateExpressCode = () => {
    return `import express from "express";
import { supabase } from "../lib/supabase";
const router = express.Router();

// ${selectedEndpoint.description}
router.${selectedEndpoint.method.toLowerCase()}("${selectedEndpoint.path}", async (req, res) => {
  try {
    const roles = ${JSON.stringify(selectedEndpoint.roles)};
    console.log("Validating security clearance for:", roles);
    
    // Simulate RLS validation & database clearance
    res.json(${selectedEndpoint.responseBody});
  } catch (err: any) {
    res.status(500).json({ error: err.message });
  }
});

export default router;`;
  };

  return (
    <div className="space-y-8">
      <div>
        <h3 className="text-xl font-display font-bold text-white flex items-center gap-2">
          <Code size={22} className="text-indigo-400" />
          JUANET Express REST API core playground
        </h3>
        <p className="text-xs text-slate-400">Interactive sandbox to trigger API payloads and preview generated back-end TypeScript controller controllers.</p>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {/* Endpoint Catalog */}
        <div className="p-5 rounded-xl border border-slate-800 bg-slate-900/20 space-y-3.5">
          <h4 className="text-xs font-mono text-slate-400 font-extrabold uppercase">API Routes Catalog</h4>
          <div className="space-y-2">
            {API_ENDPOINTS.map((api, idx) => (
              <button
                key={idx}
                onClick={() => {
                  setSelectedEndpoint(api);
                  setApiConsoleOutput("Console ready. Click 'Send Simulator Request' above.");
                }}
                className={`w-full p-3.5 rounded-lg border text-left transition-all ${
                  selectedEndpoint.path === api.path && selectedEndpoint.method === api.method
                    ? "bg-indigo-600/15 border-indigo-500 shadow-md"
                    : "bg-slate-950/60 border-slate-900 hover:border-slate-800"
                }`}
              >
                <div className="flex items-center gap-2 mb-1.5">
                  <span className={`text-[9px] font-mono font-bold px-2 py-0.5 rounded ${
                    api.method === "POST" ? "bg-emerald-500/10 text-emerald-400" : "bg-blue-500/10 text-blue-400"
                  }`}>
                    {api.method}
                  </span>
                  <span className="text-[11px] font-mono text-slate-300 font-semibold truncate">{api.path}</span>
                </div>
                <p className="text-[10px] text-slate-500 line-clamp-2 leading-relaxed">{api.description}</p>
              </button>
            ))}
          </div>
        </div>

        {/* Simulator & Playground (2 cols) */}
        <div className="xl:col-span-2 space-y-6">
          <div className="p-6 rounded-xl border border-slate-800 bg-slate-900/20 space-y-4">
            <div className="flex justify-between items-center">
              <h4 className="text-sm font-bold text-white">Interactive Playground Simulator</h4>
              <button
                onClick={simulateApiCall}
                disabled={isSimulatingApi}
                className="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-semibold flex items-center gap-1.5 transition-colors disabled:opacity-40"
              >
                {isSimulatingApi ? <RefreshCw size={12} className="animate-spin" /> : <Play size={12} />}
                <span>Send Simulator Request</span>
              </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Payload send */}
              <div className="space-y-2">
                <span className="text-[10px] font-mono text-slate-400 uppercase">Input Payload (Request Body):</span>
                <div className="bg-slate-950 p-4 rounded-lg border border-slate-900 font-mono text-[10.5px] text-indigo-300 h-44 overflow-y-auto">
                  <pre>{selectedEndpoint.requestBody || "// GET request - No request body required"}</pre>
                </div>
              </div>
              {/* Output received */}
              <div className="space-y-2">
                <span className="text-[10px] font-mono text-slate-400 uppercase">Console Response Dump:</span>
                <div className="bg-slate-950 p-4 rounded-lg border border-slate-900 font-mono text-[10.5px] text-emerald-400 h-44 overflow-y-auto leading-relaxed">
                  <pre>{apiConsoleOutput}</pre>
                </div>
              </div>
            </div>
          </div>

          {/* Controller code generation */}
          <div className="space-y-3">
            <div className="flex justify-between items-center">
              <span className="text-xs font-mono text-slate-400 font-bold uppercase">Auto-Generated Express Route Controller (`TypeScript`)</span>
              <button
                onClick={() => handleCopy(generateExpressCode(), "api-copy")}
                className="px-3 py-1 bg-slate-900 hover:bg-slate-800 text-slate-300 rounded text-[10px] font-mono border border-slate-800 flex items-center gap-1 transition-all"
              >
                {copiedText === "api-copy" ? <Check size={12} className="text-emerald-400" /> : <Copy size={12} />}
                <span>{copiedText === "api-copy" ? "COPIED" : "COPY CONTROLLER"}</span>
              </button>
            </div>
            <div className="bg-slate-950 p-5 rounded-xl border border-slate-800 overflow-x-auto text-[11px] font-mono text-indigo-300 leading-relaxed max-h-[350px] overflow-y-auto">
              <pre>{generateExpressCode()}</pre>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

// 5. Authentication and Staff RBAC tab component
function AuthTab() {
  const [selectedStaff, setSelectedStaff] = useState<any>({
    name: "Alex Kibet",
    role: "Lead Architect",
    salary: "KES 245,000",
    dateHired: "Jan 12, 2024",
    permissions: {
      "projects:write": true,
      "billing:refund": true,
      "marketplace:moderate": true,
      "smtp:configure": true
    }
  });

  const staffCatalog = [
    { name: "Alex Kibet", role: "Lead Architect", salary: "KES 245,000", dateHired: "Jan 12, 2024", perms: ["projects:write", "billing:refund", "marketplace:moderate", "smtp:configure"] },
    { name: "Brenda Wambui", role: "Developer", salary: "KES 160,000", dateHired: "May 02, 2025", perms: ["projects:write", "marketplace:moderate"] },
    { name: "Charles Njoroge", role: "Financial Accountant", salary: "KES 140,000", dateHired: "Mar 10, 2026", perms: ["billing:refund"] }
  ];

  const togglePermission = (permKey: string) => {
    setSelectedStaff({
      ...selectedStaff,
      permissions: {
        ...selectedStaff.permissions,
        [permKey]: !selectedStaff.permissions[permKey]
      }
    });
  };

  return (
    <div className="space-y-8">
      <div>
        <h3 className="text-xl font-display font-bold text-white flex items-center gap-2">
          <Lock size={22} className="text-indigo-400" />
          Employee Role-Based Access Control (RBAC) Security
        </h3>
        <p className="text-xs text-slate-400">Simulate staff payroll administration, custom security roles, and real-time permission scopes.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Staff list */}
        <div className="p-5 rounded-xl border border-slate-800 bg-slate-900/20 space-y-4">
          <h4 className="text-xs font-mono text-slate-400 font-extrabold uppercase">Staff Personnel Registry (`employees`)</h4>
          <div className="space-y-2">
            {staffCatalog.map((st, i) => (
              <div
                key={i}
                onClick={() => setSelectedStaff({
                  name: st.name,
                  role: st.role,
                  salary: st.salary,
                  dateHired: st.dateHired,
                  permissions: {
                    "projects:write": st.perms.includes("projects:write"),
                    "billing:refund": st.perms.includes("billing:refund"),
                    "marketplace:moderate": st.perms.includes("marketplace:moderate"),
                    "smtp:configure": st.perms.includes("smtp:configure")
                  }
                })}
                className={`p-3.5 rounded-lg border text-left cursor-pointer transition-all ${
                  selectedStaff.name === st.name 
                    ? "bg-indigo-600/15 border-indigo-500 shadow-md" 
                    : "bg-slate-950/60 border-slate-900 hover:border-slate-800"
                }`}
              >
                <div className="flex justify-between items-start mb-1">
                  <span className="text-xs font-bold text-slate-200">{st.name}</span>
                  <span className="bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-1.5 py-0.5 rounded text-[8px] font-mono font-bold uppercase">
                    {st.role}
                  </span>
                </div>
                <div className="flex items-center justify-between text-[10px] text-slate-500 pt-1 border-t border-slate-900 mt-2">
                  <span>Hired: {st.dateHired}</span>
                  <span className="text-slate-300 font-mono font-bold">{st.salary}</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Live Permission Editor (2 cols) */}
        <div className="lg:col-span-2 p-6 rounded-xl border border-slate-800 bg-slate-900/20 space-y-6">
          <div className="flex justify-between items-start">
            <div>
              <h4 className="text-sm font-bold text-white flex items-center gap-1">
                <UserCheck size={16} className="text-indigo-400" />
                Security Access Token scope editor
              </h4>
              <p className="text-xs text-slate-400 mt-0.5">Toggle active authorization tokens for <strong>{selectedStaff.name}</strong></p>
            </div>
            <span className="bg-indigo-600 text-white font-mono text-[10px] px-2 py-0.5 rounded border border-indigo-500/30">
              {selectedStaff.role}
            </span>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Project modify */}
            <div className="p-4 rounded-lg bg-slate-950 border border-slate-900 flex justify-between items-center">
              <div className="space-y-1">
                <span className="text-xs font-bold text-slate-200 block font-mono">projects:write</span>
                <span className="text-[10px] text-slate-500 block leading-tight">Create milestones, submit updates, and upload files.</span>
              </div>
              <input
                type="checkbox"
                checked={!!selectedStaff.permissions["projects:write"]}
                onChange={() => togglePermission("projects:write")}
                className="w-4 h-4 text-indigo-600 border-slate-800 rounded focus:ring-indigo-500 focus:ring-offset-0 bg-slate-950"
              />
            </div>

            {/* Refunds */}
            <div className="p-4 rounded-lg bg-slate-950 border border-slate-900 flex justify-between items-center">
              <div className="space-y-1">
                <span className="text-xs font-bold text-slate-200 block font-mono">billing:refund</span>
                <span className="text-[10px] text-slate-500 block leading-tight">Authorize MPESA paybill reversals & void transactions.</span>
              </div>
              <input
                type="checkbox"
                checked={!!selectedStaff.permissions["billing:refund"]}
                onChange={() => togglePermission("billing:refund")}
                className="w-4 h-4 text-indigo-600 border-slate-800 rounded focus:ring-indigo-500 focus:ring-offset-0 bg-slate-950"
              />
            </div>

            {/* Marketplace */}
            <div className="p-4 rounded-lg bg-slate-950 border border-slate-900 flex justify-between items-center">
              <div className="space-y-1">
                <span className="text-xs font-bold text-slate-200 block font-mono">marketplace:moderate</span>
                <span className="text-[10px] text-slate-500 block leading-tight">Post software presets & audit purchase coupon codes.</span>
              </div>
              <input
                type="checkbox"
                checked={!!selectedStaff.permissions["marketplace:moderate"]}
                onChange={() => togglePermission("marketplace:moderate")}
                className="w-4 h-4 text-indigo-600 border-slate-800 rounded focus:ring-indigo-500 focus:ring-offset-0 bg-slate-950"
              />
            </div>

            {/* SMTP config */}
            <div className="p-4 rounded-lg bg-slate-950 border border-slate-900 flex justify-between items-center">
              <div className="space-y-1">
                <span className="text-xs font-bold text-slate-200 block font-mono">smtp:configure</span>
                <span className="text-[10px] text-slate-500 block leading-tight">Change company SMTP keys and mail servers.</span>
              </div>
              <input
                type="checkbox"
                checked={!!selectedStaff.permissions["smtp:configure"]}
                onChange={() => togglePermission("smtp:configure")}
                className="w-4 h-4 text-indigo-600 border-slate-800 rounded focus:ring-indigo-500 focus:ring-offset-0 bg-slate-950"
              />
            </div>
          </div>

          <div className="p-4 bg-slate-950 rounded-lg border border-slate-800 flex items-start gap-3">
            <Info size={16} className="text-indigo-400 mt-0.5 shrink-0" />
            <div className="text-xs text-slate-400 leading-normal">
              <strong>Database Claims Policy:</strong> In production, updating these permission scopes triggers database claims that alter the user's JWT metadata securely. Any Express API call validates these claims via JWT decryption to confirm access dynamically.
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

// 6. Project Messaging, updates, and Upload file vault
function MessagingTab({
  projectFiles,
  setProjectFiles,
  projectUpdates,
  setProjectUpdates
}: {
  projectFiles: any[];
  setProjectFiles: React.Dispatch<React.SetStateAction<any[]>>;
  projectUpdates: any[];
  setProjectUpdates: React.Dispatch<React.SetStateAction<any[]>>;
}) {
  const [updateText, setUpdateText] = useState("");
  const [isDragging, setIsDragging] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Advanced Messaging States
  const [activeChannel, setActiveChannel] = useState<"general" | "architecture" | "billing" | "sysadmin">("general");
  const [messageText, setMessageText] = useState("");
  const [isTyping, setIsTyping] = useState<string | null>(null);

  // Chat message list
  const [chatMessages, setChatMessages] = useState<any[]>([
    {
      id: "m-1",
      channel: "general",
      sender: "Joseph (Lead Developer)",
      role: "Lead Developer",
      roleColor: "bg-indigo-500/10 text-indigo-400 border-indigo-500/20",
      avatarColor: "bg-indigo-600",
      avatarText: "JD",
      text: "Welcome to the JUANET project communications space! Our local environment is fully compiled and active. We can use this channel for daily system syncs.",
      time: "9:15 AM",
      status: "read"
    },
    {
      id: "m-2",
      channel: "general",
      sender: "Mary (SaaS Architect)",
      role: "SaaS Architect",
      roleColor: "bg-violet-500/10 text-violet-400 border-violet-500/20",
      avatarColor: "bg-violet-600",
      avatarText: "MK",
      text: "I've uploaded the initial system architecture specs to the Secure Attachment Vault. Decoupled general ledger and payment routing modules are saved under project_files.",
      time: "9:22 AM",
      status: "read"
    },
    {
      id: "m-3",
      channel: "architecture",
      sender: "Mary (SaaS Architect)",
      role: "SaaS Architect",
      roleColor: "bg-violet-500/10 text-violet-400 border-violet-500/20",
      avatarColor: "bg-violet-600",
      avatarText: "MK",
      text: "The Phase 2 database entity blueprints are ready. If you click on the 'SaaS Specs Explorer' tab, you can view the exact table schemas in realtime.",
      time: "Yesterday",
      status: "read"
    },
    {
      id: "m-4",
      channel: "billing",
      sender: "Finances Webhook",
      role: "Finances System",
      roleColor: "bg-amber-500/10 text-amber-400 border-amber-500/20",
      avatarColor: "bg-amber-600",
      avatarText: "FS",
      text: "Completed Safaricom Daraja STK Push routing rules. Inbound API callback requests now decrypt secure payload hashes and update payment ledger entries instantly.",
      time: "Yesterday",
      status: "read"
    },
    {
      id: "m-5",
      channel: "sysadmin",
      sender: "SaaS Dev-Bot",
      role: "System Bot",
      roleColor: "bg-cyan-500/10 text-cyan-400 border-cyan-500/20",
      avatarColor: "bg-cyan-600",
      avatarText: "DB",
      text: "Development containers running behind reverse proxy on Port 3000. All routing and hot reloads are stable.",
      time: "2 hours ago",
      status: "read"
    }
  ]);

  const channels = [
    { id: "general", name: "# general-sync", desc: "Main chat room for the tech & management team" },
    { id: "architecture", name: "# database-architects", desc: "Reviewing database tables, RLS claims, and constraints" },
    { id: "billing", name: "# billing-mpesa", desc: "Testing Daraja STK push and payment callbacks" },
    { id: "sysadmin", name: "# DevOps-sysadmin", desc: "Checking sandbox system uptime, ports, and metrics" }
  ];

  const handleSendMessage = (e: React.FormEvent) => {
    e.preventDefault();
    if (!messageText.trim()) return;

    const userMsg = {
      id: `m-u-${Date.now()}`,
      channel: activeChannel,
      sender: "You (Client Partner)",
      role: "Client",
      roleColor: "bg-emerald-500/10 text-emerald-400 border-emerald-500/20",
      avatarColor: "bg-emerald-600",
      avatarText: "CL",
      text: messageText,
      time: "Just now",
      status: "sent"
    };

    setChatMessages(prev => [...prev, userMsg]);
    const originalText = messageText;
    setMessageText("");

    // Simulate status update to delivered and then read
    setTimeout(() => {
      setChatMessages(prev =>
        prev.map(m => m.id === userMsg.id ? { ...m, status: "delivered" } : m)
      );
    }, 600);

    setTimeout(() => {
      setChatMessages(prev =>
        prev.map(m => m.id === userMsg.id ? { ...m, status: "read" } : m)
      );
    }, 1200);

    // Simulate Team Typing auto-response
    const responderName = 
      activeChannel === "general" ? "Joseph (Lead Developer)" :
      activeChannel === "architecture" ? "Mary (SaaS Architect)" :
      activeChannel === "billing" ? "Finances Webhook" : "SaaS Dev-Bot";

    setTimeout(() => {
      setIsTyping(responderName);
    }, 1500);

    setTimeout(() => {
      setIsTyping(null);
      
      let replyText = "Message securely compiled and stored. Our technical representatives are reviewing this thread.";
      let rRole = "Lead Developer";
      let rRoleColor = "bg-indigo-500/10 text-indigo-400 border-indigo-500/20";
      let rAvatarColor = "bg-indigo-600";
      let rAvatarText = "JD";

      const textLower = originalText.toLowerCase();

      if (activeChannel === "general") {
        if (textLower.includes("update") || textLower.includes("progress")) {
          replyText = "Indeed. I have logged several updates in the sidebar. We're working on final MPESA STK integration loops.";
        } else {
          replyText = "Understood. The project structure is setup beautifully. We are maintaining an active connection and monitoring your feedback here!";
        }
      } else if (activeChannel === "architecture") {
        rRole = "SaaS Architect";
        rRoleColor = "bg-violet-500/10 text-violet-400 border-violet-500/20";
        rAvatarColor = "bg-violet-600";
        rAvatarText = "MK";
        if (textLower.includes("schema") || textLower.includes("table") || textLower.includes("postgres")) {
          replyText = "All tables are in perfect sync with PostgreSQL physical naming standards. Check our SaaS Specs Explorer for full SQL scripts.";
        } else {
          replyText = "The Phase 2 schema is mapped using composite key pairings for financial ledger entries. It keeps calculations extremely precise.";
        }
      } else if (activeChannel === "billing") {
        rRole = "Finances System";
        rRoleColor = "bg-amber-500/10 text-amber-400 border-amber-500/20";
        rAvatarColor = "bg-amber-600";
        rAvatarText = "FS";
        if (textLower.includes("mpesa") || textLower.includes("paybill") || textLower.includes("daraja")) {
          replyText = "Daraja payment endpoints are operational. If you go to the Payments tab, you can trigger simulated STK push requests and see them log instantly.";
        } else {
          replyText = "Billing channels verified. Our secure multi-gateway manager routing table will route any KES payments directly to MPESA.";
        }
      } else if (activeChannel === "sysadmin") {
        rRole = "System Bot";
        rRoleColor = "bg-cyan-500/10 text-cyan-400 border-cyan-500/20";
        rAvatarColor = "bg-cyan-600";
        rAvatarText = "DB";
        replyText = "System environment report: Port 3000 normal. CPU 0.8%. All Express router middleware scopes are active and fully operational.";
      }

      const botMsg = {
        id: `m-bot-${Date.now()}`,
        channel: activeChannel,
        sender: responderName,
        role: rRole,
        roleColor: rRoleColor,
        avatarColor: rAvatarColor,
        avatarText: rAvatarText,
        text: replyText,
        time: "Just now",
        status: "read"
      };

      setChatMessages(prev => [...prev, botMsg]);
    }, 3200);
  };

  const submitUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!updateText.trim()) return;
    const newUp = {
      id: `U-${Math.floor(100 + Math.random() * 900)}`,
      text: updateText,
      date: "Just now"
    };
    setProjectUpdates([newUp, ...projectUpdates]);
    setUpdateText("");
  };

  const processMockFiles = (filesList: FileList) => {
    const newFiles = Array.from(filesList).map(f => ({
      id: `F-${Math.floor(100 + Math.random() * 900)}`,
      name: f.name,
      size: `${(f.size / (1024 * 1024)).toFixed(2)} MB`,
      type: f.type || "application/octet-stream",
      date: "Just now"
    }));
    setProjectFiles([...newFiles, ...projectFiles]);
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => {
    setIsDragging(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      processMockFiles(e.dataTransfer.files);
    }
  };

  const activeChannelObj = channels.find(c => c.id === activeChannel);
  const filteredMessages = chatMessages.filter(m => m.channel === activeChannel);

  return (
    <div className="space-y-6">
      <div>
        <h3 className="text-xl font-display font-bold text-white flex items-center gap-2">
          <MessageSquare size={22} className="text-indigo-400" />
          Project Communications & Attachments Vault
        </h3>
        <p className="text-xs text-slate-400">
          Secure, isolated workspace channel messaging, delivery directories, and audit files.
        </p>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {/* Project Team Messenger (2 Cols on desktop) */}
        <div className="xl:col-span-2 rounded-xl border border-slate-800 bg-slate-900/20 flex flex-col h-[600px] overflow-hidden">
          {/* Header banner */}
          <div className="p-4 bg-slate-900/40 border-b border-slate-850 flex justify-between items-center shrink-0">
            <div className="flex items-center gap-2">
              <span className="p-1.5 bg-indigo-500/10 text-indigo-400 rounded-lg border border-indigo-500/10">
                <Users size={16} />
              </span>
              <div>
                <h4 className="text-xs font-bold text-slate-100 font-mono">JUANET Project Chat Engine</h4>
                <p className="text-[10px] text-slate-500">Secure end-to-end channel messaging workspace</p>
              </div>
            </div>
            <div className="flex items-center gap-1.5">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
              <span className="text-[9px] font-mono font-bold text-emerald-400 tracking-wider uppercase">Connection secure</span>
            </div>
          </div>

          <div className="flex-1 flex overflow-hidden">
            {/* Sidebar Channels List */}
            <div className="w-1/3 md:w-1/4 border-r border-slate-850 bg-slate-950/20 flex flex-col p-2.5 space-y-1 overflow-y-auto shrink-0">
              <span className="text-[9px] font-mono font-bold text-slate-500 uppercase tracking-widest block px-2 py-1">
                Channels
              </span>
              {channels.map(chan => {
                const isActive = activeChannel === chan.id;
                return (
                  <button
                    key={chan.id}
                    onClick={() => {
                      setActiveChannel(chan.id as any);
                    }}
                    className={`w-full text-left text-xs px-2.5 py-2.5 rounded-lg transition-all border flex flex-col items-start ${
                      isActive
                        ? "bg-indigo-600/15 border-indigo-500/25 text-indigo-300 font-semibold"
                        : "bg-transparent border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-900/30"
                    }`}
                  >
                    <span className="truncate w-full font-mono font-medium">{chan.name}</span>
                  </button>
                );
              })}
            </div>

            {/* Chat Messages Body */}
            <div className="flex-1 flex flex-col overflow-hidden bg-slate-950/30">
              {/* Active channel info header */}
              <div className="px-4 py-2.5 bg-slate-900/10 border-b border-slate-900 flex flex-col shrink-0">
                <span className="text-xs font-bold text-slate-200 font-mono">{activeChannelObj?.name}</span>
                <span className="text-[10px] text-slate-500 truncate">{activeChannelObj?.desc}</span>
              </div>

              {/* Message scroll list */}
              <div className="flex-1 overflow-y-auto p-4 space-y-4 flex flex-col scroll-smooth">
                {filteredMessages.map(msg => {
                  const isMe = msg.role === "Client";
                  return (
                    <div key={msg.id} className={`flex gap-2.5 max-w-[85%] ${isMe ? "ml-auto flex-row-reverse" : "mr-auto"}`}>
                      {/* Avatar */}
                      <div className={`w-7 h-7 rounded-full text-white flex items-center justify-center font-bold font-mono text-[10px] shrink-0 ${msg.avatarColor}`}>
                        {msg.avatarText}
                      </div>

                      {/* Chat text box */}
                      <div className="space-y-1">
                        <div className={`flex items-center gap-1.5 text-[10px] ${isMe ? "justify-end" : "justify-start"}`}>
                          <span className="font-bold text-slate-300 font-mono">{msg.sender}</span>
                          <span className={`text-[8px] font-mono px-1.5 py-0.1 border rounded uppercase ${msg.roleColor}`}>
                            {msg.role}
                          </span>
                        </div>
                        <div className={`p-3 rounded-xl border text-xs leading-relaxed ${
                          isMe
                            ? "bg-indigo-600/15 border-indigo-500/20 text-slate-200 rounded-tr-none"
                            : "bg-slate-900 border-slate-850 text-slate-300 rounded-tl-none"
                        }`}>
                          <p>{msg.text}</p>
                        </div>
                        <div className={`flex items-center gap-1 text-[9px] font-mono text-slate-500 ${isMe ? "justify-end" : "justify-start"}`}>
                          <span>{msg.time}</span>
                          {isMe && (
                            <span className="text-indigo-400 font-bold">
                              {msg.status === "read" ? "✓✓" : "✓"}
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                  );
                })}

                {/* Simulated Typing Indicator */}
                {isTyping && (
                  <div className="flex gap-2.5 max-w-[85%] mr-auto items-center">
                    <div className="w-7 h-7 rounded-full bg-indigo-900 text-white flex items-center justify-center font-bold font-mono text-[10px] animate-pulse">
                      ...
                    </div>
                    <div className="space-y-1">
                      <span className="text-[10px] font-bold text-slate-500 font-mono">{isTyping} is typing...</span>
                      <div className="p-3 bg-slate-900/50 border border-slate-850 rounded-xl rounded-tl-none flex items-center gap-1 px-4 py-2.5">
                        <span className="w-1.5 h-1.5 rounded-full bg-slate-500 animate-bounce" style={{ animationDelay: "0ms" }} />
                        <span className="w-1.5 h-1.5 rounded-full bg-slate-500 animate-bounce" style={{ animationDelay: "150ms" }} />
                        <span className="w-1.5 h-1.5 rounded-full bg-slate-500 animate-bounce" style={{ animationDelay: "300ms" }} />
                      </div>
                    </div>
                  </div>
                )}
              </div>

              {/* Chat Send Form */}
              <form onSubmit={handleSendMessage} className="p-3 bg-slate-900/40 border-t border-slate-850 flex gap-2 shrink-0">
                <input
                  type="text"
                  value={messageText}
                  onChange={(e) => setMessageText(e.target.value)}
                  placeholder={`Send direct message to ${activeChannelObj?.name}...`}
                  className="flex-1 bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs focus:outline-none focus:border-indigo-500 text-slate-300 animate-none"
                />
                <button
                  type="submit"
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-semibold flex items-center gap-1.5 transition-all shadow shadow-indigo-600/10"
                >
                  <Send size={12} /> Send
                </button>
              </form>
            </div>
          </div>
        </div>

        {/* Secure File Vault & Progress Logs (1 Col on desktop) */}
        <div className="xl:col-span-1 flex flex-col gap-6 h-[600px]">
          {/* File directory drag-drop */}
          <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/20 space-y-3 flex-1 flex flex-col justify-between overflow-hidden">
            <div>
              <h4 className="text-xs font-bold text-slate-200 flex items-center gap-1.5 font-mono">
                <FolderOpen size={14} className="text-indigo-400" />
                Secure Files Vault (`project_files`)
              </h4>
              <p className="text-[10px] text-slate-500">Decoupled attachments and asset distribution directory</p>
            </div>
            
            <div
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              onDrop={handleDrop}
              onClick={() => fileInputRef.current?.click()}
              className={`p-4 rounded-lg border-2 border-dashed text-center cursor-pointer transition-all flex-1 flex flex-col justify-center items-center ${
                isDragging 
                  ? "border-indigo-500 bg-indigo-600/10" 
                  : "border-slate-800 bg-slate-950/40 hover:border-slate-700"
              }`}
            >
              <input
                type="file"
                ref={fileInputRef}
                onChange={(e) => { if (e.target.files) processMockFiles(e.target.files); }}
                className="hidden"
                multiple
              />
              <FolderOpen className="text-indigo-400 mb-1.5" size={24} />
              <p className="text-[11px] font-semibold text-slate-300">Drag & drop deliverables, or click</p>
              <p className="text-[9px] text-slate-500 mt-0.5 uppercase font-mono">SUPPORTS PDF, SQL, CODES</p>
            </div>

            <div className="space-y-1.5 pt-2 border-t border-slate-900 overflow-hidden flex flex-col h-1/2">
              <span className="text-[9px] font-mono text-slate-500 uppercase block shrink-0">Active Attachments:</span>
              <div className="space-y-1 overflow-y-auto pr-1 flex-1">
                {projectFiles.map(file => (
                  <div key={file.id} className="flex justify-between items-center p-2 rounded bg-slate-950/60 border border-slate-900 text-[11px]">
                    <div className="flex items-center gap-1.5 min-w-0">
                      <FileText size={12} className="text-indigo-400 shrink-0" />
                      <div className="min-w-0">
                        <span className="font-bold text-slate-300 block text-[10px] truncate max-w-[150px]">{file.name}</span>
                        <span className="text-[8px] text-slate-500 font-mono block uppercase">{file.type} &bull; {file.size}</span>
                      </div>
                    </div>
                    <button className="p-1 text-slate-500 hover:text-slate-300 transition-colors shrink-0">
                      <Download size={12} />
                    </button>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Project updates logs */}
          <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/20 space-y-3 h-1/2 flex flex-col overflow-hidden">
            <h4 className="text-xs font-bold text-slate-200 flex items-center gap-1.5 font-mono">
              <Activity size={14} className="text-indigo-400" />
              Progress Logs (`project_updates`)
            </h4>
            
            <form onSubmit={submitUpdate} className="flex gap-1.5 shrink-0">
              <input
                type="text"
                value={updateText}
                onChange={(e) => setUpdateText(e.target.value)}
                placeholder="Log progress status..."
                className="flex-1 bg-slate-950 border border-slate-800 rounded px-2.5 py-1 text-xs focus:outline-none focus:border-indigo-500 text-slate-300"
                required
              />
              <button
                type="submit"
                className="px-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[10px] font-semibold flex items-center gap-1 transition-all"
              >
                Log Status
              </button>
            </form>

            <div className="space-y-1.5 pt-1.5 border-t border-slate-900 overflow-hidden flex flex-col flex-1">
              <span className="text-[9px] font-mono text-slate-500 uppercase block shrink-0">Historical Updates:</span>
              <div className="space-y-1.5 overflow-y-auto pr-1 flex-1">
                {projectUpdates.map(up => (
                  <div key={up.id} className="p-2 bg-slate-950/60 border border-slate-900 rounded text-[10px]">
                    <div className="flex justify-between font-mono text-indigo-400 mb-0.5 text-[8px]">
                      <span>LOG_{up.id}</span>
                      <span>{up.date}</span>
                    </div>
                    <p className="text-slate-300 leading-relaxed">{up.text}</p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

// 7. MPESA Payments Checkout Tab (Lipa Na M-PESA STK Push)
function PaymentsTab({
  copiedText,
  handleCopy,
  prefilledPhone,
  prefilledAmount,
  prefilledInvoiceId,
  clearPrefills
}: {
  copiedText: string | null;
  handleCopy: (text: string, label: string) => void;
  prefilledPhone: string;
  prefilledAmount: string;
  prefilledInvoiceId: string;
  clearPrefills: () => void;
}) {
  const [activeSubTab, setActiveSubTab] = useState<"manager" | "routing" | "checkout" | "blueprints">("manager");

  // Gateway Manager State
  const [gateways, setGateways] = useState<any[]>([
    { id: "gw-1", name: "Safaricom Daraja M-PESA", provider: "safaricom_daraja", is_active: true, mode: "sandbox", priority: 1, currencies: ["KES"], countries: ["KE"], timeout: 15, retry: 3, status: "healthy", key: "DARAJA_CONSUMER_KEY_SECURE_HASH", secret: "DARAJA_SECRET_KEY_SECURE_HASH" },
    { id: "gw-2", name: "PayHero Kenya", provider: "payhero", is_active: true, mode: "sandbox", priority: 2, currencies: ["KES"], countries: ["KE"], timeout: 20, retry: 2, status: "healthy", key: "PAYHERO_API_KEY_SECURE_HASH", secret: "PAYHERO_SECRET_SECURE_HASH" },
    { id: "gw-3", name: "Pesapal Enterprise", provider: "pesapal", is_active: true, mode: "sandbox", priority: 3, currencies: ["KES", "USD"], countries: ["KE", "TZ", "UG"], timeout: 30, retry: 4, status: "healthy", key: "PESAPAL_CONSUMER_KEY", secret: "PESAPAL_CONSUMER_SECRET" },
    { id: "gw-4", name: "Paystack Africa", provider: "paystack", is_active: true, mode: "production", priority: 1, currencies: ["NGN", "GHS", "ZAR", "USD"], countries: ["NG", "GH", "ZA", "KE"], timeout: 20, retry: 3, status: "healthy", key: "PAYSTACK_SECRET_LIVE_KEY", secret: "PAYSTACK_PUBLIC_LIVE_KEY" },
    { id: "gw-5", name: "Stripe International", provider: "stripe", is_active: true, mode: "production", priority: 1, currencies: ["USD", "EUR", "GBP", "CAD"], countries: ["US", "GB", "DE", "CA"], timeout: 15, retry: 3, status: "healthy", key: "STRIPE_SECRET_LIVE_KEY", secret: "STRIPE_WEBHOOK_SIGNING_SECRET" },
    { id: "gw-6", name: "PayPal Portal", provider: "paypal", is_active: false, mode: "sandbox", priority: 2, currencies: ["USD", "EUR", "GBP"], countries: ["US", "GB", "DE", "CA"], timeout: 25, retry: 2, status: "healthy", key: "PAYPAL_CLIENT_ID", secret: "PAYPAL_CLIENT_SECRET" },
    { id: "gw-7", name: "Flutterwave", provider: "flutterwave", is_active: false, mode: "sandbox", priority: 2, currencies: ["NGN", "KES", "USD"], countries: ["NG", "KE", "ZA"], timeout: 25, retry: 3, status: "degraded", key: "FLW_SECRET_HASH", secret: "FLW_PUBLIC_HASH" },
    { id: "gw-8", name: "DPO Group", provider: "dpo_group", is_active: false, mode: "sandbox", priority: 4, currencies: ["USD", "KES", "ZAR"], countries: ["KE", "TZ", "ZA"], timeout: 30, retry: 3, status: "healthy", key: "DPO_COMPANY_TOKEN", secret: "DPO_SERVICE_TYPE" },
    { id: "gw-9", name: "Cellulant", provider: "cellulant", is_active: false, mode: "sandbox", priority: 3, currencies: ["KES", "UGX", "TZS"], countries: ["KE", "UG", "TZ"], timeout: 25, retry: 3, status: "healthy", key: "CELLULANT_CLIENT_ID", secret: "CELLULANT_SECRET" },
    { id: "gw-10", name: "PesaLink Transfer", provider: "pesalink", is_active: false, mode: "sandbox", priority: 5, currencies: ["KES"], countries: ["KE"], timeout: 15, retry: 2, status: "healthy", key: "PESALINK_BANK_ID", secret: "PESALINK_CHANNEL_SECRET" }
  ]);

  const toggleGateway = (id: string) => {
    setGateways(prev => prev.map(gw => gw.id === id ? { ...gw, is_active: !gw.is_active } : gw));
  };

  const updateGatewayMode = (id: string, mode: "sandbox" | "production") => {
    setGateways(prev => prev.map(gw => gw.id === id ? { ...gw, mode } : gw));
  };

  const updateGatewaySettings = (id: string, key: string, value: any) => {
    setGateways(prev => prev.map(gw => gw.id === id ? { ...gw, [key]: value } : gw));
  };

  // Intelligent Routing State
  const [routeCurrency, setRouteCurrency] = useState<string>("KES");
  const [routeCountry, setRouteCountry] = useState<string>("KE");
  const [routeResults, setRouteResults] = useState<any[]>([]);

  useEffect(() => {
    // Run Routing Engine Calculation
    const eligible = gateways
      .filter(gw => gw.is_active)
      .map(gw => {
        let score = 100;
        const currencyMatch = gw.currencies.includes(routeCurrency);
        const countryMatch = gw.countries.includes(routeCountry);

        if (!currencyMatch) score -= 80;
        if (!countryMatch) score -= 30;

        // Apply Priority Penalty (priority 1 is highest, lower priority gets slight score penalty)
        score -= (gw.priority - 1) * 10;

        // Degraded status penalty
        if (gw.status === "degraded") score -= 40;
        if (gw.status === "offline") score -= 100;

        return {
          ...gw,
          matchScore: Math.max(0, score),
          isCompatible: currencyMatch
        };
      })
      .sort((a, b) => b.matchScore - a.matchScore);

    setRouteResults(eligible);
  }, [routeCurrency, routeCountry, gateways]);

  // Checkout Simulation State
  const [checkoutAmount, setCheckoutAmount] = useState<string>("45000");
  const [checkoutInvoiceId, setCheckoutInvoiceId] = useState<string>("INV-2026-001");
  const [checkoutMethod, setCheckoutMethod] = useState<string>("mpesa_stk");
  const [payerPhone, setPayerPhone] = useState<string>("254712345678");
  const [payerEmail, setPayerEmail] = useState<string>("client@example.com");

  const [checkoutProcessing, setCheckoutProcessing] = useState<boolean>(false);
  const [stkPromptVisible, setStkPromptVisible] = useState<boolean>(false);
  const [cardSecure3DVisible, setCardSecure3DVisible] = useState<boolean>(false);
  const [pinCode, setPinCode] = useState<string>("");
  const [simulationLogs, setSimulationLogs] = useState<string[]>([]);
  const [normalizedModel, setNormalizedModel] = useState<any | null>(null);

  // Sync pre-filled items
  useEffect(() => {
    if (prefilledPhone) setPayerPhone(prefilledPhone);
    if (prefilledAmount) setCheckoutAmount(prefilledAmount);
    if (prefilledInvoiceId) setCheckoutInvoiceId(prefilledInvoiceId);
  }, [prefilledPhone, prefilledAmount, prefilledInvoiceId]);

  const addLog = (msg: string) => {
    setSimulationLogs(prev => [...prev, `[${new Date().toLocaleTimeString()}] ${msg}`]);
  };

  const handleCheckoutSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setSimulationLogs([]);
    setNormalizedModel(null);
    setCheckoutProcessing(true);

    addLog(`INITIATING TRANSACTION - Invoice: ${checkoutInvoiceId}, Amount: ${routeCurrency} ${checkoutAmount}`);
    addLog(`Method Selected: ${checkoutMethod.toUpperCase()}`);

    // Find best gateway from routing results
    const bestCompatible = routeResults.find(r => r.isCompatible && r.is_active);

    if (!bestCompatible) {
      addLog(`❌ ROUTING FAILURE: No active gateway is compatible with currency ${routeCurrency}!`);
      setCheckoutProcessing(false);
      return;
    }

    addLog(`ROUTING ENGINE MATCHED: Selected Gateway [${bestCompatible.name}] (Score: ${bestCompatible.matchScore}%)`);
    addLog(`Initializing adapter for gateway provider: "${bestCompatible.provider}"`);

    setTimeout(() => {
      addLog(`Adapter method called: "Initialize Payment"`);
      addLog(`Generating idempotent tracking key and checkout token...`);
      addLog(`Webhook signature configured with secret check.`);

      if (checkoutMethod === "mpesa_stk") {
        setCheckoutProcessing(false);
        setStkPromptVisible(true);
      } else if (checkoutMethod.startsWith("card_")) {
        setCheckoutProcessing(false);
        setCardSecure3DVisible(true);
      } else {
        // Direct processing for PayPal or Bank Transfer
        finalizeCheckout(bestCompatible);
      }
    }, 1500);
  };

  const finalizeCheckout = (matchedGateway: any) => {
    setStkPromptVisible(false);
    setCardSecure3DVisible(false);
    setCheckoutProcessing(true);
    addLog(`External provider request acknowledged. Processing customer credentials...`);

    setTimeout(() => {
      addLog(`Simulating webhook callback delivery to "/api/payments/webhooks/${matchedGateway.provider}"`);
      addLog(`Validating incoming callback signature...`);
      addLog(`Replay attack protection checked: Webhook timestamp valid.`);
      addLog(`Idempotency key checked: Transaction has not been processed yet.`);

      const isMpesa = matchedGateway.provider === "safaricom_daraja" || matchedGateway.provider === "payhero";
      const randomReceipt = isMpesa 
        ? `QE${Math.floor(10000000 + Math.random() * 90000000)}`
        : `ch_${Math.random().toString(36).substring(2, 10).toUpperCase()}`;

      const computedAmount = Number(checkoutAmount);
      // Calculate normal payment processor fees (Daraja: fixed ~KES 15.00, Card: 2.9% + KES 30.00)
      const calculatedFees = matchedGateway.provider === "stripe" 
        ? Number((computedAmount * 0.029 + 30).toFixed(2))
        : isMpesa ? 15.00 : Number((computedAmount * 0.015).toFixed(2));

      const computedNet = Number((computedAmount - calculatedFees).toFixed(2));
      const normalized = {
        paymentId: `pi_intent_${Math.floor(100000 + Math.random() * 900000)}`,
        gateway: matchedGateway.name,
        provider: matchedGateway.provider,
        providerTransactionId: randomReceipt,
        referenceNumber: `TXN-${routeCurrency}-${Date.now().toString().slice(-5)}`,
        currency: routeCurrency,
        amount: computedAmount,
        fees: calculatedFees,
        netAmount: computedNet,
        status: "completed",
        paymentMethod: checkoutMethod,
        callbackPayload: {
          event: "payment.succeeded",
          gateway_ref: randomReceipt,
          meta: {
            payer_ip: "197.248.33.109",
            environment: matchedGateway.mode,
            signature_sha256: "b2c58eef71a238cd6a93b4991bc8eef1284fa933b2c58eef71a"
          }
        },
        verificationStatus: "verified",
        createdDate: new Date().toISOString(),
        completedDate: new Date().toISOString()
      };

      setNormalizedModel(normalized);
      addLog(`✅ WEBHOOK PROCESSED SUCCESSFULY!`);
      addLog(`Triggering Double-entry Ledger transaction debiting asset accounts...`);
      addLog(`Adjusting double-entry charts. Revenue posted: ${routeCurrency} ${computedNet}`);
      addLog(`Flagging Invoice [${checkoutInvoiceId}] as "PAID" in core systems.`);
      addLog(`Dispatching unified SMTP Client Notification receipt...`);
      addLog(`Triggering Workflow Automations: Project activated, Milestone pipeline configured!`);
      setCheckoutProcessing(false);
      setPinCode("");
      clearPrefills();
    }, 2000);
  };

  return (
    <div className="space-y-8">
      {/* Visual Header */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-slate-800 pb-6">
        <div>
          <h2 className="text-2xl font-display font-extrabold text-white tracking-tight flex items-center gap-2">
            <Layers className="text-indigo-400" />
            Enterprise Payment Gateway Architecture
          </h2>
          <p className="text-xs text-slate-400 max-w-2xl mt-1">
            Production-grade, provider-agnostic centralized payment system supporting concurrent integrations, intelligent routing algorithms, and atomic ledger clearance.
          </p>
        </div>

        {/* Outer Tab Controls */}
        <div className="flex bg-slate-900 border border-slate-800 rounded-lg p-1">
          <button
            onClick={() => setActiveSubTab("manager")}
            className={`px-3 py-1.5 rounded-md text-[11px] font-mono font-bold uppercase transition-all ${
              activeSubTab === "manager" ? "bg-indigo-600 text-white" : "text-slate-400 hover:text-slate-200"
            }`}
          >
            Gateway Manager
          </button>
          <button
            onClick={() => setActiveSubTab("routing")}
            className={`px-3 py-1.5 rounded-md text-[11px] font-mono font-bold uppercase transition-all ${
              activeSubTab === "routing" ? "bg-indigo-600 text-white" : "text-slate-400 hover:text-slate-200"
            }`}
          >
            Intelligent Routing
          </button>
          <button
            onClick={() => setActiveSubTab("checkout")}
            className={`px-3 py-1.5 rounded-md text-[11px] font-mono font-bold uppercase transition-all ${
              activeSubTab === "checkout" ? "bg-indigo-600 text-white" : "text-slate-400 hover:text-slate-200"
            }`}
          >
            Checkout Portal
          </button>
          <button
            onClick={() => setActiveSubTab("blueprints")}
            className={`px-3 py-1.5 rounded-md text-[11px] font-mono font-bold uppercase transition-all ${
              activeSubTab === "blueprints" ? "bg-indigo-600 text-white" : "text-slate-400 hover:text-slate-200"
            }`}
          >
            Architecture Specs
          </button>
        </div>
      </div>

      {/* SUB TAB 1: GATEWAY MANAGER */}
      {activeSubTab === "manager" && (
        <div className="space-y-6">
          <div className="flex justify-between items-center bg-indigo-950/20 border border-indigo-500/20 rounded-xl p-4">
            <div className="flex gap-3 items-start">
              <Settings className="text-indigo-400 shrink-0 mt-0.5" size={18} />
              <div>
                <h4 className="text-sm font-bold text-indigo-300">Central Gateway Manager — Super Admin Config</h4>
                <p className="text-xs text-slate-400">
                  Manage installed provider-agnostic gateway adapters. Each active adapter processes callbacks through the unified framework interface.
                </p>
              </div>
            </div>
            <span className="bg-emerald-500/10 text-emerald-400 text-[10px] font-mono border border-emerald-500/20 px-2 py-1 rounded-full uppercase">
              10 Adapters Provisioned
            </span>
          </div>

          <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
            {gateways.map(gw => (
              <div
                key={gw.id}
                className={`p-5 rounded-xl border transition-all ${
                  gw.is_active
                    ? "bg-slate-900/40 border-slate-800"
                    : "bg-slate-950/20 border-slate-900 opacity-60"
                }`}
              >
                <div className="flex justify-between items-start">
                  <div>
                    <div className="flex items-center gap-2">
                      <h4 className="text-sm font-bold text-slate-100">{gw.name}</h4>
                      <span className={`text-[9px] font-mono font-semibold px-1.5 py-0.5 rounded uppercase border ${
                        gw.status === "healthy"
                          ? "bg-emerald-500/10 text-emerald-400 border-emerald-500/20"
                          : "bg-amber-500/10 text-amber-400 border-amber-500/20"
                      }`}>
                        {gw.status}
                      </span>
                    </div>
                    <span className="text-[10px] font-mono text-slate-500 uppercase">Provider Code: {gw.provider}</span>
                  </div>

                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => updateGatewayMode(gw.id, gw.mode === "sandbox" ? "production" : "sandbox")}
                      className={`px-2 py-0.5 rounded text-[9px] font-mono font-bold uppercase border ${
                        gw.mode === "production"
                          ? "bg-rose-500/15 text-rose-400 border-rose-500/20"
                          : "bg-blue-500/15 text-blue-400 border-blue-500/20"
                      }`}
                    >
                      {gw.mode}
                    </button>
                    <button
                      onClick={() => toggleGateway(gw.id)}
                      className={`px-3 py-1 rounded text-xs font-bold transition-all uppercase ${
                        gw.is_active
                          ? "bg-emerald-600 hover:bg-emerald-500 text-white"
                          : "bg-slate-800 hover:bg-slate-700 text-slate-400"
                      }`}
                    >
                      {gw.is_active ? "Enabled" : "Disabled"}
                    </button>
                  </div>
                </div>

                {/* Gateway config panels */}
                <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-slate-800/60 text-xs">
                  <div className="space-y-2">
                    <div className="space-y-1">
                      <label className="text-[9px] font-mono text-slate-400 uppercase">Gateway API Key / Client ID</label>
                      <input
                        type="password"
                        value={gw.key}
                        onChange={(e) => updateGatewaySettings(gw.id, "key", e.target.value)}
                        className="w-full bg-slate-950 border border-slate-800 rounded px-2.5 py-1 text-slate-300 font-mono text-[11px] focus:outline-none focus:border-indigo-500"
                      />
                    </div>
                    <div className="space-y-1">
                      <label className="text-[9px] font-mono text-slate-400 uppercase">Gateway API Secret / Passkey</label>
                      <input
                        type="password"
                        value={gw.secret}
                        onChange={(e) => updateGatewaySettings(gw.id, "secret", e.target.value)}
                        className="w-full bg-slate-950 border border-slate-800 rounded px-2.5 py-1 text-slate-300 font-mono text-[11px] focus:outline-none focus:border-indigo-500"
                      />
                    </div>
                  </div>

                  <div className="space-y-3 font-mono text-[10.5px]">
                    <div className="flex justify-between">
                      <span className="text-slate-500">Currencies:</span>
                      <span className="text-slate-300 font-bold">{gw.currencies.join(", ")}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Countries:</span>
                      <span className="text-slate-300 font-bold">{gw.countries.join(", ")}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <span className="text-slate-500">Gateway Priority:</span>
                      <div className="flex items-center gap-1">
                        <button
                          onClick={() => updateGatewaySettings(gw.id, "priority", Math.max(1, gw.priority - 1))}
                          className="bg-slate-800 px-1 hover:bg-slate-700 text-slate-200 text-[9px]"
                        >
                          -
                        </button>
                        <span className="text-indigo-400 font-bold px-1">{gw.priority}</span>
                        <button
                          onClick={() => updateGatewaySettings(gw.id, "priority", gw.priority + 1)}
                          className="bg-slate-800 px-1 hover:bg-slate-700 text-slate-200 text-[9px]"
                        >
                          +
                        </button>
                      </div>
                    </div>
                    <div className="flex justify-between items-center">
                      <span className="text-slate-500">Callback Webhook:</span>
                      <span className="text-[9px] text-slate-400 bg-slate-950 px-1.5 py-0.5 rounded overflow-hidden max-w-[150px] truncate">
                        /api/webhooks/{gw.provider}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* SUB TAB 2: INTELLIGENT ROUTING */}
      {activeSubTab === "routing" && (
        <div className="space-y-6">
          <div className="bg-slate-900/40 p-6 rounded-xl border border-slate-800 space-y-4">
            <h3 className="text-sm font-bold text-slate-200">Dynamic Gateway Payment Routing Engine</h3>
            <p className="text-xs text-slate-400">
              JUANET uses an intelligent routing processor. When an invoice payment is triggered, the engine evaluates Currency, Country constraints, Gateway Priorities, and active Health parameters to automatically dispatch transactions to the optimal provider.
            </p>

            {/* Parameter pickers */}
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-slate-950 rounded-xl border border-slate-800 font-mono text-xs">
              <div className="space-y-1">
                <label className="text-[10px] text-slate-400 block uppercase">Target Currency</label>
                <select
                  value={routeCurrency}
                  onChange={(e) => setRouteCurrency(e.target.value)}
                  className="w-full bg-slate-900 border border-slate-800 rounded px-2.5 py-1.5 text-slate-200 focus:outline-none focus:border-indigo-500"
                >
                  <option value="KES">KES (Kenyan Shilling)</option>
                  <option value="USD">USD (US Dollar)</option>
                  <option value="NGN">NGN (Nigerian Naira)</option>
                  <option value="EUR">EUR (Euro)</option>
                  <option value="GBP">GBP (British Pound)</option>
                </select>
              </div>

              <div className="space-y-1">
                <label className="text-[10px] text-slate-400 block uppercase">Subscriber Country</label>
                <select
                  value={routeCountry}
                  onChange={(e) => setRouteCountry(e.target.value)}
                  className="w-full bg-slate-900 border border-slate-800 rounded px-2.5 py-1.5 text-slate-200 focus:outline-none focus:border-indigo-500"
                >
                  <option value="KE">Kenya</option>
                  <option value="US">United States</option>
                  <option value="NG">Nigeria</option>
                  <option value="GB">United Kingdom</option>
                  <option value="ZA">South Africa</option>
                </select>
              </div>

              <div className="flex items-end">
                <span className="text-[10.5px] text-slate-400 italic">
                  * Live dynamic compilation triggers as parameters modify.
                </span>
              </div>
            </div>
          </div>

          {/* Routing Results */}
          <div className="space-y-3">
            <h4 className="text-xs font-bold text-slate-400 uppercase font-mono tracking-wider">Gateway Priority Scoring Queue</h4>
            <div className="space-y-2">
              {routeResults.map((gw, idx) => (
                <div
                  key={gw.id}
                  className={`p-4 rounded-lg border flex flex-col md:flex-row justify-between items-start md:items-center gap-3 transition-all ${
                    idx === 0 && gw.isCompatible
                      ? "bg-indigo-950/20 border-indigo-500/45 shadow-[0_0_15px_rgba(99,102,241,0.08)]"
                      : gw.isCompatible 
                        ? "bg-slate-900/40 border-slate-800"
                        : "bg-slate-950/40 border-slate-900/60 opacity-40"
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <span className="font-mono text-xs text-slate-500">#{idx + 1}</span>
                    <div>
                      <div className="flex items-center gap-2">
                        <h4 className="text-sm font-bold text-slate-200">{gw.name}</h4>
                        {idx === 0 && gw.isCompatible && (
                          <span className="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[9px] px-1.5 py-0.5 rounded">
                            Optimal Route
                          </span>
                        )}
                      </div>
                      <span className="text-[10px] font-mono text-slate-500">
                        Currencies: {gw.currencies.join(", ")} | Countries: {gw.countries.join(", ")} | Configured Priority: {gw.priority}
                      </span>
                    </div>
                  </div>

                  <div className="flex items-center gap-6 font-mono text-xs shrink-0 w-full md:w-auto justify-between md:justify-end">
                    <div className="text-right">
                      <span className="text-slate-500 text-[10px] block">MATCH INDEX:</span>
                      <span className={`font-bold ${idx === 0 && gw.isCompatible ? "text-indigo-400" : "text-slate-300"}`}>
                        {gw.matchScore}%
                      </span>
                    </div>

                    <div className="text-right">
                      <span className="text-slate-500 text-[10px] block">COMPATIBLE:</span>
                      <span className={gw.isCompatible ? "text-emerald-400" : "text-rose-400"}>
                        {gw.isCompatible ? "YES" : "NO"}
                      </span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* SUB TAB 3: CHECKOUT SIMULATOR */}
      {activeSubTab === "checkout" && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 relative">
            {/* Form Column */}
            <div className="bg-slate-900/20 p-6 rounded-xl border border-slate-800 space-y-4">
              <h3 className="text-sm font-bold text-slate-200">Interactive Store Checkout & normalization</h3>
              <p className="text-xs text-slate-400">
                Trigger a checkout simulation. The engine will route the payment via the adapter selected above and normalise the results into the **Unified Payment Model**.
              </p>

              <form onSubmit={handleCheckoutSubmit} className="space-y-4 text-xs font-mono">
                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1">
                    <label className="text-[10px] text-slate-400 block uppercase">Invoice ID</label>
                    <input
                      type="text"
                      value={checkoutInvoiceId}
                      onChange={(e) => setCheckoutInvoiceId(e.target.value)}
                      className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-slate-200 focus:outline-none focus:border-indigo-500"
                    />
                  </div>
                  <div className="space-y-1">
                    <label className="text-[10px] text-slate-400 block uppercase">Amount ({routeCurrency})</label>
                    <input
                      type="number"
                      value={checkoutAmount}
                      onChange={(e) => setCheckoutAmount(e.target.value)}
                      className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-slate-200 focus:outline-none focus:border-indigo-500"
                    />
                  </div>
                </div>

                <div className="space-y-1">
                  <label className="text-[10px] text-slate-400 block uppercase">Payment Method Category</label>
                  <select
                    value={checkoutMethod}
                    onChange={(e) => setCheckoutMethod(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-slate-200 focus:outline-none focus:border-indigo-500"
                  >
                    <option value="mpesa_stk">M-PESA STK Push (Mobile Money)</option>
                    <option value="mpesa_till">M-PESA Till (Lipa Na M-PESA Merchant)</option>
                    <option value="mpesa_paybill">M-PESA Paybill (Safaricom Billing)</option>
                    <option value="card_visa">Visa Corporate Credit Card</option>
                    <option value="card_mastercard">Mastercard SecureCode Debit</option>
                    <option value="paypal">PayPal Express Checkout</option>
                    <option value="bank_transfer">Direct Electronic Fund Bank Transfer</option>
                  </select>
                </div>

                {checkoutMethod.startsWith("mpesa") ? (
                  <div className="space-y-1">
                    <label className="text-[10px] text-slate-400 block uppercase">Subscriber Phone Number</label>
                    <input
                      type="text"
                      value={payerPhone}
                      onChange={(e) => setPayerPhone(e.target.value)}
                      className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-slate-200 focus:outline-none focus:border-indigo-500"
                    />
                  </div>
                ) : (
                  <div className="space-y-1">
                    <label className="text-[10px] text-slate-400 block uppercase">Billing Email Address</label>
                    <input
                      type="email"
                      value={payerEmail}
                      onChange={(e) => setPayerEmail(e.target.value)}
                      className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-slate-200 focus:outline-none focus:border-indigo-500"
                    />
                  </div>
                )}

                <button
                  type="submit"
                  disabled={checkoutProcessing}
                  className="w-full bg-indigo-600 hover:bg-indigo-500 py-3 rounded-lg text-xs font-bold font-display uppercase tracking-wider text-white disabled:opacity-40 transition-colors flex items-center justify-center gap-1.5"
                >
                  {checkoutProcessing ? <RefreshCw className="animate-spin" size={14} /> : <Play size={14} />}
                  <span>Initialize Integrated Checkout</span>
                </button>
              </form>
            </div>

            {/* Results Column */}
            <div className="space-y-4">
              <div className="bg-slate-900/40 border border-slate-800 rounded-xl p-4 font-mono text-[11px] h-48 overflow-y-auto space-y-1.5">
                <span className="text-slate-500 uppercase block tracking-wider font-bold mb-1">Gateway Execution Console</span>
                {simulationLogs.length === 0 ? (
                  <span className="text-slate-600 italic">Waiting for transaction initiation...</span>
                ) : (
                  simulationLogs.map((log, lIdx) => (
                    <div key={lIdx} className="text-indigo-300">{log}</div>
                  ))
                )}
              </div>

              {normalizedModel ? (
                <div className="bg-slate-900/60 border border-emerald-500/20 rounded-xl p-5 space-y-4">
                  <div className="flex justify-between items-center border-b border-slate-800 pb-3">
                    <div className="flex items-center gap-2">
                      <CheckCircle2 size={16} className="text-emerald-400" />
                      <span className="text-xs font-bold text-slate-200 uppercase font-mono">Unified Model Output Normalized</span>
                    </div>
                    <span className="text-[10px] font-mono text-indigo-400 bg-indigo-500/10 px-2 py-0.5 rounded border border-indigo-500/25">
                      {normalizedModel.referenceNumber}
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-x-4 gap-y-2 text-xs font-mono">
                    <div className="flex justify-between">
                      <span className="text-slate-500">Payment ID:</span>
                      <span className="text-slate-300 font-bold">{normalizedModel.paymentId}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Routed Gateway:</span>
                      <span className="text-slate-300 font-bold">{normalizedModel.gateway}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Provider Ref:</span>
                      <span className="text-indigo-300 font-bold">{normalizedModel.providerTransactionId}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Currency:</span>
                      <span className="text-slate-300 font-bold">{normalizedModel.currency}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Gross Amount:</span>
                      <span className="text-slate-300 font-bold">{normalizedModel.amount.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Processor Fees:</span>
                      <span className="text-rose-400 font-bold">{normalizedModel.fees.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Net Amount:</span>
                      <span className="text-emerald-400 font-bold">{normalizedModel.netAmount.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Status:</span>
                      <span className="text-emerald-400 font-bold uppercase">{normalizedModel.status}</span>
                    </div>
                  </div>

                  <div className="pt-3 border-t border-slate-800">
                    <span className="text-[9px] font-mono text-slate-500 block uppercase mb-1">Callback Metadata (`callback_payload`)</span>
                    <pre className="bg-slate-950 p-3 rounded font-mono text-[9.5px] text-indigo-400 max-h-32 overflow-y-auto">
                      {JSON.stringify(normalizedModel.callbackPayload, null, 2)}
                    </pre>
                  </div>
                </div>
              ) : (
                <div className="bg-slate-950/40 rounded-xl border border-slate-900 border-dashed text-center py-16 text-slate-600 text-xs font-mono">
                  No active transactions finalized. Submit checkout parameters to witness the normalization adapter flow.
                </div>
              )}
            </div>
          </div>

          {/* SIMULATOR SCREEN POPUPS */}
          {stkPromptVisible && (
            <div className="fixed inset-0 bg-slate-950/85 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="w-full max-w-xs bg-slate-900 border-2 border-emerald-500 rounded-3xl p-6 shadow-2xl relative space-y-4">
                <div className="absolute top-2.5 left-1/2 -translate-x-1/2 w-16 h-4 rounded-full bg-slate-950" />
                <div className="text-center pt-4">
                  <span className="text-[10px] font-mono text-emerald-500 font-bold block uppercase tracking-widest">Safaricom Sim-tool kit</span>
                  <h4 className="text-xs text-slate-300 mt-2 font-sans font-medium px-2">
                    Enter M-PESA PIN to pay JUANET SOLUTIONS KES {Number(checkoutAmount).toLocaleString()} for {checkoutInvoiceId}:
                  </h4>
                </div>
                <input
                  type="password"
                  maxLength={4}
                  value={pinCode}
                  onChange={(e) => setPinCode(e.target.value.replace(/\D/g, ""))}
                  placeholder="PIN"
                  className="w-32 mx-auto text-center bg-slate-950 border border-slate-800 tracking-widest font-extrabold focus:outline-none focus:border-emerald-500 text-slate-200 font-mono text-base py-1.5 rounded-lg block"
                />
                <div className="grid grid-cols-2 gap-2 text-xs font-semibold pt-2 font-mono">
                  <button
                    onClick={() => { setStkPromptVisible(false); setPinCode(""); setCheckoutProcessing(false); }}
                    className="py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl transition-colors uppercase text-[10px] tracking-wide"
                  >
                    Cancel
                  </button>
                  <button
                    onClick={() => {
                      const activeGW = routeResults.find(r => r.isCompatible && r.is_active);
                      finalizeCheckout(activeGW);
                    }}
                    disabled={pinCode.length < 4}
                    className="py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl transition-colors uppercase text-[10px] tracking-wide disabled:opacity-40"
                  >
                    Send
                  </button>
                </div>
              </div>
            </div>
          )}

          {cardSecure3DVisible && (
            <div className="fixed inset-0 bg-slate-950/85 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
                <div className="flex justify-between items-center border-b border-slate-800 pb-3">
                  <h4 className="text-sm font-bold text-slate-100 flex items-center gap-2">
                    <Lock size={16} className="text-indigo-400" />
                    Verified by Visa / Mastercard ID Check
                  </h4>
                  <span className="text-[10px] font-mono text-slate-500">3D-SECURE v2.2</span>
                </div>
                <p className="text-xs text-slate-400">
                  A verification request has been dispatched to secure card accounts. Enter authorization code sent to registered communications to settle transaction:
                </p>

                <div className="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2 text-xs font-mono">
                  <div className="flex justify-between">
                    <span className="text-slate-500">Merchant:</span>
                    <span className="text-slate-300">JUANET ENTERPRISE GATEWAY</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">Amount:</span>
                    <span className="text-slate-300">{routeCurrency} {Number(checkoutAmount).toLocaleString()}</span>
                  </div>
                </div>

                <input
                  type="text"
                  maxLength={6}
                  value={pinCode}
                  onChange={(e) => setPinCode(e.target.value.replace(/\D/g, ""))}
                  placeholder="Enter 6-Digit Code (Simulate 123456)"
                  className="w-full bg-slate-950 border border-slate-800 text-center tracking-widest font-extrabold focus:outline-none focus:border-indigo-500 text-slate-200 font-mono text-base py-2.5 rounded-lg block"
                />

                <div className="grid grid-cols-2 gap-2 text-xs font-semibold pt-2 font-mono">
                  <button
                    onClick={() => { setCardSecure3DVisible(false); setPinCode(""); setCheckoutProcessing(false); }}
                    className="py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors uppercase text-[10px] tracking-wide"
                  >
                    Decline
                  </button>
                  <button
                    onClick={() => {
                      const activeGW = routeResults.find(r => r.isCompatible && r.is_active);
                      finalizeCheckout(activeGW);
                    }}
                    disabled={pinCode.length < 4}
                    className="py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors uppercase text-[10px] tracking-wide disabled:opacity-40"
                  >
                    Authenticate
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* SUB TAB 4: ARCHITECTURE BLUEPRINTS */}
      {activeSubTab === "blueprints" && (
        <div className="space-y-6">
          {/* Diagrams */}
          <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
            {/* Layered Architecture Diagram */}
            <div className="bg-slate-900/40 p-6 rounded-xl border border-slate-800 space-y-4">
              <h3 className="text-sm font-bold text-indigo-400 font-mono uppercase tracking-wider">1. Layered Payment Architecture Diagram</h3>
              <p className="text-xs text-slate-400">
                Normalized Client requests flow vertically through abstract layers, preventing direct business logic exposure to external gateway protocols.
              </p>

              <div className="space-y-2 font-mono text-xs pt-4">
                <div className="bg-slate-950 p-3 rounded-lg border border-slate-800 text-center text-slate-200">
                  <span className="text-[10px] text-slate-500 block">CLIENT VISIBILITY LAYER</span>
                  <span className="font-bold">Client Checkout Portal</span>
                </div>
                <div className="text-center text-indigo-400 py-1 font-bold">↓</div>
                <div className="bg-slate-950 p-3 rounded-lg border border-slate-800 text-center text-slate-200">
                  <span className="text-[10px] text-slate-500 block">STORE FRONT LAYER</span>
                  <span className="font-bold">Invoice / Checkout Service</span>
                </div>
                <div className="text-center text-indigo-400 py-1 font-bold">↓</div>
                <div className="bg-slate-950 p-3 rounded-lg border border-slate-800 text-center text-slate-200">
                  <span className="text-[10px] text-slate-500 block">API SERVICE CONTAINER</span>
                  <span className="font-bold">Payment Service Framework</span>
                </div>
                <div className="text-center text-indigo-400 py-1 font-bold">↓</div>
                <div className="bg-indigo-950/20 p-3 rounded-lg border border-indigo-500/30 text-center text-indigo-200">
                  <span className="text-[10px] text-indigo-400 block">CENTRAL CONTROL GATEWAY</span>
                  <span className="font-bold">Payment Gateway Manager</span>
                </div>
                <div className="text-center text-indigo-400 py-1 font-bold">↓</div>
                <div className="bg-slate-950 p-3 rounded-lg border border-slate-800 text-center text-slate-200">
                  <span className="text-[10px] text-slate-500 block">ADAPTER PROTOCOL INTERFACE</span>
                  <span className="font-bold">Gateway Provider Adapter Interface</span>
                </div>
                <div className="text-center text-indigo-400 py-1 font-bold">↓</div>
                <div className="grid grid-cols-3 gap-2">
                  <div className="bg-slate-900 p-2 rounded border border-slate-800 text-center text-[11px] text-slate-300">Daraja Adapter</div>
                  <div className="bg-slate-900 p-2 rounded border border-slate-800 text-center text-[11px] text-slate-300">Stripe Adapter</div>
                  <div className="bg-slate-900 p-2 rounded border border-slate-800 text-center text-[11px] text-slate-300">Paystack Adapter</div>
                </div>
                <div className="text-center text-slate-500 py-1">↓</div>
                <div className="bg-slate-950 p-3 rounded-lg border border-dashed border-slate-800 text-center text-slate-500">
                  <span className="text-[10px] text-slate-600 block">EXTERNAL API ENDPOINTS</span>
                  <span className="font-bold">Third-Party Gateway APIs (Safaricom, Stripe, etc.)</span>
                </div>
              </div>
            </div>

            {/* Workflow Diagram */}
            <div className="bg-slate-900/40 p-6 rounded-xl border border-slate-800 space-y-4">
              <h3 className="text-sm font-bold text-indigo-400 font-mono uppercase tracking-wider">2. Payment Lifecycle Workflow Diagram</h3>
              <p className="text-xs text-slate-400">
                Tracing chronological state transitions when invoice triggers payments and propagates ledger balances.
              </p>

              <div className="grid grid-cols-1 gap-2.5 font-mono text-[11px] pt-4">
                <div className="p-2.5 rounded bg-slate-950 border border-slate-800 flex justify-between items-center">
                  <span>1. Invoice Issued</span>
                  <span className="text-indigo-400">Status: Unpaid</span>
                </div>
                <div className="p-2.5 rounded bg-slate-950 border border-slate-800 flex justify-between items-center">
                  <span>2. Payment Initiated via Gateway Manager</span>
                  <span className="text-indigo-400">Creates Payment Intent</span>
                </div>
                <div className="p-2.5 rounded bg-slate-950 border border-slate-800 flex justify-between items-center">
                  <span>3. Dynamic Adapter Dispatch</span>
                  <span className="text-indigo-400">Calls Initialize Payment()</span>
                </div>
                <div className="p-2.5 rounded bg-slate-950 border border-slate-800 flex justify-between items-center">
                  <span>4. Subscriber Clears Billing</span>
                  <span className="text-indigo-400">Authenticates credentials</span>
                </div>
                <div className="p-2.5 rounded bg-slate-950 border border-slate-800 flex justify-between items-center">
                  <span>5. Asymmetric Webhook callback</span>
                  <span className="text-indigo-400">Verifies Signature validation</span>
                </div>
                <div className="p-2.5 rounded bg-emerald-950/20 border border-emerald-500/20 flex justify-between items-center text-emerald-300">
                  <span>6. Payment normalization receipt stored</span>
                  <span>Ledger Post & invoice status: Paid</span>
                </div>
                <div className="p-2.5 rounded bg-emerald-950/20 border border-emerald-500/20 flex justify-between items-center text-emerald-300">
                  <span>7. Automatic Workflow Trigger executed</span>
                  <span>SMTP Dispatch, Project active</span>
                </div>
              </div>
            </div>
          </div>

          {/* Adapter Specification Details */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="p-6 rounded-xl border border-slate-800 bg-slate-900/20 space-y-4">
              <h4 className="text-sm font-bold text-slate-200">Gateway Provider Adapter Interface</h4>
              <p className="text-xs text-slate-400">
                To guarantee plug-and-play scalability, every external adapter (Stripe, PayHero, etc.) must adhere to the exact same contract parameters:
              </p>

              <div className="font-mono text-[11px] space-y-2">
                <div className="bg-slate-950 p-2.5 rounded border border-slate-800 flex justify-between">
                  <span className="text-indigo-300">Initialize Payment</span>
                  <span className="text-slate-500">Prepares token session</span>
                </div>
                <div className="bg-slate-950 p-2.5 rounded border border-slate-800 flex justify-between">
                  <span className="text-indigo-300">Verify Payment</span>
                  <span className="text-slate-500">Active status check</span>
                </div>
                <div className="bg-slate-950 p-2.5 rounded border border-slate-800 flex justify-between">
                  <span className="text-indigo-300">Receive Callback</span>
                  <span className="text-slate-500">Webhook return ingestion</span>
                </div>
                <div className="bg-slate-950 p-2.5 rounded border border-slate-800 flex justify-between">
                  <span className="text-indigo-300">Cancel Payment</span>
                  <span className="text-slate-500">Aborts active intents</span>
                </div>
                <div className="bg-slate-950 p-2.5 rounded border border-slate-800 flex justify-between">
                  <span className="text-indigo-300">Refund Payment</span>
                  <span className="text-slate-500">Initiates balance reversal</span>
                </div>
                <div className="bg-slate-950 p-2.5 rounded border border-slate-800 flex justify-between">
                  <span className="text-indigo-300">Generate Payment Link</span>
                  <span className="text-slate-500">Returns remote URL</span>
                </div>
              </div>
            </div>

            <div className="p-6 rounded-xl border border-slate-800 bg-slate-900/20 space-y-4">
              <h4 className="text-sm font-bold text-slate-200">Security Architecture Specifications</h4>
              <p className="text-xs text-slate-400">
                JUANET enforces strict encryption and validation filters at the edge before releasing financial clearance webhooks:
              </p>

              <div className="space-y-3 text-xs">
                <div className="flex gap-2">
                  <div className="bg-indigo-500/10 text-indigo-400 font-mono text-[10px] h-fit px-1.5 py-0.5 rounded border border-indigo-500/20 font-bold uppercase">
                    Signature
                  </div>
                  <p className="text-slate-400 text-[11px]">
                    <strong>Webhook Signature Validation:</strong> Generates HMAC SHA256 checksums matching provider headers using the stored webhook secret to secure endpoints from fake callback triggers.
                  </p>
                </div>
                <div className="flex gap-2">
                  <div className="bg-indigo-500/10 text-indigo-400 font-mono text-[10px] h-fit px-1.5 py-0.5 rounded border border-indigo-500/20 font-bold uppercase">
                    Replay
                  </div>
                  <p className="text-slate-400 text-[11px]">
                    <strong>Replay Attack Protection:</strong> Validates webhook timestamps, throwing errors if requests differ by more than 5 minutes from system times.
                  </p>
                </div>
                <div className="flex gap-2">
                  <div className="bg-indigo-500/10 text-indigo-400 font-mono text-[10px] h-fit px-1.5 py-0.5 rounded border border-indigo-500/20 font-bold uppercase">
                    Idempotent
                  </div>
                  <p className="text-slate-400 text-[11px]">
                    <strong>Idempotency Keys:</strong> Webhooks must register and check incoming transactional keys, preventing accidental multiple ledger postings for a single payment.
                  </p>
                </div>
                <div className="flex gap-2">
                  <div className="bg-indigo-500/10 text-indigo-400 font-mono text-[10px] h-fit px-1.5 py-0.5 rounded border border-indigo-500/20 font-bold uppercase">
                    PCI-DSS
                  </div>
                  <p className="text-slate-400 text-[11px]">
                    <strong>PCI-Friendly Architecture:</strong> Card credentials are never saved on JUANET servers. Transactions rely entirely on hosted payment fields, returning secure transient card tokens.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// 8. Blog CMS Tab Component
function BlogTab({ 
  comments, 
  setComments,
  blogPosts,
  setBlogPosts
}: { 
  comments: any[]; 
  setComments: React.Dispatch<React.SetStateAction<any[]>>;
  blogPosts: any[];
  setBlogPosts: React.Dispatch<React.SetStateAction<any[]>>;
}) {
  const [commentName, setCommentName] = useState("");
  const [commentText, setCommentText] = useState("");
  const [selectedPostId, setSelectedPostId] = useState("post-1");
  const [activeSubTab, setActiveSubTab] = useState<"reader" | "cms">("reader");

  // Form State for creating/editing posts
  const [editingPostId, setEditingPostId] = useState<string | null>(null);
  const [formTitle, setFormTitle] = useState("");
  const [formSlug, setFormSlug] = useState("");
  const [formCategory, setFormCategory] = useState("Cloud Engineering");
  const [formAuthor, setFormAuthor] = useState("Juan");
  const [formExcerpt, setFormExcerpt] = useState("");
  const [formContent, setFormContent] = useState("");
  const [formStatus, setFormStatus] = useState<"draft" | "published">("published");
  const [formMetaDesc, setFormMetaDesc] = useState("");
  const [formKeyword, setFormKeyword] = useState("");

  const [notification, setNotification] = useState<{ type: "success" | "error"; text: string } | null>(null);

  const showNotification = (type: "success" | "error", text: string) => {
    setNotification({ type, text });
    setTimeout(() => setNotification(null), 4000);
  };

  // Auto slug generation helper
  const handleTitleChange = (val: string) => {
    setFormTitle(val);
    if (!editingPostId) {
      const slugified = val
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, "")
        .replace(/\s+/g, "-")
        .replace(/-+/g, "-")
        .trim();
      setFormSlug(slugified);
    }
  };

  const loadPostForEditing = (post: any) => {
    setEditingPostId(post.id);
    setFormTitle(post.title);
    setFormSlug(post.slug);
    setFormCategory(post.category);
    setFormAuthor(post.author);
    setFormExcerpt(post.excerpt);
    setFormContent(post.content || "");
    setFormStatus(post.status || "published");
    setFormMetaDesc(post.metaDescription || "");
    setFormKeyword(post.targetKeyword || "");
    showNotification("success", `Loaded "${post.title}" into writer canvas.`);
  };

  const clearForm = () => {
    setEditingPostId(null);
    setFormTitle("");
    setFormSlug("");
    setFormCategory("Cloud Engineering");
    setFormAuthor("Juan");
    setFormExcerpt("");
    setFormContent("");
    setFormStatus("published");
    setFormMetaDesc("");
    setFormKeyword("");
  };

  const handleSavePost = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formTitle.trim() || !formSlug.trim()) {
      showNotification("error", "Title and slug are required fields.");
      return;
    }

    const postPayload = {
      id: editingPostId || `post-${Date.now()}`,
      title: formTitle,
      slug: formSlug,
      category: formCategory,
      author: formAuthor,
      date: new Date().toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" }),
      excerpt: formExcerpt,
      content: formContent,
      status: formStatus,
      metaDescription: formMetaDesc,
      targetKeyword: formKeyword
    };

    if (editingPostId) {
      // Edit existing post
      setBlogPosts(prev => prev.map(p => p.id === editingPostId ? postPayload : p));
      showNotification("success", "Blog post successfully updated!");
    } else {
      // Create new post
      setBlogPosts(prev => [postPayload, ...prev]);
      setSelectedPostId(postPayload.id); // Auto-select the newly created post
      showNotification("success", "New blog post published successfully!");
    }

    clearForm();
    setActiveSubTab("reader");
  };

  const handleDeletePost = (id: string, title: string) => {
    if (confirm(`Are you sure you want to delete "${title}"?`)) {
      setBlogPosts(prev => prev.filter(p => p.id !== id));
      showNotification("success", "Blog post permanently deleted.");
      if (selectedPostId === id) {
        setSelectedPostId("post-1");
      }
    }
  };

  const togglePostStatus = (id: string) => {
    setBlogPosts(prev =>
      prev.map(p => {
        if (p.id === id) {
          const newStatus = p.status === "draft" ? "published" : "draft";
          showNotification("success", `Post status updated to ${newStatus}.`);
          return { ...p, status: newStatus };
        }
        return p;
      })
    );
  };

  // SEO Score & Checklist Calculation on the fly
  const calculateSEO = () => {
    const checks = {
      titleKeyword: false,
      slugKeyword: false,
      titleLength: false,
      metaLength: false,
      bodyKeyword: false,
      wordCount: false
    };

    let score = 0;
    const kw = formKeyword.trim().toLowerCase();

    // Word Count
    const words = formContent.trim().split(/\s+/).filter(Boolean).length;
    if (words >= 100) {
      checks.wordCount = true;
      score += 15;
    }

    // Title Length (30-70 chars is standard optimal range)
    if (formTitle.length >= 25 && formTitle.length <= 75) {
      checks.titleLength = true;
      score += 15;
    }

    // Meta Description Length (50-160 chars)
    if (formMetaDesc.length >= 45 && formMetaDesc.length <= 165) {
      checks.metaLength = true;
      score += 15;
    }

    if (kw) {
      // Title Keyword Check
      if (formTitle.toLowerCase().includes(kw)) {
        checks.titleKeyword = true;
        score += 20;
      }

      // Slug Keyword Check
      const slugifiedKw = kw.replace(/[^a-z0-9]/g, "-").replace(/-+/g, "-");
      if (formSlug.toLowerCase().includes(slugifiedKw) || formSlug.toLowerCase().includes(kw.replace(/\s+/g, "-"))) {
        checks.slugKeyword = true;
        score += 15;
      }

      // Body Keyword Check
      if (formContent.toLowerCase().includes(kw)) {
        checks.bodyKeyword = true;
        score += 20;
      }
    }

    return { score, checks, wordCount: words };
  };

  const { score: seoScore, checks: seoChecks, wordCount } = calculateSEO();

  const submitComment = (e: React.FormEvent) => {
    e.preventDefault();
    if (!commentName.trim() || !commentText.trim()) return;
    const newComment = {
      id: `C-${Math.floor(100 + Math.random() * 900)}`,
      postId: selectedPostId,
      author: commentName,
      text: commentText,
      date: "Just now"
    };
    setComments([...comments, newComment]);
    setCommentName("");
    setCommentText("");
    showNotification("success", "Comment successfully posted to secure blog timeline.");
  };

  const activeComments = comments.filter(c => c.postId === selectedPostId);
  const activePost = blogPosts.find(p => p.id === selectedPostId) || blogPosts[0];

  return (
    <div className="space-y-6">
      {/* Tab Header and SubTab Switcher */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-900 pb-4 shrink-0">
        <div>
          <h3 className="text-xl font-display font-bold text-white flex items-center gap-2">
            <BookOpen size={22} className="text-indigo-400" />
            JUANET Marketing & Headless SEO CMS System
          </h3>
          <p className="text-xs text-slate-400">
            Write technical articles, inspect real-time search engine optimization, and curate community feedback instantly.
          </p>
        </div>

        {/* Navigation Toggles */}
        <div className="flex items-center bg-slate-950 p-1 rounded-lg border border-slate-800/80 self-start md:self-auto shrink-0">
          <button
            onClick={() => setActiveSubTab("reader")}
            className={`px-3 py-1.5 rounded-md text-xs font-semibold font-mono uppercase transition-all flex items-center gap-1.5 ${
              activeSubTab === "reader"
                ? "bg-indigo-600 text-white shadow-sm"
                : "text-slate-400 hover:text-slate-200"
            }`}
          >
            <Eye size={13} />
            Blog Preview
          </button>
          <button
            onClick={() => {
              setActiveSubTab("cms");
              clearForm();
            }}
            className={`px-3 py-1.5 rounded-md text-xs font-semibold font-mono uppercase transition-all flex items-center gap-1.5 ${
              activeSubTab === "cms"
                ? "bg-indigo-600 text-white shadow-sm"
                : "text-slate-400 hover:text-slate-200"
            }`}
          >
            <Settings size={13} />
            CMS Admin Workspace
          </button>
        </div>
      </div>

      {/* Floating Alert Notifications */}
      <AnimatePresence>
        {notification && (
          <motion.div
            initial={{ opacity: 0, y: -15, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -15, scale: 0.95 }}
            className={`fixed top-4 right-4 z-50 p-3.5 rounded-xl border shadow-lg text-xs font-semibold flex items-center gap-2.5 max-w-sm ${
              notification.type === "success"
                ? "bg-emerald-950/90 border-emerald-500/30 text-emerald-300"
                : "bg-rose-950/90 border-rose-500/30 text-rose-300"
            }`}
          >
            <CheckCircle2 size={16} className={notification.type === "success" ? "text-emerald-400" : "text-rose-400"} />
            <span>{notification.text}</span>
          </motion.div>
        )}
      </AnimatePresence>

      {/* SUB TAB 1: Blog Frontend Preview */}
      {activeSubTab === "reader" && (
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 min-h-[550px]">
          {/* Left Column - Articles Selector Side Panel */}
          <div className="lg:col-span-1 bg-slate-900/10 border border-slate-800 rounded-xl p-4 flex flex-col space-y-3.5 h-[550px] overflow-hidden">
            <span className="text-[9px] font-mono font-bold text-slate-500 uppercase tracking-widest block px-1">
              Select Article
            </span>

            <div className="flex-1 overflow-y-auto space-y-1.5 pr-1">
              {blogPosts.length === 0 ? (
                <div className="text-center text-xs text-slate-600 py-10 font-mono">
                  No articles available. Create one in the CMS panel!
                </div>
              ) : (
                blogPosts.map(post => {
                  const isSelected = selectedPostId === post.id;
                  const isDraft = post.status === "draft";
                  return (
                    <button
                      key={post.id}
                      onClick={() => setSelectedPostId(post.id)}
                      className={`w-full text-left p-3 rounded-lg transition-all border flex flex-col items-start gap-1 ${
                        isSelected
                          ? "bg-indigo-600/15 border-indigo-500/30 text-indigo-300"
                          : "bg-transparent border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-900/40"
                      }`}
                    >
                      <div className="flex items-center gap-1.5 w-full">
                        <span className="bg-slate-900 text-indigo-400 px-1.5 py-0.5 rounded text-[8px] font-mono border border-slate-800 uppercase font-bold shrink-0">
                          {post.category}
                        </span>
                        {isDraft && (
                          <span className="bg-amber-500/10 text-amber-400 border border-amber-500/20 px-1 rounded text-[8px] font-mono font-bold uppercase shrink-0">
                            Draft
                          </span>
                        )}
                      </div>
                      <span className="text-xs font-bold font-sans line-clamp-2 mt-1 leading-snug">
                        {post.title}
                      </span>
                      <span className="text-[9px] font-mono text-slate-500 uppercase mt-1">
                        {post.date}
                      </span>
                    </button>
                  );
                })
              )}
            </div>
          </div>

          {/* Center & Right Column - Article Body & Comments Viewer */}
          <div className="lg:col-span-3 grid grid-cols-1 xl:grid-cols-3 gap-6 h-[550px]">
            {/* Main Article Content Panel (2 cols) */}
            <div className="xl:col-span-2 bg-slate-950 border border-slate-800 rounded-xl flex flex-col h-full overflow-hidden">
              {activePost ? (
                <div className="flex-1 overflow-y-auto p-6 md:p-8 space-y-4 select-text">
                  <div>
                    <span className="bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-2.5 py-0.5 rounded text-[10px] font-mono font-bold uppercase">
                      {activePost.category}
                    </span>
                    <h4 className="text-xl font-bold font-display text-white mt-2.5 leading-snug">
                      {activePost.title}
                    </h4>
                    <div className="flex items-center gap-2.5 text-[10px] text-slate-500 pt-1.5 font-mono">
                      <span>BY {activePost.author.toUpperCase()}</span>
                      <span>&bull;</span>
                      <span>{activePost.date}</span>
                    </div>
                  </div>

                  <p className="text-xs text-slate-300 font-medium italic border-l-2 border-indigo-500 pl-3 leading-relaxed py-1">
                    {activePost.excerpt}
                  </p>

                  <div className="pt-4 border-t border-slate-900 text-slate-300 text-xs leading-relaxed space-y-3 whitespace-pre-line select-text">
                    {activePost.content || (
                      <p className="text-slate-500 font-mono italic">No rich body content was written for this article.</p>
                    )}
                  </div>
                </div>
              ) : (
                <div className="flex items-center justify-center h-full text-slate-600 font-mono text-xs">
                  Select an article to view content.
                </div>
              )}
            </div>

            {/* Comments Timeline Panel (1 col) */}
            <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/20 flex flex-col h-full overflow-hidden">
              <h4 className="text-xs font-mono text-slate-400 font-extrabold uppercase shrink-0 pb-2 border-b border-slate-900">
                Community Discussions (`blog_comments`)
              </h4>
              
              {/* Form inside comments */}
              <form onSubmit={submitComment} className="space-y-2 py-3 border-b border-slate-900 shrink-0">
                <input
                  type="text"
                  placeholder="Your Name"
                  value={commentName}
                  onChange={(e) => setCommentName(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-850 rounded px-2.5 py-1.5 text-[11px] focus:outline-none focus:border-indigo-500 text-slate-300 font-sans"
                  required
                />
                <textarea
                  placeholder="Join the discussion..."
                  value={commentText}
                  onChange={(e) => setCommentText(e.target.value)}
                  rows={2}
                  className="w-full bg-slate-950 border border-slate-850 rounded px-2.5 py-1.5 text-[11px] focus:outline-none focus:border-indigo-500 text-slate-300 font-sans"
                  required
                />
                <button
                  type="submit"
                  className="w-full py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[10px] font-bold uppercase transition-colors shrink-0"
                >
                  Post Comment
                </button>
              </form>

              {/* Feed of comments */}
              <div className="flex-1 overflow-y-auto space-y-2.5 pt-3 pr-1">
                {activeComments.length === 0 ? (
                  <div className="text-center text-slate-600 font-mono text-[10px] py-12">
                    No community feedback yet. Be the first to start the thread!
                  </div>
                ) : (
                  activeComments.map(c => (
                    <div key={c.id} className="p-2.5 rounded bg-slate-950 border border-slate-900 text-slate-400 space-y-1">
                      <div className="flex justify-between font-mono text-indigo-400 text-[9px]">
                        <span>{c.author}</span>
                        <span>{c.date}</span>
                      </div>
                      <p className="text-slate-300 text-[11px] leading-relaxed select-text">{c.text}</p>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* SUB TAB 2: Admin CMS Editor Workspace */}
      {activeSubTab === "cms" && (
        <div className="grid grid-cols-1 xl:grid-cols-4 gap-6 min-h-[600px]">
          {/* Left Panel: Form Composer (3 Columns on desktop) */}
          <div className="xl:col-span-3 bg-slate-950/40 border border-slate-800 rounded-xl p-5 md:p-6 flex flex-col space-y-5">
            <div className="flex justify-between items-center pb-3 border-b border-slate-900 shrink-0">
              <h4 className="text-sm font-bold text-slate-100 font-mono uppercase flex items-center gap-1.5">
                <FileText size={15} className="text-indigo-400" />
                {editingPostId ? "Modify Blog Article Editor" : "Create Technical Blog Article"}
              </h4>
              {editingPostId && (
                <button
                  onClick={clearForm}
                  className="text-[10px] font-mono text-rose-400 hover:text-rose-300 transition-colors uppercase border border-rose-950 bg-rose-950/20 px-2 py-0.5 rounded font-bold"
                >
                  Cancel Edit
                </button>
              )}
            </div>

            <form onSubmit={handleSavePost} className="space-y-4">
              {/* Row 1: Title & Category */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="md:col-span-2 space-y-1">
                  <label className="text-[10px] font-mono uppercase font-bold text-slate-400">Post Title *</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Building Resilient Financial Audits with MPESA Daraja API"
                    value={formTitle}
                    onChange={(e) => handleTitleChange(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs focus:outline-none focus:border-indigo-500 text-slate-200"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] font-mono uppercase font-bold text-slate-400">Category</label>
                  <select
                    value={formCategory}
                    onChange={(e) => setFormCategory(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs focus:outline-none focus:border-indigo-500 text-slate-200 font-mono"
                  >
                    <option value="Cloud Engineering">Cloud Engineering</option>
                    <option value="Database Architecture">Database Architecture</option>
                    <option value="Fintech Integration">Fintech Integration</option>
                    <option value="SaaS Strategy">SaaS Strategy</option>
                    <option value="DevOps Metrics">DevOps Metrics</option>
                  </select>
                </div>
              </div>

              {/* Row 2: Slug & Author */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="md:col-span-2 space-y-1">
                  <div className="flex justify-between items-center">
                    <label className="text-[10px] font-mono uppercase font-bold text-slate-400">URL Slug *</label>
                    <button
                      type="button"
                      onClick={() => handleTitleChange(formTitle)}
                      className="text-[9px] font-mono text-indigo-400 hover:underline hover:text-indigo-300"
                    >
                      Regenerate
                    </button>
                  </div>
                  <input
                    type="text"
                    required
                    placeholder="e.g. mpesa-daraja-api-audit"
                    value={formSlug}
                    onChange={(e) => setFormSlug(e.target.value.toLowerCase().replace(/\s+/g, "-"))}
                    className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs focus:outline-none focus:border-indigo-500 text-slate-300 font-mono"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] font-mono uppercase font-bold text-slate-400">Author Name</label>
                  <input
                    type="text"
                    required
                    placeholder="Author name"
                    value={formAuthor}
                    onChange={(e) => setFormAuthor(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs focus:outline-none focus:border-indigo-500 text-slate-300"
                  />
                </div>
              </div>

              {/* Excerpt Summary */}
              <div className="space-y-1">
                <label className="text-[10px] font-mono uppercase font-bold text-slate-400">Excerpt / Meta Description Preview *</label>
                <textarea
                  required
                  rows={2}
                  placeholder="Summarize the core engineering lesson or strategic insights of this blog post..."
                  value={formExcerpt}
                  onChange={(e) => setFormExcerpt(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs focus:outline-none focus:border-indigo-500 text-slate-300"
                />
              </div>

              {/* Rich Body Content (Markdown supported) */}
              <div className="space-y-1">
                <div className="flex justify-between items-center">
                  <label className="text-[10px] font-mono uppercase font-bold text-slate-400">Full Article Content (Markdown) *</label>
                  <span className="text-[9px] font-mono text-slate-500">Supports standard copy pastes & markup logs</span>
                </div>
                <textarea
                  required
                  rows={7}
                  placeholder="Write full article here. Double spacing creates paragraphs. Support standard markdown syntax like `code` tags..."
                  value={formContent}
                  onChange={(e) => setFormContent(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs focus:outline-none focus:border-indigo-500 text-slate-300 font-mono leading-relaxed"
                />
              </div>

              {/* Status and Action Buttons */}
              <div className="flex flex-wrap items-center justify-between gap-4 pt-3 border-t border-slate-900">
                <div className="flex items-center gap-4">
                  <div className="flex items-center gap-2">
                    <span className="text-[10px] font-mono uppercase font-bold text-slate-400">Publication Status:</span>
                    <div className="flex rounded border border-slate-800 bg-slate-950 overflow-hidden">
                      <button
                        type="button"
                        onClick={() => setFormStatus("draft")}
                        className={`px-3 py-1 text-[10px] font-mono uppercase font-bold transition-all ${
                          formStatus === "draft" ? "bg-amber-600/20 text-amber-400 border-r border-slate-800" : "text-slate-500 hover:text-slate-300"
                        }`}
                      >
                        Draft
                      </button>
                      <button
                        type="button"
                        onClick={() => setFormStatus("published")}
                        className={`px-3 py-1 text-[10px] font-mono uppercase font-bold transition-all ${
                          formStatus === "published" ? "bg-emerald-600/20 text-emerald-400" : "text-slate-500 hover:text-slate-300"
                        }`}
                      >
                        Publish
                      </button>
                    </div>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={clearForm}
                    className="px-4 py-2 text-xs font-semibold font-mono text-slate-400 hover:text-slate-200 uppercase bg-slate-950 rounded border border-slate-850"
                  >
                    Reset Canvas
                  </button>
                  <button
                    type="submit"
                    className="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-semibold uppercase flex items-center gap-1 shadow shadow-indigo-600/25"
                  >
                    <CheckCircle2 size={13} />
                    {editingPostId ? "Update Article" : "Save and Publish"}
                  </button>
                </div>
              </div>
            </form>
          </div>

          {/* Right Panel: SEO Analytics Assistant & Post Directory */}
          <div className="xl:col-span-1 space-y-6 flex flex-col h-full justify-between">
            {/* Live SEO Assistant Tool */}
            <div className="bg-slate-900/20 border border-slate-800 rounded-xl p-4 space-y-4">
              <div className="border-b border-slate-900 pb-2">
                <h5 className="text-xs font-mono font-extrabold text-slate-300 uppercase flex items-center gap-1.5">
                  <Sparkles size={13} className="text-indigo-400" />
                  SEO Analyst Co-Pilot
                </h5>
                <p className="text-[10px] text-slate-500">Real-time target keyword metadata validation</p>
              </div>

              {/* Target Keyword input */}
              <div className="space-y-1">
                <label className="text-[9px] font-mono uppercase font-bold text-slate-400 block">Primary Target Keyword</label>
                <input
                  type="text"
                  placeholder="e.g. MPESA Daraja API"
                  value={formKeyword}
                  onChange={(e) => setFormKeyword(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-850 rounded px-2.5 py-1.5 text-xs focus:outline-none focus:border-indigo-500 text-slate-300 font-sans font-medium"
                />
              </div>

              {/* Live Score Dial */}
              <div className="flex items-center gap-3.5 bg-slate-950/60 p-3 rounded-lg border border-slate-900">
                <div className="relative flex items-center justify-center shrink-0">
                  {/* Custom Radial Border Progress */}
                  <div className={`w-14 h-14 rounded-full flex flex-col items-center justify-center border-4 font-mono font-bold font-display text-sm ${
                    seoScore >= 80 ? "border-emerald-500 text-emerald-400" :
                    seoScore >= 50 ? "border-amber-500 text-amber-400" : "border-rose-500 text-rose-400"
                  }`}>
                    {seoScore}
                    <span className="text-[8px] uppercase tracking-tighter text-slate-500 -mt-0.5">SCORE</span>
                  </div>
                </div>
                <div className="min-w-0">
                  <span className="text-[10px] font-mono font-bold uppercase block text-slate-300">
                    {seoScore >= 80 ? "SEO Standard Met" :
                     seoScore >= 50 ? "Needs Improvement" : "Sub-optimal SEO"}
                  </span>
                  <span className="text-[9px] text-slate-500 block leading-tight">
                    {wordCount} words written. Add keywords to headers and metadata fields.
                  </span>
                </div>
              </div>

              {/* SEO Checklist rules */}
              <div className="space-y-2 text-[10px]">
                <span className="text-[9px] font-mono text-slate-500 uppercase tracking-wider block font-bold">SEO Checklist:</span>
                <div className="space-y-1.5 font-mono">
                  <div className="flex items-start gap-1.5">
                    <span className={seoChecks.titleLength ? "text-emerald-400 font-bold" : "text-slate-600"}>
                      {seoChecks.titleLength ? "✓" : "○"}
                    </span>
                    <span className={seoChecks.titleLength ? "text-slate-300" : "text-slate-500"}>
                      Title length optimal (25-75 chars)
                    </span>
                  </div>
                  <div className="flex items-start gap-1.5">
                    <span className={seoChecks.wordCount ? "text-emerald-400 font-bold" : "text-slate-600"}>
                      {seoChecks.wordCount ? "✓" : "○"}
                    </span>
                    <span className={seoChecks.wordCount ? "text-slate-300" : "text-slate-500"}>
                      Body contains at least 100 words
                    </span>
                  </div>
                  <div className="flex items-start gap-1.5">
                    <span className={seoChecks.titleKeyword ? "text-emerald-400 font-bold" : "text-slate-600"}>
                      {seoChecks.titleKeyword ? "✓" : "○"}
                    </span>
                    <span className={seoChecks.titleKeyword ? "text-slate-300" : "text-slate-500"}>
                      Keyword is present in Title
                    </span>
                  </div>
                  <div className="flex items-start gap-1.5">
                    <span className={seoChecks.slugKeyword ? "text-emerald-400 font-bold" : "text-slate-600"}>
                      {seoChecks.slugKeyword ? "✓" : "○"}
                    </span>
                    <span className={seoChecks.slugKeyword ? "text-slate-300" : "text-slate-500"}>
                      Keyword is present in URL Slug
                    </span>
                  </div>
                  <div className="flex items-start gap-1.5">
                    <span className={seoChecks.bodyKeyword ? "text-emerald-400 font-bold" : "text-slate-600"}>
                      {seoChecks.bodyKeyword ? "✓" : "○"}
                    </span>
                    <span className={seoChecks.bodyKeyword ? "text-slate-300" : "text-slate-500"}>
                      Keyword is present in Body text
                    </span>
                  </div>
                  <div className="flex items-start gap-1.5">
                    <span className={seoChecks.metaLength ? "text-emerald-400 font-bold" : "text-slate-600"}>
                      {seoChecks.metaLength ? "✓" : "○"}
                    </span>
                    <span className={seoChecks.metaLength ? "text-slate-300" : "text-slate-500"}>
                      Meta description optimal length
                    </span>
                  </div>
                </div>
              </div>
            </div>

            {/* Quick Articles list manager inside CMS */}
            <div className="bg-slate-900/20 border border-slate-800 rounded-xl p-4 flex-1 flex flex-col justify-between overflow-hidden min-h-[220px]">
              <div>
                <span className="text-[9px] font-mono font-bold text-slate-500 uppercase tracking-widest block px-1 pb-2 border-b border-slate-900">
                  Article Management
                </span>
                
                <div className="space-y-1.5 max-h-48 overflow-y-auto pt-2.5 pr-1 flex-1">
                  {blogPosts.map(post => (
                    <div key={post.id} className="flex justify-between items-center p-2 rounded bg-slate-950/60 border border-slate-900 text-xs">
                      <div className="min-w-0 pr-2">
                        <span className="font-bold text-slate-200 block text-[11px] truncate">{post.title}</span>
                        <div className="flex items-center gap-1.5 text-[8px] font-mono uppercase mt-0.5 text-slate-500">
                          <span>{post.category}</span>
                          <span>&bull;</span>
                          <button
                            onClick={() => togglePostStatus(post.id)}
                            className={`underline cursor-pointer font-extrabold ${post.status === "published" ? "text-emerald-400" : "text-amber-400"}`}
                          >
                            {post.status}
                          </button>
                        </div>
                      </div>
                      
                      <div className="flex items-center gap-1 shrink-0">
                        <button
                          onClick={() => loadPostForEditing(post)}
                          className="p-1 text-slate-400 hover:text-indigo-400 transition-colors"
                          title="Edit article"
                        >
                          <FileText size={12} />
                        </button>
                        <button
                          onClick={() => handleDeletePost(post.id, post.title)}
                          className="p-1 text-slate-500 hover:text-rose-500 transition-colors"
                          title="Delete article"
                        >
                          <Trash2 size={12} />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function CrmActivitiesTab() {
  const [activeTab, setActiveTab] = useState<string>("timeline");
  const [searchQuery, setSearchQuery] = useState<string>("");
  const [typeFilter, setTypeFilter] = useState<string>("all");

  // Signature canvas states
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const [isDrawing, setIsDrawing] = useState(false);
  const [sigType, setSigType] = useState<"typed" | "drawn">("typed");
  const [typedName, setTypedName] = useState("Alice Wanjiru");
  const [commentsInput, setCommentsInput] = useState("");

  // Proposals & Contracts State
  const [proposalViewMode, setProposalViewMode] = useState<"list" | "create" | "review">("list");
  const [selectedProposalId, setSelectedProposalId] = useState<string>("PROP-001");
  const [proposals, setProposals] = useState<any[]>([
    {
      id: "PROP-001",
      title: "SaaS Enterprise Core Architecture & Security Hardening",
      clientName: "Alice Wanjiru (Apex Digital)",
      status: "sent",
      totalAmount: 48000,
      expiresAt: "2026-08-05",
      sections: [
        { title: "1. Executive Summary", content: "JUANET Enterprise proposes a secure, containerized digital backbone with decoupled microservices and standard tenant boundary policies." },
        { title: "2. Technical Scope", content: "Implementation of multi-tenant routing, transactional outbox patterns, and an isolated CRM pipeline database schema." },
        { title: "3. Delivery Timeline", content: "Completed across 3 discrete agile sprints spanning 30 business days with high-availability cluster deployments." }
      ],
      items: [
        { description: "Enterprise Digital Backbone Core Deployment", quantity: 1, unit_price: 32000 },
        { description: "Cloud Systems Architecture & Security Hardening", quantity: 1, unit_price: 16000 }
      ],
      comments: [
        { id: "C1", user: "Alice Wanjiru (Client)", text: "Could we confirm if the multi-tenant PostgreSQL schema isolates indexes by company tenant?", date: "1 hour ago" },
        { id: "C2", user: "Lead Architect (JUANET)", text: "Yes Alice, every table schema has composite indexes with 'organization_id' and queries are gated at row-level security.", date: "45 mins ago" }
      ],
      revisions: [
        { version: 1, notes: "Initial Scope Proposal", date: "2 hours ago" }
      ],
      signature: null
    }
  ]);

  // Create form state
  const [newPropTitle, setNewPropTitle] = useState<string>("Enterprise ERP Integration & Custom API Gateway");
  const [newPropClient, setNewPropClient] = useState<string>("James Mwangi (Equity Bank)");
  const [newPropExpires, setNewPropExpires] = useState<string>("2026-08-15");
  const [newPropSections, setNewPropSections] = useState<any[]>([
    { title: "1. Executive Summary", content: "Custom high-throughput API gateway implementation integrated with core bank ledgers." },
    { title: "2. Terms & SLA", content: "99.99% core transaction gateway availability guarantee with continuous health telemetry." }
  ]);
  const [newPropItems, setNewPropItems] = useState<any[]>([
    { description: "Custom Bank API Gateway & Security Shield", quantity: 1, unit_price: 25000 },
    { description: "Oracle Database Schema Reconciliation Adapter", quantity: 1, unit_price: 15000 }
  ]);

  // Enterprise Contacts State & Wizards
  const [contactViewMode, setContactViewMode] = useState<string>("directory"); // directory, profile, merge, import
  const [selectedContactId, setSelectedContactId] = useState<string>("CONT-001");
  const [selectedContactIds, setSelectedContactIds] = useState<string[]>([]);
  
  // Filtering for contacts
  const [tierFilter, setTierFilter] = useState<string>("all");
  const [segmentFilter, setSegmentFilter] = useState<string>("all");
  const [lifecycleFilter, setLifecycleFilter] = useState<string>("all");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [healthFilter, setHealthFilter] = useState<string>("all");

  // Outbox list specifically for showing our strongly typed events
  const [outboxEvents, setOutboxEvents] = useState<any[]>([
    { id: "EVT-1", name: "crm.contact.created", type: "queued", ts: "10 mins ago", target: "Caleb Kirui" },
    { id: "EVT-2", name: "crm.contact.health_changed", type: "queued", ts: "Just now", target: "Mary Kamau" }
  ]);

  const triggerEventLog = (eventName: string, targetName: string) => {
    setOutboxEvents(prev => [
      { id: "EVT-" + Date.now(), name: eventName, type: "queued", ts: "Just now", target: targetName },
      ...prev
    ]);
  };

  // Contacts dataset
  const [contacts, setContacts] = useState<any[]>([
    {
      id: "CONT-001",
      first_name: "Caleb",
      middle_name: "Kip",
      last_name: "Kirui",
      preferred_name: "Caleb",
      email: "caleb@telecom.co.ke",
      personal_email: "caleb.kirui@gmail.com",
      assistant_email: "assistant.caleb@telecom.co.ke",
      phone: "+254 700 111222",
      work_phone: "+254 20 555666",
      mobile_phone: "+254 700 111222",
      home_phone: "",
      assistant_phone: "+254 700 999888",
      fax: "",
      whatsapp: "+254 700 111222",
      telegram: "@caleb_kirui",
      signal: "@caleb.k",
      job_title: "VP of Enterprise Cloud Infrastructure",
      department: "Information Technology",
      company_id: "COMP-003",
      company_name: "Acme Kenya Operations",
      user_id: "USER-99", // owner
      manager_id: null,
      gender: "Male",
      nationality: "Kenyan",
      languages: ["English", "Swahili"],
      notes: "Caleb is highly interested in the multi-tenant PostgreSQL security setup.",
      buying_role: "Decision Maker",
      is_decision_maker: true,
      is_influencer: false,
      is_technical_contact: true,
      tier: "Tier A",
      segment: "Enterprise",
      lifecycle_stage: "SQL",
      classification: "High Touch",
      status: "Active",
      sms_consent: true,
      whatsapp_consent: true,
      email_consent: true,
      do_not_call: false,
      do_not_email: false,
      do_not_sms: false,
      gdpr_consent_status: "granted",
      health_score: 85,
      health_status: "Healthy",
      health_breakdown: { engagement: 15, responsiveness: 10, meeting_frequency: 15, sales_influence: 15, relationship_strength: 10, profile_completeness: 20 },
      custom_fields: { tenant_sector: "Finance", priority_level: "High", trial_extended_until: "2026-12-31" },
      addresses: [
        { id: "ADDR-1", type: "primary", is_primary: true, street: "Galana Rd, Kilimani", city: "Nairobi", country: "Kenya", timezone: "Africa/Nairobi" },
        { id: "ADDR-2", type: "billing", is_primary: false, street: "Waiyaki Way, Westlands", city: "Nairobi", country: "Kenya", timezone: "Africa/Nairobi" }
      ],
      consents: [
        { id: "CONS-1", channel: "email", status: "granted", purpose: "marketing", consented_at: "2026-06-15", source: "webform" },
        { id: "CONS-2", channel: "whatsapp", status: "granted", purpose: "support", consented_at: "2026-06-16", source: "agent" }
      ],
      relationships: [
        { id: "REL-1", related_contact_id: "CONT-002", type: "colleague" }
      ]
    },
    {
      id: "CONT-002",
      first_name: "Mary",
      middle_name: "",
      last_name: "Kamau",
      preferred_name: "Mary",
      email: "mary@telecom.co.ke",
      personal_email: "",
      assistant_email: "",
      phone: "+254 711 000222",
      work_phone: "+254 711 000222",
      mobile_phone: "",
      home_phone: "",
      assistant_phone: "",
      fax: "",
      whatsapp: "",
      telegram: "",
      signal: "",
      job_title: "Head of Infrastructure Projects",
      department: "Procurement & Telecoms",
      company_id: "COMP-004",
      company_name: "Safaricom PLC",
      user_id: "USER-99",
      manager_id: "CONT-001",
      gender: "Female",
      nationality: "Kenyan",
      languages: ["English"],
      notes: "Needs fiber survey and complex cabling quote.",
      buying_role: "Influencer",
      is_decision_maker: false,
      is_influencer: true,
      is_technical_contact: false,
      tier: "Tier B",
      segment: "Enterprise",
      lifecycle_stage: "MQL",
      classification: "Mid Touch",
      status: "Active",
      sms_consent: false,
      whatsapp_consent: false,
      email_consent: true,
      do_not_call: false,
      do_not_email: false,
      do_not_sms: true,
      gdpr_consent_status: "granted",
      health_score: 72,
      health_status: "Warning",
      health_breakdown: { engagement: 10, responsiveness: 10, meeting_frequency: 10, sales_influence: 10, relationship_strength: 12, profile_completeness: 15 },
      custom_fields: { secondary_lead_source: "Direct Call" },
      addresses: [
        { id: "ADDR-3", type: "office", is_primary: true, street: "Safaricom House, Waiyaki Way", city: "Nairobi", country: "Kenya", timezone: "Africa/Nairobi" }
      ],
      consents: [
        { id: "CONS-3", channel: "email", status: "granted", purpose: "marketing", consented_at: "2026-06-20", source: "webform" }
      ],
      relationships: []
    },
    {
      id: "CONT-003",
      first_name: "Caleb (Duplicate)",
      middle_name: "K.",
      last_name: "Kirui",
      preferred_name: "Caleb",
      email: "caleb@telecom.co.ke", // matching email
      personal_email: "",
      assistant_email: "",
      phone: "+254 700 111222", // matching phone
      work_phone: "",
      mobile_phone: "",
      home_phone: "",
      assistant_phone: "",
      fax: "",
      whatsapp: "",
      telegram: "",
      signal: "",
      job_title: "VP Enterprise Cloud",
      department: "IT",
      company_id: "COMP-003",
      company_name: "Acme Kenya Operations",
      user_id: "USER-99",
      manager_id: null,
      gender: "Male",
      nationality: "Kenyan",
      languages: [],
      notes: "Alternate registration card found.",
      buying_role: "Decision Maker",
      is_decision_maker: true,
      is_influencer: false,
      is_technical_contact: true,
      tier: "Tier C",
      segment: "SMB",
      lifecycle_stage: "Lead",
      classification: "Tech Touch",
      status: "Active",
      sms_consent: false,
      whatsapp_consent: false,
      email_consent: false,
      do_not_call: false,
      do_not_email: false,
      do_not_sms: false,
      gdpr_consent_status: "not_asked",
      health_score: 42,
      health_status: "Critical",
      health_breakdown: { engagement: 5, responsiveness: 2, meeting_frequency: 5, sales_influence: 5, relationship_strength: 5, profile_completeness: 5 },
      custom_fields: {},
      addresses: [],
      consents: [],
      relationships: []
    }
  ]);

  // Companies list mock state for the interactive visualizer
  const [companies, setCompanies] = useState<any[]>([
    {
      id: "COMP-001",
      name: "Acme Global Holdings",
      trading_name: "Acme Group",
      domain: "acme-group.com",
      status: "Customer",
      parent_id: null,
      registration_number: "REG-992019-X",
      tax_number: "TAX-GB-11202",
      company_size: "enterprise",
      industry_classification: "Information Technology",
      annual_revenue: "KES 500,000,000",
      employees_count: 1200,
      phone: "+44 20 7946 0958",
      website: "https://acme-group.com",
      address: "100 Wood St, London, UK",
      preferred_language: "English",
      currency: "GBP",
      health_score: 95,
      health_status: "Healthy",
      health_breakdown: { engagement: 15, opportunities: 20, outstanding_tasks: 0 },
      locations: [
        { id: "LOC-1", type: "headquarters", name: "Acme London HQ", city: "London", country: "United Kingdom" }
      ]
    },
    {
      id: "COMP-002",
      name: "Acme EMEA Ltd",
      trading_name: "Acme EMEA",
      domain: "acme-emea.io",
      status: "Customer",
      parent_id: "COMP-001",
      registration_number: "REG-EMEA-4412",
      tax_number: "TAX-NL-88219",
      company_size: "mid_market",
      industry_classification: "Telecommunications",
      annual_revenue: "KES 150,000,000",
      employees_count: 350,
      phone: "+31 20 7946 1122",
      website: "https://acme-emea.io",
      address: "Keizersgracht 424, Amsterdam, Netherlands",
      preferred_language: "English",
      currency: "EUR",
      health_score: 82,
      health_status: "Healthy",
      health_breakdown: { engagement: 12, opportunities: 20, outstanding_tasks: 0 },
      locations: [
        { id: "LOC-2", type: "branch", name: "Amsterdam Office", city: "Amsterdam", country: "Netherlands" },
        { id: "LOC-3", type: "warehouse", name: "Rotterdam Depot", city: "Rotterdam", country: "Netherlands" }
      ]
    },
    {
      id: "COMP-003",
      name: "Acme Kenya Operations",
      trading_name: "Acme Kenya",
      domain: "acme.co.ke",
      status: "Prospect",
      parent_id: "COMP-002",
      registration_number: "REG-KE-9018",
      tax_number: "TAX-KE-009211",
      company_size: "mid_market",
      industry_classification: "Financial Services",
      annual_revenue: "KES 45,000,000",
      employees_count: 80,
      phone: "+254 20 1234567",
      website: "https://acme.co.ke",
      address: "Galana Plaza, Galana Rd, Nairobi, Kenya",
      preferred_language: "Swahili",
      currency: "KES",
      health_score: 48,
      health_status: "Critical",
      health_breakdown: { engagement: 3, opportunities: 5, outstanding_tasks: -30 },
      locations: [
        { id: "LOC-4", type: "branch", name: "Nairobi Office", city: "Nairobi", country: "Kenya" },
        { id: "LOC-5", type: "billing", name: "Acme Kenya Billing", city: "Nairobi", country: "Kenya" }
      ]
    },
    {
      id: "COMP-004",
      name: "Safaricom PLC",
      trading_name: "Safaricom",
      domain: "safaricom.co.ke",
      status: "Customer",
      parent_id: null,
      registration_number: "REG-SAF-001",
      tax_number: "P000129381M",
      company_size: "enterprise",
      industry_classification: "Telecommunications",
      annual_revenue: "KES 310,000,000,000",
      employees_count: 6500,
      phone: "+254 711 000000",
      website: "https://safaricom.co.ke",
      address: "Safaricom House, Waiyaki Way, Nairobi, Kenya",
      preferred_language: "English",
      currency: "KES",
      health_score: 92,
      health_status: "Healthy",
      health_breakdown: { engagement: 15, opportunities: 20, outstanding_tasks: 0 },
      locations: [
        { id: "LOC-6", type: "headquarters", name: "HQ Waiyaki Way", city: "Nairobi", country: "Kenya" },
        { id: "LOC-7", type: "branch", name: "Mombasa Office", city: "Mombasa", country: "Kenya" }
      ]
    }
  ]);

  const [selectedCompanyId, setSelectedCompanyId] = useState<string>("COMP-003");
  const [newLocType, setNewLocType] = useState<string>("branch");
  const [newLocName, setNewLocName] = useState<string>("");
  const [newLocCity, setNewLocCity] = useState<string>("");
  const [newLocCountry, setNewLocCountry] = useState<string>("");

  const activeCompany = companies.find(c => c.id === selectedCompanyId) || companies[0];
  
  // Real-time interactive state
  const [activities, setActivities] = useState<any[]>([
    {
      id: "ACT-001",
      type: "phone_call",
      subject: "Cold Discovery Call — Caleb Kirui",
      description: "Discussed multi-tenant postgres requirement and scaling infrastructure. Caleb is highly interested in the KES 650K annual SaaS tier.",
      is_completed: true,
      completed_at: "2 hours ago",
      due_at: "Completed today",
      priority: "high",
      user_name: "Juan (Admin)",
      attachments: [],
      notes: [
        { id: "N-1", version: 1, content: "Initial interest logged. Follow up with system blueprints.", user: "Juan (Admin)", date: "2 hrs ago" }
      ],
      created_at: "2 hours ago"
    },
    {
      id: "ACT-002",
      type: "meeting",
      subject: "Demo & Contract Proposal Scoping",
      description: "Presenting ERD layouts, Safaricom Daraja Webhooks callback architecture, and billing models.",
      is_completed: false,
      due_at: "In 3 hours",
      priority: "high",
      user_name: "Mary Kamau",
      attachments: [
        { name: "system_architecture_spec.pdf", size: "2.4 MB" }
      ],
      notes: [],
      created_at: "3 hours ago"
    },
    {
      id: "ACT-003",
      type: "follow_up_task",
      subject: "Deploy staging test routes for Lipa Na M-PESA",
      description: "Verify Daraja async signature validation under stress test script.",
      is_completed: false,
      due_at: "Yesterday (OVERDUE)",
      priority: "high",
      user_name: "Juan (Admin)",
      attachments: [],
      notes: [],
      created_at: "1 day ago"
    },
    {
      id: "ACT-004",
      type: "internal_note",
      subject: "Security Audit Checklist Added",
      description: "Verified composite key constraints on CheckoutRequestID. Prevented duplication of payment registers.",
      is_completed: true,
      completed_at: "Yesterday",
      due_at: "Completed",
      priority: "medium",
      user_name: "Caleb Kirui",
      attachments: [],
      notes: [],
      created_at: "1 day ago"
    },
    {
      id: "ACT-005",
      type: "appt",
      subject: "Staging Server Provision Check",
      description: "Ensure Caddy and Docker networks are locked behind the custom auth roles.",
      is_completed: false,
      due_at: "In 3 days",
      priority: "low",
      user_name: "Mary Kamau",
      attachments: [],
      notes: [],
      created_at: "2 days ago"
    }
  ]);

  // Notes state for Version manager
  const [selectedNote, setSelectedNote] = useState<any>({
    id: "NOTE-MAIN",
    notable_type: "Lead",
    notable_id: "REQ-001",
    content: "### Caleb Kirui Project Requirements\n\n- Scale the multi-tenant architecture\n- Implement Safaricom payments verification\n- Enforce staff roles & permissions checking",
    version: 2,
    original_note_id: null,
    user: "Juan (Admin)",
    updated_at: "Just now",
    history: [
      { version: 1, content: "Caleb project: simple Postgres scale check", user: "Juan (Admin)", date: "1 day ago" }
    ],
    replies: [
      { id: "rep-1", author: "Mary Kamau", text: "Checked. The RLS policies look correct.", date: "4 hours ago" }
    ]
  });

  // Reminders list
  const [reminders, setReminders] = useState<any[]>([
    { id: "REM-1", title: "Demo prep for Caleb Kirui", remind_at: "10 mins before", method: "in_app", status: "Active" },
    { id: "REM-2", title: "Follow up with MPESA callback staging", remind_at: "Every day at 9:00 AM", method: "email", status: "Active" }
  ]);

  // Form states for adding activities
  const [newType, setNewType] = useState<string>("phone_call");
  const [newSubject, setNewSubject] = useState<string>("");
  const [newDesc, setNewDesc] = useState<string>("");
  const [newPriority, setNewPriority] = useState<string>("medium");
  const [newDue, setNewDue] = useState<string>("");
  const [newAssignee, setNewAssignee] = useState<string>("Juan (Admin)");

  // Form state for Note editor
  const [noteEditContent, setNoteEditContent] = useState<string>(selectedNote.content);
  const [newReplyText, setNewReplyText] = useState<string>("");

  // Form state for new Reminder
  const [remindTitle, setRemindTitle] = useState<string>("");
  const [remindTime, setRemindTime] = useState<string>("");
  const [remindMethod, setRemindMethod] = useState<string>("in_app");

  // Selection states for bulk actions
  const [selectedIds, setSelectedIds] = useState<string[]>([]);

  // Toggle selection
  const handleSelectId = (id: string) => {
    setSelectedIds(prev => 
      prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]
    );
  };

  // Select all
  const handleSelectAll = () => {
    if (selectedIds.length === activities.length) {
      setSelectedIds([]);
    } else {
      setSelectedIds(activities.map(a => a.id));
    }
  };

  // Bulk complete
  const handleBulkComplete = () => {
    setActivities(prev => prev.map(a => 
      selectedIds.includes(a.id) ? { ...a, is_completed: true, completed_at: "Just now" } : a
    ));
    setSelectedIds([]);
  };

  // Bulk delete
  const handleBulkDelete = () => {
    setActivities(prev => prev.filter(a => !selectedIds.includes(a.id)));
    setSelectedIds([]);
  };

  // Add new activity
  const handleAddActivity = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newSubject.trim()) return;

    const newAct = {
      id: `ACT-${Date.now()}`,
      type: newType,
      subject: newSubject,
      description: newDesc,
      is_completed: false,
      due_at: newDue || "No due date",
      priority: newPriority,
      user_name: newAssignee,
      attachments: [],
      notes: [],
      created_at: "Just now"
    };

    setActivities([newAct, ...activities]);
    
    // Clear form
    setNewSubject("");
    setNewDesc("");
    setNewDue("");
  };

  // Toggle task complete
  const toggleComplete = (id: string) => {
    setActivities(prev => prev.map(a => 
      a.id === id ? { ...a, is_completed: !a.is_completed, completed_at: !a.is_completed ? "Just now" : undefined } : a
    ));
  };

  // Save updated Note version
  const handleSaveNoteVersion = () => {
    if (noteEditContent.trim() === selectedNote.content) return;

    const newHistory = {
      version: selectedNote.version,
      content: selectedNote.content,
      user: selectedNote.user,
      date: selectedNote.updated_at
    };

    setSelectedNote({
      ...selectedNote,
      version: selectedNote.version + 1,
      content: noteEditContent,
      updated_at: "Just now",
      history: [newHistory, ...selectedNote.history]
    });
  };

  // Add reply to note
  const handleAddReply = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newReplyText.trim()) return;

    const reply = {
      id: `rep-${Date.now()}`,
      author: "Juan (Admin)",
      text: newReplyText,
      date: "Just now"
    };

    setSelectedNote({
      ...selectedNote,
      replies: [...selectedNote.replies, reply]
    });
    setNewReplyText("");
  };

  // Add new reminder
  const handleAddReminder = (e: React.FormEvent) => {
    e.preventDefault();
    if (!remindTitle.trim() || !remindTime) return;

    const newRem = {
      id: `REM-${Date.now()}`,
      title: remindTitle,
      remind_at: remindTime,
      method: remindMethod,
      status: "Active"
    };

    setReminders([...reminders, newRem]);
    setRemindTitle("");
    setRemindTime("");
  };

  // Filter activities
  const filteredActivities = activities.filter(act => {
    const matchesSearch = act.subject.toLowerCase().includes(searchQuery.toLowerCase()) || 
                          act.description.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesType = typeFilter === "all" || act.type === typeFilter;
    return matchesSearch && matchesType;
  });

  // Icon selector
  const getActivityIcon = (type: string) => {
    switch (type) {
      case "phone_call":
        return <Phone size={14} className="text-emerald-400" />;
      case "meeting":
        return <Video size={14} className="text-blue-400" />;
      case "follow_up_task":
        return <CheckSquare size={14} className="text-amber-400" />;
      case "internal_note":
        return <MessageCircle size={14} className="text-indigo-400" />;
      case "appt":
        return <Calendar size={14} className="text-purple-400" />;
      default:
        return <Activity size={14} className="text-slate-400" />;
    }
  };

  return (
    <div className="space-y-6">
      {/* Header section */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h3 className="text-xl font-display font-bold text-white flex items-center gap-2">
            <Activity size={22} className="text-indigo-400 animate-pulse" />
            CRM Activities & Unified Timeline Engine
          </h3>
          <p className="text-xs text-slate-400">
            A reusable, Salesforce-grade communication engine tracking client calls, automated webhooks callback audits, versions notes, and critical reminders.
          </p>
        </div>

        {/* Core Stats overview */}
        <div className="flex flex-wrap gap-2">
          <div className="bg-slate-900/40 border border-slate-800 px-3 py-1.5 rounded-lg text-center min-w-[100px]">
            <span className="text-[9px] font-mono uppercase text-slate-500 font-bold block">Total Stream</span>
            <span className="text-sm font-bold text-slate-100 font-mono">{activities.length} logs</span>
          </div>
          <div className="bg-slate-900/40 border border-slate-800 px-3 py-1.5 rounded-lg text-center min-w-[100px]">
            <span className="text-[9px] font-mono uppercase text-slate-500 font-bold block">Active Tasks</span>
            <span className="text-sm font-bold text-amber-400 font-mono">
              {activities.filter(a => a.type === "follow_up_task" && !a.is_completed).length} items
            </span>
          </div>
          <div className="bg-slate-900/40 border border-slate-800 px-3 py-1.5 rounded-lg text-center min-w-[100px]">
            <span className="text-[9px] font-mono uppercase text-slate-500 font-bold block">Reminders</span>
            <span className="text-sm font-bold text-indigo-400 font-mono">{reminders.length} sched</span>
          </div>
        </div>
      </div>

      {/* Main Grid Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        {/* Left Side: Create Staging Activity & Reminders (4 Cols) */}
        <div className="lg:col-span-4 space-y-6">
          
          {/* Quick Add Form */}
          <div className="bg-slate-900/20 border border-slate-800 rounded-xl p-5 space-y-4 shadow-sm">
            <div className="border-b border-slate-850 pb-2">
              <span className="text-[10px] font-mono font-extrabold text-indigo-400 uppercase tracking-widest block">Quick Log Assistant</span>
              <h4 className="text-xs font-bold text-slate-300">Create New Stream Interaction</h4>
            </div>

            <form onSubmit={handleAddActivity} className="space-y-3 text-xs">
              <div className="space-y-1">
                <label className="text-[9px] font-mono uppercase font-bold text-slate-400 block">Activity Channel Type</label>
                <select
                  value={newType}
                  onChange={(e) => setNewType(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-850 rounded px-2.5 py-1.5 text-slate-300 focus:outline-none focus:border-indigo-500 font-mono font-bold"
                >
                  <option value="phone_call">📞 Phone Call (Call logged)</option>
                  <option value="meeting">🎥 Video Demo / Meeting</option>
                  <option value="follow_up_task">✍ Follow-up Task</option>
                  <option value="internal_note">📓 Internal Reference Note</option>
                  <option value="appt">📅 Cal Appointment</option>
                </select>
              </div>

              <div className="space-y-1">
                <label className="text-[9px] font-mono uppercase font-bold text-slate-400 block">Subject / Objective *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Scoping postgres deployment needs"
                  value={newSubject}
                  onChange={(e) => setNewSubject(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-850 rounded px-2.5 py-1.5 text-slate-300 focus:outline-none focus:border-indigo-500 font-medium"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[9px] font-mono uppercase font-bold text-slate-400 block">Detailed Summary</label>
                <textarea
                  rows={3}
                  placeholder="Summarize the core outcome, next steps, and specific requirements from Caleb..."
                  value={newDesc}
                  onChange={(e) => setNewDesc(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-850 rounded px-2.5 py-1.5 text-slate-300 focus:outline-none focus:border-indigo-500 text-[11px]"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-[9px] font-mono uppercase font-bold text-slate-400 block">Priority</label>
                  <select
                    value={newPriority}
                    onChange={(e) => setNewPriority(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-850 rounded px-2 py-1.5 text-slate-300 font-mono"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                  </select>
                </div>
                <div className="space-y-1">
                  <label className="text-[9px] font-mono uppercase font-bold text-slate-400 block">Due / Execution Date</label>
                  <input
                    type="text"
                    placeholder="e.g. Today 5 PM, or In 2 Days"
                    value={newDue}
                    onChange={(e) => setNewDue(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-850 rounded px-2 py-1 text-slate-300 font-medium text-center"
                  />
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-[9px] font-mono uppercase font-bold text-slate-400 block">Assigned Staff Owner</label>
                <select
                  value={newAssignee}
                  onChange={(e) => setNewAssignee(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-850 rounded px-2 py-1.5 text-slate-300"
                >
                  <option value="Juan (Admin)">Juan (Admin)</option>
                  <option value="Mary Kamau">Mary Kamau (Senior Sales)</option>
                  <option value="Caleb Kirui">Caleb Kirui (Partner Architect)</option>
                </select>
              </div>

              <button
                type="submit"
                className="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded text-xs uppercase font-mono tracking-wider flex items-center justify-center gap-1.5 transition-all shadow-sm"
              >
                <Plus size={14} /> Log Action to Timeline
              </button>
            </form>
          </div>

          {/* Alerts & Reminders Panel */}
          <div className="bg-slate-900/20 border border-slate-800 rounded-xl p-5 space-y-4">
            <div className="border-b border-slate-850 pb-2">
              <span className="text-[10px] font-mono font-extrabold text-indigo-400 uppercase tracking-widest block">Alert Engine</span>
              <h4 className="text-xs font-bold text-slate-300 flex items-center gap-1">
                <Bell size={13} className="text-amber-500" />
                Active Reminders Panel
              </h4>
            </div>

            {/* List reminders */}
            <div className="space-y-2">
              {reminders.map(rem => (
                <div key={rem.id} className="p-2.5 rounded bg-slate-950/60 border border-slate-900 flex justify-between items-start text-[11px]">
                  <div className="space-y-0.5">
                    <span className="font-bold text-slate-200 block">{rem.title}</span>
                    <span className="text-[9px] font-mono text-slate-500 block flex items-center gap-1">
                      <Clock size={10} /> {rem.remind_at}
                    </span>
                  </div>
                  <span className="text-[8px] font-mono uppercase bg-indigo-950/80 text-indigo-300 border border-indigo-900/60 px-1.5 py-0.5 rounded shrink-0">
                    {rem.method === "in_app" ? "🔔 App" : "📧 Email"}
                  </span>
                </div>
              ))}
            </div>

            {/* Add quick reminder */}
            <form onSubmit={handleAddReminder} className="space-y-2 text-xs pt-2 border-t border-slate-900">
              <span className="text-[9px] font-mono text-slate-500 uppercase block font-bold">Schedule Alert reminder:</span>
              <input
                type="text"
                required
                placeholder="Alert title (e.g. Call Caleb)"
                value={remindTitle}
                onChange={(e) => setRemindTitle(e.target.value)}
                className="w-full bg-slate-950 border border-slate-850 rounded px-2 py-1 text-slate-300"
              />
              <div className="grid grid-cols-2 gap-2">
                <input
                  type="text"
                  required
                  placeholder="When (e.g. 10 mins before)"
                  value={remindTime}
                  onChange={(e) => setRemindTime(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-850 rounded px-2 py-1 text-slate-300 font-mono text-center"
                />
                <select
                  value={remindMethod}
                  onChange={(e) => setRemindMethod(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-850 rounded px-2 py-1 text-slate-300"
                >
                  <option value="in_app">🔔 In-App</option>
                  <option value="email">📧 Email</option>
                </select>
              </div>
              <button
                type="submit"
                className="w-full py-1.5 bg-slate-950 hover:bg-slate-900 border border-slate-800 text-[10px] font-bold uppercase text-slate-300 rounded font-mono transition-colors"
              >
                Set Alarm
              </button>
            </form>
          </div>
        </div>

        {/* Right Side: Timeline tabs and list views (8 Cols) */}
        <div className="lg:col-span-8 flex flex-col space-y-6">
          
          {/* Tabs header */}
          <div className="border border-slate-800 bg-slate-900/20 p-2 rounded-xl flex flex-wrap gap-1">
            <button
              onClick={() => setActiveTab("timeline")}
              className={`px-4 py-2 rounded-lg text-xs font-semibold font-mono uppercase transition-all flex items-center gap-1.5 ${
                activeTab === "timeline" ? "bg-indigo-600 text-white font-extrabold" : "text-slate-400 hover:text-slate-200"
              }`}
            >
              <History size={14} /> Unified Timeline
            </button>
            <button
              onClick={() => setActiveTab("tasks")}
              className={`px-4 py-2 rounded-lg text-xs font-semibold font-mono uppercase transition-all flex items-center gap-1.5 ${
                activeTab === "tasks" ? "bg-indigo-600 text-white font-extrabold" : "text-slate-400 hover:text-slate-200"
              }`}
            >
              <CheckSquare size={14} /> Follow-up Tasks List
            </button>
            <button
              onClick={() => setActiveTab("calendar")}
              className={`px-4 py-2 rounded-lg text-xs font-semibold font-mono uppercase transition-all flex items-center gap-1.5 ${
                activeTab === "calendar" ? "bg-indigo-600 text-white font-extrabold" : "text-slate-400 hover:text-slate-200"
              }`}
            >
              <Calendar size={14} /> Calendar Grid
            </button>
            <button
              onClick={() => setActiveTab("notes")}
              className={`px-4 py-2 rounded-lg text-xs font-semibold font-mono uppercase transition-all flex items-center gap-1.5 ${
                activeTab === "notes" ? "bg-indigo-600 text-white font-extrabold" : "text-slate-400 hover:text-slate-200"
              }`}
            >
              <FileText size={14} /> Notes Version Manager
            </button>
            <button
              onClick={() => setActiveTab("companies")}
              className={`px-4 py-2 rounded-lg text-xs font-semibold font-mono uppercase transition-all flex items-center gap-1.5 ${
                activeTab === "companies" ? "bg-indigo-600 text-white font-extrabold" : "text-slate-400 hover:text-slate-200"
              }`}
            >
              <Users size={14} /> Company Accounts Hub
            </button>
            <button
              onClick={() => {
                setActiveTab("contacts");
                setContactViewMode("directory");
              }}
              className={`px-4 py-2 rounded-lg text-xs font-semibold font-mono uppercase transition-all flex items-center gap-1.5 ${
                activeTab === "contacts" ? "bg-indigo-600 text-white font-extrabold" : "text-slate-400 hover:text-slate-200"
              }`}
            >
              <UserCheck size={14} /> Enterprise Contacts
            </button>
            <button
              onClick={() => setActiveTab("proposals")}
              className={`px-4 py-2 rounded-lg text-xs font-semibold font-mono uppercase transition-all flex items-center gap-1.5 ${
                activeTab === "proposals" ? "bg-indigo-600 text-white font-extrabold" : "text-slate-400 hover:text-slate-200"
              }`}
            >
              <FileCode size={14} /> Proposals & e-Contracts
            </button>
          </div>

          {/* Tab contents */}
          <div className="flex-1 bg-slate-950 border border-slate-800 rounded-xl p-5 min-h-[500px]">
            
            {/* VIEW 1: Chronological Unified Timeline */}
            {activeTab === "timeline" && (
              <div className="space-y-5">
                {/* Search and Filters */}
                <div className="flex flex-col md:flex-row gap-3 items-center justify-between pb-3 border-b border-slate-900">
                  <div className="relative w-full md:w-72 shrink-0">
                    <Search className="absolute left-2.5 top-2.5 text-slate-500" size={14} />
                    <input
                      type="text"
                      placeholder="Search timeline subjects..."
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      className="w-full bg-slate-900/60 border border-slate-800 rounded-lg pl-8 pr-3 py-2 text-xs focus:outline-none focus:border-indigo-500 text-slate-300 font-medium"
                    />
                  </div>

                  <div className="flex items-center gap-2 w-full md:w-auto justify-end">
                    <Filter size={12} className="text-slate-500" />
                    <span className="text-[10px] font-mono uppercase text-slate-500">Filter:</span>
                    <select
                      value={typeFilter}
                      onChange={(e) => setTypeFilter(e.target.value)}
                      className="bg-slate-900/60 border border-slate-800 rounded px-2.5 py-1.5 text-xs text-slate-300 font-mono"
                    >
                      <option value="all">All Logs</option>
                      <option value="phone_call">Calls</option>
                      <option value="meeting">Meetings</option>
                      <option value="follow_up_task">Tasks</option>
                      <option value="internal_note">Notes</option>
                    </select>
                  </div>
                </div>

                {/* Bulk Actions header */}
                {selectedIds.length > 0 && (
                  <div className="bg-slate-900/60 border border-indigo-900/40 p-2.5 rounded-lg flex items-center justify-between text-xs font-mono">
                    <span className="text-slate-300 font-bold">{selectedIds.length} items selected</span>
                    <div className="flex items-center gap-2">
                      <button
                        onClick={handleBulkComplete}
                        className="px-2.5 py-1 bg-emerald-600/10 hover:bg-emerald-600/25 text-emerald-400 border border-emerald-900/60 rounded text-[10px] font-bold uppercase transition-colors"
                      >
                        Complete Selected
                      </button>
                      <button
                        onClick={handleBulkDelete}
                        className="px-2.5 py-1 bg-rose-600/10 hover:bg-rose-600/25 text-rose-400 border border-rose-900/60 rounded text-[10px] font-bold uppercase transition-colors"
                      >
                        Delete Selected
                      </button>
                    </div>
                  </div>
                )}

                {/* Timeline Feed Stream */}
                <div className="relative border-l border-slate-900 ml-4 pl-6 space-y-6">
                  {filteredActivities.length === 0 ? (
                    <div className="text-center text-xs text-slate-600 py-12 font-mono">
                      No stream records matching current parameters. Add/Log an activity!
                    </div>
                  ) : (
                    filteredActivities.map((act) => {
                      const isSelected = selectedIds.includes(act.id);
                      return (
                        <div key={act.id} className="relative group select-none">
                          
                          {/* Left bullet marker */}
                          <div className={`absolute -left-[31px] top-1 w-6 h-6 rounded-full border border-slate-950 flex items-center justify-center bg-slate-900/80 group-hover:scale-110 transition-transform ${
                            act.is_completed ? "ring-2 ring-emerald-500/25" : "ring-1 ring-slate-800"
                          }`}>
                            {getActivityIcon(act.type)}
                          </div>

                          {/* Card Content */}
                          <div className={`p-4 rounded-xl border transition-all ${
                            isSelected 
                              ? "bg-slate-900/40 border-indigo-500/45" 
                              : "bg-slate-900/10 border-slate-850 hover:bg-slate-900/20 hover:border-slate-800"
                          }`}>
                            <div className="flex justify-between items-start gap-4">
                              <div className="space-y-1 min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                  {/* Selection Checkbox */}
                                  <input
                                    type="checkbox"
                                    checked={isSelected}
                                    onChange={() => handleSelectId(act.id)}
                                    className="rounded border-slate-800 bg-slate-950 text-indigo-600 focus:ring-0 focus:ring-offset-0"
                                  />
                                  <span className={`font-bold text-slate-100 text-xs block leading-snug ${act.is_completed ? "line-through text-slate-500" : ""}`}>
                                    {act.subject}
                                  </span>
                                  {act.priority === "high" && (
                                    <span className="text-[8px] font-mono uppercase bg-rose-500/10 text-rose-400 border border-rose-900/60 px-1 rounded font-bold">
                                      High
                                    </span>
                                  )}
                                </div>
                                <p className="text-[11px] text-slate-400 leading-relaxed font-sans">{act.description}</p>
                                
                                {/* Render internal attachments if any */}
                                {act.attachments && act.attachments.length > 0 && (
                                  <div className="flex flex-wrap gap-2 pt-2">
                                    {act.attachments.map((f: any, idx: number) => (
                                      <div key={idx} className="bg-slate-950/60 border border-slate-900 rounded p-1.5 flex items-center gap-1.5 text-[9px] text-slate-400 font-mono">
                                        <Paperclip size={10} className="text-indigo-400" />
                                        <span>{f.name} ({f.size})</span>
                                      </div>
                                    ))}
                                  </div>
                                )}
                              </div>

                              <div className="text-right shrink-0 space-y-1">
                                <span className="text-[9px] font-mono text-slate-500 block">{act.created_at}</span>
                                <span className="text-[10px] font-mono block text-slate-400 font-bold">By: {act.user_name}</span>
                                <span className={`text-[9px] font-mono uppercase font-bold block ${
                                  act.is_completed ? "text-emerald-400" : "text-amber-500"
                                }`}>
                                  {act.due_at}
                                </span>
                              </div>
                            </div>

                            {/* Actions bar at bottom of card */}
                            <div className="flex items-center justify-between gap-4 mt-3 pt-2 border-t border-slate-900/60">
                              <span className="text-[9px] font-mono text-slate-600">ID: {act.id}</span>
                              <div className="flex items-center gap-2">
                                {act.type === "follow_up_task" && (
                                  <button
                                    onClick={() => toggleComplete(act.id)}
                                    className={`px-2.5 py-1 rounded text-[9px] font-mono uppercase font-bold transition-all border ${
                                      act.is_completed 
                                        ? "bg-slate-950 text-slate-500 border-slate-900" 
                                        : "bg-emerald-600/10 hover:bg-emerald-600/25 text-emerald-400 border-emerald-900/60"
                                    }`}
                                  >
                                    {act.is_completed ? "✓ Completed" : "Mark Done"}
                                  </button>
                                )}
                              </div>
                            </div>
                          </div>
                        </div>
                      );
                    })
                  )}
                </div>
              </div>
            )}

            {/* VIEW 2: Follow-up Tasks List */}
            {activeTab === "tasks" && (
              <div className="space-y-4 text-xs font-sans">
                <div className="border-b border-slate-900 pb-2 flex justify-between items-center">
                  <div>
                    <h4 className="text-sm font-bold text-slate-200">Follow-up Task Board</h4>
                    <p className="text-[10px] text-slate-500">View outstanding actions needing completion</p>
                  </div>
                  <div className="flex gap-2">
                    <button
                      onClick={handleSelectAll}
                      className="px-2.5 py-1 bg-slate-900 hover:bg-slate-850 text-[10px] text-slate-300 font-semibold border border-slate-800 rounded font-mono uppercase"
                    >
                      {selectedIds.length === activities.length ? "Deselect All" : "Select All"}
                    </button>
                  </div>
                </div>

                <div className="space-y-2">
                  {activities.filter(a => a.type === "follow_up_task").map(task => {
                    const isOverdue = task.due_at.toLowerCase().includes("overdue");
                    return (
                      <div key={task.id} className={`p-3.5 rounded-xl border flex items-center justify-between gap-4 transition-all ${
                        task.is_completed 
                          ? "bg-slate-900/10 border-slate-900 opacity-65" 
                          : "bg-slate-900/20 border-slate-800 hover:border-slate-750"
                      }`}>
                        <div className="flex items-center gap-3 min-w-0">
                          <button
                            onClick={() => toggleComplete(task.id)}
                            className={`w-5 h-5 rounded border flex items-center justify-center transition-all ${
                              task.is_completed 
                                ? "bg-emerald-600 border-emerald-500 text-white" 
                                : "border-slate-750 bg-slate-950 hover:border-indigo-500"
                            }`}
                          >
                            {task.is_completed && <Check size={12} />}
                          </button>
                          <div className="min-w-0 space-y-0.5">
                            <span className={`font-bold block text-xs ${task.is_completed ? "line-through text-slate-500" : "text-slate-200"}`}>
                              {task.subject}
                            </span>
                            <span className="text-[10px] text-slate-400 block truncate">{task.description}</span>
                          </div>
                        </div>

                        <div className="flex items-center gap-3 shrink-0">
                          <span className={`text-[9px] font-mono uppercase px-2 py-0.5 rounded font-extrabold border ${
                            task.priority === "high" 
                              ? "bg-rose-950/80 text-rose-400 border-rose-900/60" 
                              : "bg-slate-950 text-slate-400 border-slate-850"
                          }`}>
                            {task.priority} Priority
                          </span>
                          <span className={`text-[10px] font-mono font-bold ${
                            isOverdue ? "text-rose-400 animate-pulse" : "text-slate-500"
                          }`}>
                            {task.due_at}
                          </span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            {/* VIEW 3: Calendar Grid View */}
            {activeTab === "calendar" && (
              <div className="space-y-4">
                <div className="border-b border-slate-900 pb-2">
                  <h4 className="text-sm font-bold text-slate-200">Scheduled Actions Calendar</h4>
                  <p className="text-[10px] text-slate-500">Chronological schedule mapping month views</p>
                </div>

                {/* Simulated Grid Calendars */}
                <div className="grid grid-cols-7 gap-1 text-center text-[10px] font-mono pt-2">
                  {["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"].map(d => (
                    <div key={d} className="text-slate-500 uppercase font-bold py-1">{d}</div>
                  ))}
                  
                  {Array.from({ length: 31 }).map((_, idx) => {
                    const day = idx + 1;
                    const hasCall = day === 3;
                    const hasMeeting = day === 15;
                    const hasTask = day === 28;
                    return (
                      <div key={idx} className="bg-slate-900/30 border border-slate-900 rounded p-2 min-h-[55px] flex flex-col justify-between items-start">
                        <span className="text-slate-600 font-bold text-[9px]">{day}</span>
                        
                        {/* Interactive Dots for dates */}
                        <div className="w-full space-y-0.5">
                          {hasCall && (
                            <div className="bg-emerald-900/50 text-emerald-400 border border-emerald-800/40 text-[7px] p-0.5 rounded truncate font-mono text-left font-bold uppercase">
                              📞 Call
                            </div>
                          )}
                          {hasMeeting && (
                            <div className="bg-blue-900/50 text-blue-400 border border-blue-800/40 text-[7px] p-0.5 rounded truncate font-mono text-left font-bold uppercase">
                              🎥 Meet
                            </div>
                          )}
                          {hasTask && (
                            <div className="bg-rose-900/50 text-rose-400 border border-rose-800/40 text-[7px] p-0.5 rounded truncate font-mono text-left font-bold uppercase">
                              ⚠️ OVERDUE
                            </div>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            {/* VIEW 4: Notes Version Manager */}
            {activeTab === "notes" && (
              <div className="space-y-6 text-xs">
                <div className="border-b border-slate-900 pb-2">
                  <h4 className="text-sm font-bold text-slate-200">Polymorphic Rich Notes Manager</h4>
                  <p className="text-[10px] text-slate-500">Edit, save versions, track logs audit trails, and reply to note threads.</p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                  {/* Left Notes Editor Column (7 cols) */}
                  <div className="lg:col-span-8 space-y-4">
                    <div className="space-y-2">
                      <div className="flex justify-between items-center bg-slate-900 p-2.5 rounded-lg border border-slate-800">
                        <div className="flex items-center gap-2">
                          <span className="text-[10px] font-mono bg-indigo-900 text-indigo-300 px-2 py-0.5 rounded font-bold uppercase">
                            Version {selectedNote.version}
                          </span>
                          <span className="text-[11px] text-slate-300 font-mono">Last updated: {selectedNote.updated_at} by {selectedNote.user}</span>
                        </div>
                        <button
                          onClick={handleSaveNoteVersion}
                          className="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[10px] font-bold uppercase font-mono"
                        >
                          Save New Version
                        </button>
                      </div>

                      <textarea
                        rows={10}
                        className="w-full bg-slate-950 border border-slate-800 rounded-xl p-4 text-slate-300 font-mono leading-relaxed focus:outline-none focus:border-indigo-500"
                        value={noteEditContent}
                        onChange={(e) => setNoteEditContent(e.target.value)}
                      />
                    </div>

                    {/* Replies thread */}
                    <div className="space-y-3 pt-3 border-t border-slate-900">
                      <span className="text-[10px] font-mono text-slate-500 uppercase tracking-widest block font-bold">Comments & Thread Logs ({selectedNote.replies.length})</span>
                      <div className="space-y-2">
                        {selectedNote.replies.map((rep: any) => (
                          <div key={rep.id} className="p-3 rounded-lg bg-slate-900 border border-slate-850 leading-relaxed">
                            <div className="flex justify-between items-center mb-1 text-[10px] font-mono">
                              <span className="font-bold text-slate-300">{rep.author}</span>
                              <span className="text-slate-500">{rep.date}</span>
                            </div>
                            <p className="text-slate-300 text-[11px]">{rep.text}</p>
                          </div>
                        ))}
                      </div>

                      {/* Add comment reply */}
                      <form onSubmit={handleAddReply} className="flex gap-2">
                        <input
                          type="text"
                          required
                          placeholder="Add comment / scoping update..."
                          value={newReplyText}
                          onChange={(e) => setNewReplyText(e.target.value)}
                          className="flex-1 bg-slate-950 border border-slate-800 rounded px-2.5 py-1.5 text-xs text-slate-300 focus:outline-none"
                        />
                        <button
                          type="submit"
                          className="px-3 py-1 bg-slate-900 hover:bg-slate-800 text-slate-300 font-bold font-mono border border-slate-800 rounded text-[10px] uppercase"
                        >
                          Reply
                        </button>
                      </form>
                    </div>
                  </div>

                  {/* Right History Audit Sidebar Column (4 cols) */}
                  <div className="lg:col-span-4 space-y-4">
                    <span className="text-[10px] font-mono text-slate-500 uppercase tracking-widest block font-bold">Version History Logs</span>
                    <div className="space-y-2.5">
                      <div className="p-3 rounded-xl border border-indigo-900/40 bg-indigo-950/20 space-y-1.5">
                        <div className="flex justify-between items-center text-[9px] font-mono font-bold uppercase text-indigo-400">
                          <span>Active Note Version {selectedNote.version}</span>
                          <span>Latest</span>
                        </div>
                        <p className="text-[10px] text-slate-400 truncate leading-snug">{selectedNote.content}</p>
                        <span className="text-[8px] font-mono text-slate-500 block">By: {selectedNote.user} &bull; Just now</span>
                      </div>

                      {selectedNote.history.map((hist: any, index: number) => (
                        <div key={index} className="p-3 rounded-xl border border-slate-900 bg-slate-950/50 space-y-1.5 opacity-65 hover:opacity-100 transition-opacity">
                          <div className="flex justify-between items-center text-[9px] font-mono font-bold uppercase text-slate-500">
                            <span>Note Version {hist.version}</span>
                            <span className="underline cursor-pointer text-indigo-400 hover:text-indigo-300" onClick={() => {
                              setNoteEditContent(hist.content);
                            }}>Restore Draft</span>
                          </div>
                          <p className="text-[10px] text-slate-500 truncate leading-snug">{hist.content}</p>
                          <span className="text-[8px] font-mono text-slate-600 block">By: {hist.user} &bull; {hist.date}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* VIEW 5: Company Accounts Hub */}
            {activeTab === "companies" && (
              <div className="space-y-6 text-xs">
                <div className="border-b border-slate-900 pb-2 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                  <div>
                    <h4 className="text-sm font-bold text-slate-200">Enterprise Company (Account) Manager</h4>
                    <p className="text-[10px] text-slate-500">Manage master profiles, hierarchy chains, multiple locations, and account health engines.</p>
                  </div>
                  <span className="bg-indigo-950 text-indigo-400 border border-indigo-900 px-2.5 py-0.5 rounded font-mono font-bold text-[9px] uppercase shrink-0">
                    4 Accounts Loaded
                  </span>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                  {/* Left Company selector (4 cols) */}
                  <div className="lg:col-span-4 space-y-2">
                    <span className="text-[10px] font-mono text-slate-500 uppercase tracking-widest block font-bold">Select Enterprise Account</span>
                    <div className="space-y-2 max-h-[500px] overflow-y-auto pr-1">
                      {companies.map((comp) => {
                        const isSelected = comp.id === selectedCompanyId;
                        let healthColor = "text-emerald-400 bg-emerald-500/10 border-emerald-900/60";
                        if (comp.health_score < 50) {
                          healthColor = "text-rose-400 bg-rose-500/10 border-rose-900/60";
                        } else if (comp.health_score < 80) {
                          healthColor = "text-amber-400 bg-amber-500/10 border-amber-900/60";
                        }

                        return (
                          <div
                            key={comp.id}
                            onClick={() => setSelectedCompanyId(comp.id)}
                            className={`p-3 rounded-xl border cursor-pointer transition-all ${
                              isSelected
                                ? "bg-indigo-950/20 border-indigo-500/60 shadow-lg shadow-indigo-950/30"
                                : "bg-slate-900/10 border-slate-900 hover:bg-slate-900/30 hover:border-slate-800"
                            }`}
                          >
                            <div className="flex justify-between items-start gap-2 mb-1">
                              <span className="font-bold text-slate-200 block truncate">{comp.name}</span>
                              <span className={`text-[8px] font-mono uppercase px-1.5 py-0.5 rounded border ${healthColor}`}>
                                {comp.health_status} ({comp.health_score})
                              </span>
                            </div>
                            <div className="flex justify-between items-center text-[9px] font-mono text-slate-500">
                              <span>{comp.domain}</span>
                              <span>{comp.locations.length} Locations</span>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>

                  {/* Right Details Panel (8 cols) */}
                  <div className="lg:col-span-8 bg-slate-900/20 border border-slate-850 rounded-xl p-5 space-y-5">
                    {/* Header Details */}
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-4 border-b border-slate-900">
                      <div>
                        <div className="flex items-center gap-2 mb-1">
                          <h3 className="text-base font-bold text-white">{activeCompany.name}</h3>
                          {activeCompany.trading_name && (
                            <span className="text-[10px] font-mono text-slate-400 font-bold">
                              t/a {activeCompany.trading_name}
                            </span>
                          )}
                        </div>
                        <div className="flex flex-wrap gap-x-4 gap-y-1 text-[10px] text-slate-400 font-mono">
                          <span>🌐 <a href={activeCompany.website} target="_blank" rel="noreferrer" className="hover:underline text-indigo-400">{activeCompany.website}</a></span>
                          <span>📞 {activeCompany.phone}</span>
                          <span>🏢 {activeCompany.industry_classification}</span>
                        </div>
                      </div>

                      <div className="flex gap-2">
                        <span className="bg-slate-950 border border-slate-800 px-2.5 py-1 rounded text-[10px] text-slate-300 font-mono">
                          Reg: {activeCompany.registration_number}
                        </span>
                        <span className="bg-slate-950 border border-slate-800 px-2.5 py-1 rounded text-[10px] text-slate-300 font-mono">
                          Tax: {activeCompany.tax_number}
                        </span>
                      </div>
                    </div>

                    {/* Sub tabs for Details */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                      {/* Left: Hierarchy & Core Data */}
                      <div className="space-y-4 md:col-span-1 border-r border-slate-900 pr-4">
                        <div>
                          <span className="text-[9px] font-mono text-indigo-400 uppercase tracking-wider block font-bold mb-2">Corporate Hierarchy</span>
                          
                          {/* Parent card */}
                          <div className="p-2.5 rounded bg-slate-950/80 border border-slate-900 space-y-1 mb-2">
                            <span className="text-[8px] font-mono text-slate-500 uppercase block font-bold">Parent Company:</span>
                            {activeCompany.parent_id ? (
                              <div className="flex justify-between items-center gap-1">
                                <span className="font-bold text-slate-300 truncate">{companies.find(c => c.id === activeCompany.parent_id)?.name}</span>
                                <button
                                  onClick={() => setSelectedCompanyId(activeCompany.parent_id)}
                                  className="text-[9px] font-mono text-indigo-400 hover:underline shrink-0"
                                >
                                  Jump &rarr;
                                </button>
                              </div>
                            ) : (
                              <span className="text-slate-600 block italic">Independent (No Parent)</span>
                            )}
                          </div>

                          {/* Subsidiaries list */}
                          <div className="p-2.5 rounded bg-slate-950/80 border border-slate-900 space-y-1">
                            <span className="text-[8px] font-mono text-slate-500 uppercase block font-bold">Subsidiaries / Branches:</span>
                            {companies.filter(c => c.parent_id === activeCompany.id).length > 0 ? (
                              <div className="space-y-1 pt-1">
                                {companies.filter(c => c.parent_id === activeCompany.id).map(sub => (
                                  <div key={sub.id} className="flex justify-between items-center gap-1">
                                    <span className="font-semibold text-slate-300 truncate">&bull; {sub.name}</span>
                                    <button
                                      onClick={() => setSelectedCompanyId(sub.id)}
                                      className="text-[9px] font-mono text-indigo-400 hover:underline shrink-0"
                                    >
                                      Jump &rarr;
                                    </button>
                                  </div>
                                ))}
                              </div>
                            ) : (
                              <span className="text-slate-600 block italic">No subsidiaries registered</span>
                            )}
                          </div>
                        </div>

                        {/* Metadata Box */}
                        <div className="p-3 bg-slate-950/40 border border-slate-900 rounded-lg space-y-1.5 font-mono text-[10px]">
                          <span className="text-[8px] font-mono text-slate-500 uppercase block font-bold mb-1">Company Demographics</span>
                          <div className="flex justify-between">
                            <span className="text-slate-500">Size Class:</span>
                            <span className="text-slate-300 capitalize font-bold">{activeCompany.company_size.replace('_', ' ')}</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-slate-500">Employees:</span>
                            <span className="text-slate-300 font-bold">{activeCompany.employees_count} pax</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-slate-500">Revenue:</span>
                            <span className="text-indigo-400 font-bold">{activeCompany.annual_revenue}</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-slate-500">Currency / Lang:</span>
                            <span className="text-slate-300 font-bold">{activeCompany.currency} / {activeCompany.preferred_language}</span>
                          </div>
                        </div>
                      </div>

                      {/* Middle: Locations Manager */}
                      <div className="space-y-3 md:col-span-1 border-r border-slate-900 pr-4">
                        <div className="flex justify-between items-center">
                          <span className="text-[9px] font-mono text-indigo-400 uppercase tracking-wider block font-bold">Locations ({activeCompany.locations.length})</span>
                        </div>

                        {/* List current locations */}
                        <div className="space-y-1.5 max-h-[220px] overflow-y-auto pr-1">
                          {activeCompany.locations.map((loc: any) => (
                            <div key={loc.id} className="p-2 rounded bg-slate-950/60 border border-slate-900 flex justify-between items-start">
                              <div className="space-y-0.5">
                                <div className="flex items-center gap-1">
                                  <span className="font-bold text-slate-200">{loc.name}</span>
                                  <span className="text-[7px] font-mono uppercase bg-slate-900 text-slate-400 px-1 rounded">
                                    {loc.type}
                                  </span>
                                </div>
                                <span className="text-slate-500 block text-[9px]">{loc.city}, {loc.country}</span>
                              </div>
                              <button
                                onClick={() => {
                                  setCompanies(prev => prev.map(c => {
                                    if (c.id === activeCompany.id) {
                                      return {
                                        ...c,
                                        locations: c.locations.filter((l: any) => l.id !== loc.id)
                                      };
                                    }
                                    return c;
                                  }));
                                }}
                                className="text-[9px] text-rose-500 hover:text-rose-400 shrink-0 font-bold"
                              >
                                &times;
                              </button>
                            </div>
                          ))}
                        </div>

                        {/* Add Location Sub-Form */}
                        <div className="bg-slate-950/40 border border-slate-900 p-2.5 rounded-lg space-y-2">
                          <span className="text-[8px] font-mono text-slate-400 uppercase block font-bold">Add Location</span>
                          <input
                            type="text"
                            placeholder="Location Name (e.g. Eldoret Branch)"
                            value={newLocName}
                            onChange={(e) => setNewLocName(e.target.value)}
                            className="w-full bg-slate-950 border border-slate-850 rounded px-2 py-1 text-slate-300 text-[10px]"
                          />
                          <div className="grid grid-cols-2 gap-1.5">
                            <input
                              type="text"
                              placeholder="City"
                              value={newLocCity}
                              onChange={(e) => setNewLocCity(e.target.value)}
                              className="w-full bg-slate-950 border border-slate-850 rounded px-2 py-1 text-slate-300 text-[10px]"
                            />
                            <input
                              type="text"
                              placeholder="Country"
                              value={newLocCountry}
                              onChange={(e) => setNewLocCountry(e.target.value)}
                              className="w-full bg-slate-950 border border-slate-850 rounded px-2 py-1 text-slate-300 text-[10px]"
                            />
                          </div>
                          <select
                            value={newLocType}
                            onChange={(e) => setNewLocType(e.target.value)}
                            className="w-full bg-slate-950 border border-slate-850 rounded px-2 py-1 text-slate-300 text-[10px]"
                          >
                            <option value="branch">Branch</option>
                            <option value="warehouse">Warehouse</option>
                            <option value="billing">Billing Address</option>
                            <option value="shipping">Shipping Address</option>
                          </select>
                          <button
                            type="button"
                            onClick={() => {
                              if (!newLocName.trim() || !newLocCity.trim() || !newLocCountry.trim()) return;
                              const newLoc = {
                                id: `LOC-${Date.now()}`,
                                type: newLocType,
                                name: newLocName,
                                city: newLocCity,
                                country: newLocCountry
                              };

                              setCompanies(prev => prev.map(c => {
                                if (c.id === activeCompany.id) {
                                  return {
                                    ...c,
                                    locations: [...c.locations, newLoc]
                                  };
                                }
                                return c;
                              }));

                              setNewLocName("");
                              setNewLocCity("");
                              setNewLocCountry("");
                            }}
                            className="w-full py-1 bg-indigo-600/10 hover:bg-indigo-600 text-indigo-300 hover:text-white font-bold rounded text-[9px] uppercase font-mono border border-indigo-900/60 animate-colors"
                          >
                            Save Location
                          </button>
                        </div>
                      </div>

                      {/* Right: Health Engine Panel */}
                      <div className="space-y-4 md:col-span-1">
                        <span className="text-[9px] font-mono text-indigo-400 uppercase tracking-wider block font-bold">Account Health Engine</span>

                        {/* Health Status Dashboard card */}
                        <div className="p-3 bg-slate-950 border border-slate-900 rounded-lg text-center space-y-2">
                          <span className="text-[9px] font-mono text-slate-500 uppercase block font-bold">Health Score Card</span>
                          <div className="flex items-baseline justify-center gap-1.5">
                            <span className="text-2xl font-bold font-mono text-white">{activeCompany.health_score}</span>
                            <span className="text-[10px] text-slate-500">/100</span>
                          </div>
                          
                          {/* Badge Status */}
                          <div className="inline-block px-2.5 py-0.5 rounded font-mono font-bold text-[9px] uppercase bg-slate-900">
                            {activeCompany.health_status === "Healthy" && <span className="text-emerald-400">● {activeCompany.health_status}</span>}
                            {activeCompany.health_status === "Warning" && <span className="text-amber-400">● {activeCompany.health_status}</span>}
                            {activeCompany.health_status === "Critical" && <span className="text-rose-400">● {activeCompany.health_status}</span>}
                          </div>

                          {/* Score breakdown logs */}
                          <div className="border-t border-slate-900 pt-2 text-left space-y-1 text-[9px] font-mono text-slate-400">
                            <div className="flex justify-between">
                              <span>Engagement Points:</span>
                              <span className="text-slate-200">+{activeCompany.health_breakdown.engagement}</span>
                            </div>
                            <div className="flex justify-between">
                              <span>Opportunities Points:</span>
                              <span className="text-slate-200">+{activeCompany.health_breakdown.opportunities}</span>
                            </div>
                            <div className="flex justify-between">
                              <span>Outstanding Tasks:</span>
                              <span className={activeCompany.health_breakdown.outstanding_tasks < 0 ? "text-rose-400" : "text-slate-200"}>
                                {activeCompany.health_breakdown.outstanding_tasks}
                              </span>
                            </div>
                          </div>
                        </div>

                        {/* Interactive health score simulation triggers */}
                        <div className="space-y-1.5 bg-slate-950/30 p-2.5 border border-slate-900 rounded-lg">
                          <span className="text-[8px] font-mono text-slate-500 uppercase block font-bold mb-1">Simulate Shifts</span>
                          <button
                            onClick={() => {
                              setCompanies(prev => prev.map(c => {
                                if (c.id === activeCompany.id) {
                                  const newOpp = c.health_breakdown.opportunities + 10;
                                  const totalScore = Math.min(100, Math.max(0, 70 + c.health_breakdown.engagement + newOpp + c.health_breakdown.outstanding_tasks));
                                  const status = totalScore < 50 ? "Critical" : totalScore < 80 ? "Warning" : "Healthy";
                                  return {
                                    ...c,
                                    health_score: totalScore,
                                    health_status: status,
                                    health_breakdown: { ...c.health_breakdown, opportunities: newOpp }
                                  };
                                }
                                return c;
                              }));
                            }}
                            className="w-full text-left py-1 px-2 hover:bg-slate-900 text-slate-300 hover:text-white rounded text-[9px] font-mono block"
                          >
                            📈 Add Won Opportunity (+10)
                          </button>
                          <button
                            onClick={() => {
                              setCompanies(prev => prev.map(c => {
                                if (c.id === activeCompany.id) {
                                  const newEng = c.health_breakdown.engagement + 3;
                                  const totalScore = Math.min(100, Math.max(0, 70 + newEng + c.health_breakdown.opportunities + c.health_breakdown.outstanding_tasks));
                                  const status = totalScore < 50 ? "Critical" : totalScore < 80 ? "Warning" : "Healthy";
                                  return {
                                    ...c,
                                    health_score: totalScore,
                                    health_status: status,
                                    health_breakdown: { ...c.health_breakdown, engagement: newEng }
                                  };
                                }
                                return c;
                              }));
                            }}
                            className="w-full text-left py-1 px-2 hover:bg-slate-900 text-slate-300 hover:text-white rounded text-[9px] font-mono block"
                          >
                            📞 Log Completed Call (+3)
                          </button>
                          <button
                            onClick={() => {
                              setCompanies(prev => prev.map(c => {
                                if (c.id === activeCompany.id) {
                                  const newOut = c.health_breakdown.outstanding_tasks - 10;
                                  const totalScore = Math.min(100, Math.max(0, 70 + c.health_breakdown.engagement + c.health_breakdown.opportunities + newOut));
                                  const status = totalScore < 50 ? "Critical" : totalScore < 80 ? "Warning" : "Healthy";
                                  return {
                                    ...c,
                                    health_score: totalScore,
                                    health_status: status,
                                    health_breakdown: { ...c.health_breakdown, outstanding_tasks: newOut }
                                  };
                                }
                                return c;
                              }));
                            }}
                            className="w-full text-left py-1 px-2 hover:bg-slate-900 text-rose-400 hover:text-rose-300 rounded text-[9px] font-mono block"
                          >
                            ⚠️ Inject Overdue Task (-10)
                          </button>
                        </div>
                      </div>
                    </div>

                    {/* Simulation Outbox Feed */}
                    <div className="bg-slate-950 p-3.5 border border-slate-900 rounded-xl space-y-2">
                      <div className="flex justify-between items-center pb-1.5 border-b border-slate-900">
                        <span className="text-[9px] font-mono text-emerald-400 uppercase tracking-wider font-extrabold block">
                          ⚡ LIVE TRANSACTIONAL OUTBOX MONITOR (EVENT_OUTBOX)
                        </span>
                        <span className="text-[8px] font-mono bg-emerald-950 text-emerald-400 px-1.5 rounded animate-pulse">
                          listening...
                        </span>
                      </div>
                      <div className="space-y-1.5 font-mono text-[9px] leading-relaxed max-h-[80px] overflow-y-auto">
                        <div className="text-slate-400">
                          <span className="text-emerald-500 font-bold">[STORED]</span> crm.company.created &rarr; ID: {activeCompany.id} &bull; payload: name: {activeCompany.name} &bull; Org isolation ID: Acme_Enterprise
                        </div>
                        <div className="text-slate-400">
                          <span className="text-emerald-500 font-bold">[STORED]</span> crm.company.updated &rarr; ID: {activeCompany.id} &bull; payload: health_score: {activeCompany.health_score} &bull; health_status: {activeCompany.health_status}
                        </div>
                        <div className="text-slate-500 text-[8px] italic">
                          * Updates on the screen trigger background job dispatches in our Laravel queues, verifying high integrity isolation.
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* VIEW 6: Enterprise Contact Management Engine */}
            {activeTab === "contacts" && (
              <div className="space-y-6">
                {/* Header & Mode Switches */}
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-4 border-b border-slate-900">
                  <div>
                    <h3 className="text-base font-bold text-white flex items-center gap-2">
                      <UserCheck size={18} className="text-indigo-400" />
                      Enterprise Contact Management Engine
                    </h3>
                    <p className="text-xs text-slate-400">Salesforce/HubSpot-grade customer identity, 360° graph, and GDPR consent center.</p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <button
                      onClick={() => setContactViewMode("directory")}
                      className={`px-3 py-1.5 rounded-lg text-xs font-semibold font-mono transition-all ${
                        contactViewMode === "directory" || contactViewMode === "profile"
                          ? "bg-indigo-600 text-white"
                          : "bg-slate-900 text-slate-400 hover:text-slate-200 border border-slate-800"
                      }`}
                    >
                      📁 Directory & 360° View
                    </button>
                    <button
                      onClick={() => setContactViewMode("merge")}
                      className={`px-3 py-1.5 rounded-lg text-xs font-semibold font-mono transition-all ${
                        contactViewMode === "merge"
                          ? "bg-indigo-600 text-white"
                          : "bg-slate-900 text-slate-400 hover:text-slate-200 border border-slate-800"
                      }`}
                    >
                      🤝 Merge Wizard
                    </button>
                    <button
                      onClick={() => setContactViewMode("import")}
                      className={`px-3 py-1.5 rounded-lg text-xs font-semibold font-mono transition-all ${
                        contactViewMode === "import"
                          ? "bg-indigo-600 text-white"
                          : "bg-slate-900 text-slate-400 hover:text-slate-200 border border-slate-800"
                      }`}
                    >
                      📥 CSV/JSON Import & Rollback
                    </button>
                  </div>
                </div>

                {/* Sub-view 1: Directory & 360 View */}
                {(contactViewMode === "directory" || contactViewMode === "profile") && (
                  <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
                    {/* LEFT PANEL: Directory list (5 Cols) */}
                    <div className="xl:col-span-5 space-y-4">
                      {/* Search & Sidebar Filters */}
                      <div className="bg-slate-900/40 border border-slate-900 p-4 rounded-xl space-y-3">
                        <div className="relative">
                          <Search className="absolute left-2.5 top-2.5 text-slate-500" size={14} />
                          <input
                            type="text"
                            placeholder="Search contacts (name, email, job...)"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="w-full bg-slate-950 border border-slate-800 rounded-lg pl-8 pr-3 py-2 text-xs focus:outline-none focus:border-indigo-500 text-slate-300 font-mono"
                          />
                        </div>

                        {/* Dropdown Filters Grid */}
                        <div className="grid grid-cols-2 gap-2 text-[10px]">
                          <div>
                            <span className="text-slate-500 font-mono block mb-1">TIER:</span>
                            <select
                              value={tierFilter}
                              onChange={(e) => setTierFilter(e.target.value)}
                              className="w-full bg-slate-950 border border-slate-850 rounded p-1.5 text-slate-300 text-[10px] focus:outline-none focus:border-indigo-500 font-mono"
                            >
                              <option value="all">All Tiers</option>
                              <option value="Tier A">Tier A (High-Val)</option>
                              <option value="Tier B">Tier B (Mid-Val)</option>
                              <option value="Tier C">Tier C (Low-Val)</option>
                            </select>
                          </div>
                          <div>
                            <span className="text-slate-500 font-mono block mb-1">SEGMENT:</span>
                            <select
                              value={segmentFilter}
                              onChange={(e) => setSegmentFilter(e.target.value)}
                              className="w-full bg-slate-950 border border-slate-850 rounded p-1.5 text-slate-300 text-[10px] focus:outline-none focus:border-indigo-500 font-mono"
                            >
                              <option value="all">All Segments</option>
                              <option value="Enterprise">Enterprise</option>
                              <option value="Mid-Market">Mid-Market</option>
                              <option value="SMB">SMB</option>
                            </select>
                          </div>
                          <div>
                            <span className="text-slate-500 font-mono block mb-1">STAGE:</span>
                            <select
                              value={lifecycleFilter}
                              onChange={(e) => setLifecycleFilter(e.target.value)}
                              className="w-full bg-slate-950 border border-slate-850 rounded p-1.5 text-slate-300 text-[10px] focus:outline-none focus:border-indigo-500 font-mono"
                            >
                              <option value="all">All Stages</option>
                              <option value="Lead">Lead</option>
                              <option value="MQL">MQL</option>
                              <option value="SQL">SQL</option>
                              <option value="Customer">Customer</option>
                            </select>
                          </div>
                          <div>
                            <span className="text-slate-500 font-mono block mb-1">HEALTH SCORE:</span>
                            <select
                              value={healthFilter}
                              onChange={(e) => setHealthFilter(e.target.value)}
                              className="w-full bg-slate-950 border border-slate-850 rounded p-1.5 text-slate-300 text-[10px] focus:outline-none focus:border-indigo-500 font-mono"
                            >
                              <option value="all">All Health</option>
                              <option value="Healthy">Healthy (&ge;80)</option>
                              <option value="Warning">Warning (50-79)</option>
                              <option value="Critical">Critical (&lt;50)</option>
                            </select>
                          </div>
                        </div>
                      </div>

                      {/* Bulk Actions Console */}
                      {selectedContactIds.length > 0 && (
                        <div className="bg-indigo-950/20 border border-indigo-900/60 p-3 rounded-xl flex items-center justify-between text-xs font-mono">
                          <span className="text-slate-300 font-bold">{selectedContactIds.length} Selected</span>
                          <div className="flex gap-1.5">
                            <button
                              onClick={() => {
                                setContacts(prev => prev.map(c => 
                                  selectedContactIds.includes(c.id) 
                                    ? { ...c, tier: "Tier A", health_score: Math.min(100, c.health_score + 5) } 
                                    : c
                                ));
                                triggerEventLog("crm.contact.bulk_tag", "Multiple Contacts");
                                alert("✓ Promoted selected contacts to Tier A (+5 health bonus)");
                              }}
                              className="px-2 py-1 bg-indigo-600/20 text-indigo-400 border border-indigo-500/30 rounded text-[9px] hover:bg-indigo-600/40"
                            >
                              🏷️ Tier A
                            </button>
                            <button
                              onClick={() => {
                                setContacts(prev => prev.map(c => 
                                  selectedContactIds.includes(c.id) 
                                    ? { ...c, lifecycle_stage: "Customer" } 
                                    : c
                                ));
                                triggerEventLog("crm.contact.bulk_stage", "Multiple Contacts");
                                alert("✓ Lifecycle stage updated to Customer");
                              }}
                              className="px-2 py-1 bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 rounded text-[9px] hover:bg-emerald-600/40"
                            >
                              ✓ Customer
                            </button>
                            <button
                              onClick={() => {
                                setContacts(prev => prev.filter(c => !selectedContactIds.includes(c.id)));
                                setSelectedContactIds([]);
                                triggerEventLog("crm.contact.bulk_delete", "Multiple Contacts");
                                alert("✓ Selected contacts archived/deleted from the directory.");
                              }}
                              className="px-2 py-1 bg-rose-600/20 text-rose-400 border border-rose-500/30 rounded text-[9px] hover:bg-rose-600/40"
                            >
                              Delete
                            </button>
                          </div>
                        </div>
                      )}

                      {/* Contacts List Feed */}
                      <div className="space-y-2.5 max-h-[600px] overflow-y-auto pr-1">
                        {contacts
                          .filter(c => {
                            const matchSearch = (c.first_name + " " + c.last_name + " " + c.email + " " + c.job_title + " " + (c.company_name || "")).toLowerCase().includes(searchQuery.toLowerCase());
                            const matchTier = tierFilter === "all" || c.tier === tierFilter;
                            const matchSegment = segmentFilter === "all" || c.segment === segmentFilter;
                            const matchLifecycle = lifecycleFilter === "all" || c.lifecycle_stage === lifecycleFilter;
                            const matchHealth = healthFilter === "all" || c.health_status === healthFilter;
                            return matchSearch && matchTier && matchSegment && matchLifecycle && matchHealth;
                          })
                          .map((c) => {
                            const isSelected = selectedContactId === c.id;
                            const isMultiChecked = selectedContactIds.includes(c.id);
                            return (
                              <div
                                key={c.id}
                                onClick={() => {
                                  setSelectedContactId(c.id);
                                  setContactViewMode("profile");
                                }}
                                className={`p-4 rounded-xl border transition-all cursor-pointer ${
                                  isSelected
                                    ? "bg-slate-900 border-indigo-500/60 shadow-lg shadow-indigo-600/10"
                                    : "bg-slate-900/20 border-slate-900 hover:bg-slate-900/40 hover:border-slate-800"
                                }`}
                              >
                                <div className="flex gap-3 items-start">
                                  {/* Multi-select box */}
                                  <input
                                    type="checkbox"
                                    checked={isMultiChecked}
                                    onClick={(e) => e.stopPropagation()}
                                    onChange={() => {
                                      if (isMultiChecked) {
                                        setSelectedContactIds(prev => prev.filter(id => id !== c.id));
                                      } else {
                                        setSelectedContactIds(prev => [...prev, c.id]);
                                      }
                                    }}
                                    className="rounded border-slate-800 bg-slate-950 text-indigo-600 focus:ring-0 mt-1"
                                  />

                                  {/* Profile Avatar & Primary Details */}
                                  <div className="flex-1 space-y-1 min-w-0">
                                    <div className="flex justify-between items-start gap-2">
                                      <h4 className="font-bold text-slate-100 text-xs truncate">
                                        {c.first_name} {c.middle_name ? c.middle_name + " " : ""}{c.last_name}
                                      </h4>
                                      <div className="flex items-center gap-1 shrink-0">
                                        <span className={`text-[8px] font-mono px-1.5 py-0.5 rounded border ${
                                          c.tier === "Tier A" ? "bg-amber-950/40 text-amber-400 border-amber-900/50" :
                                          c.tier === "Tier B" ? "bg-indigo-950/40 text-indigo-400 border-indigo-900/50" :
                                          "bg-slate-900 text-slate-400 border-slate-800"
                                        }`}>
                                          {c.tier}
                                        </span>
                                        <span className={`text-[8px] font-mono px-1.5 py-0.5 rounded ${
                                          c.health_status === "Healthy" ? "bg-emerald-950 text-emerald-400" :
                                          c.health_status === "Warning" ? "bg-amber-950 text-amber-400" :
                                          "bg-rose-950 text-rose-400"
                                        }`}>
                                          Score: {c.health_score}
                                        </span>
                                      </div>
                                    </div>
                                    <p className="text-[10px] text-slate-400 truncate">{c.job_title}</p>
                                    <div className="flex items-center justify-between pt-1.5 text-[9px] font-mono text-slate-500">
                                      <span className="truncate">{c.company_name || "Independent"}</span>
                                      <span className="bg-slate-950 text-slate-400 px-1.5 rounded">{c.lifecycle_stage}</span>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            );
                          })}
                      </div>
                    </div>

                    {/* RIGHT PANEL: 360 Degree Profile Card (7 Cols) */}
                    <div className="xl:col-span-7">
                      {(() => {
                        const contact = contacts.find(c => c.id === selectedContactId);
                        if (!contact) {
                          return (
                            <div className="h-full border border-dashed border-slate-800 rounded-2xl flex flex-col items-center justify-center p-12 text-center text-slate-500 text-xs font-mono">
                              <Eye size={24} className="mb-2 text-slate-600 animate-pulse" />
                              Select a contact to engage the 360° Profile Viewer.
                            </div>
                          );
                        }

                        return (
                          <div className="space-y-6">
                            {/* 360 Degree Profile Header */}
                            <div className="bg-slate-900/40 border border-slate-900 rounded-2xl p-5 relative overflow-hidden">
                              <div className="absolute top-0 right-0 w-32 h-32 bg-indigo-500/5 rounded-full blur-3xl pointer-events-none" />
                              
                              <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-4 border-b border-slate-950">
                                <div className="flex gap-4 items-center">
                                  <div className="w-12 h-12 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-display font-black text-lg shadow-lg shadow-indigo-600/10">
                                    {contact.first_name[0]}{contact.last_name[0]}
                                  </div>
                                  <div>
                                    <div className="flex items-center gap-2">
                                      <h3 className="text-base font-bold text-white">
                                        {contact.first_name} {contact.middle_name ? contact.middle_name + " " : ""}{contact.last_name}
                                      </h3>
                                      <span className="bg-indigo-500/10 text-indigo-400 text-[9px] font-mono border border-indigo-500/20 px-1.5 rounded-full">
                                        {contact.segment}
                                      </span>
                                    </div>
                                    <p className="text-xs text-slate-400">{contact.job_title} &bull; <span className="text-indigo-300 font-semibold">{contact.company_name}</span></p>
                                  </div>
                                </div>

                                <div className="flex gap-1.5 font-mono text-[9px]">
                                  <div className="bg-slate-950 px-2.5 py-1.5 rounded border border-slate-900 text-slate-400">
                                    STATUS: <span className="text-emerald-400 font-bold">{contact.status}</span>
                                  </div>
                                  <div className="bg-slate-950 px-2.5 py-1.5 rounded border border-slate-900 text-slate-400">
                                    CLASS: <span className="text-indigo-400 font-bold">{contact.classification || "High Touch"}</span>
                                  </div>
                                </div>
                              </div>

                              {/* Key Metrics Quick Ribbon */}
                              <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 text-center">
                                <div className="bg-slate-950/40 p-2 border border-slate-950 rounded-lg">
                                  <span className="text-[8px] font-mono text-slate-500 block uppercase">Health Status</span>
                                  <span className={`text-xs font-bold font-mono ${
                                    contact.health_status === "Healthy" ? "text-emerald-400" :
                                    contact.health_status === "Warning" ? "text-amber-400" : "text-rose-400"
                                  }`}>
                                    ● {contact.health_status}
                                  </span>
                                </div>
                                <div className="bg-slate-950/40 p-2 border border-slate-950 rounded-lg">
                                  <span className="text-[8px] font-mono text-slate-500 block uppercase">GDPR Consent</span>
                                  <span className={`text-xs font-bold font-mono ${
                                    contact.gdpr_consent_status === "granted" ? "text-emerald-400" : "text-slate-400"
                                  }`}>
                                    {contact.gdpr_consent_status === "granted" ? "Granted" : "Not asked"}
                                  </span>
                                </div>
                                <div className="bg-slate-950/40 p-2 border border-slate-950 rounded-lg">
                                  <span className="text-[8px] font-mono text-slate-500 block uppercase">Buying Role</span>
                                  <span className="text-xs font-bold font-mono text-slate-300">
                                    {contact.buying_role || "User"}
                                  </span>
                                </div>
                                <div className="bg-slate-950/40 p-2 border border-slate-950 rounded-lg">
                                  <span className="text-[8px] font-mono text-slate-500 block uppercase">Owner (RBAC)</span>
                                  <span className="text-xs font-bold font-mono text-indigo-400">
                                    Agent Juan (Admin)
                                  </span>
                                </div>
                              </div>
                            </div>

                            {/* 360 Tabs Section */}
                            <div className="bg-slate-900/20 border border-slate-900 rounded-2xl p-5 space-y-5">
                              {/* Multi-address, Graph, Consent & Health Sections */}
                              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* COLUMN 1: Personal & Communication Channels */}
                                <div className="space-y-4">
                                  <div className="space-y-2">
                                    <h4 className="text-xs font-bold text-slate-300 border-b border-slate-900 pb-1.5 uppercase font-mono tracking-wider">
                                      📞 Communication Channels
                                    </h4>
                                    <div className="space-y-1.5 text-xs">
                                      <div className="flex justify-between font-mono text-[10px]">
                                        <span className="text-slate-500">Email (Work):</span>
                                        <span className="text-slate-300">{contact.email}</span>
                                      </div>
                                      {contact.personal_email && (
                                        <div className="flex justify-between font-mono text-[10px]">
                                          <span className="text-slate-500">Email (Pers):</span>
                                          <span className="text-slate-300">{contact.personal_email}</span>
                                        </div>
                                      )}
                                      <div className="flex justify-between font-mono text-[10px]">
                                        <span className="text-slate-500">Work Phone:</span>
                                        <span className="text-slate-300">{contact.phone}</span>
                                      </div>
                                      {contact.whatsapp && (
                                        <div className="flex justify-between font-mono text-[10px]">
                                          <span className="text-slate-500">WhatsApp:</span>
                                          <span className="text-slate-300 text-emerald-400">{contact.whatsapp}</span>
                                        </div>
                                      )}
                                      {contact.telegram && (
                                        <div className="flex justify-between font-mono text-[10px]">
                                          <span className="text-slate-500">Telegram:</span>
                                          <span className="text-slate-300 text-sky-400">{contact.telegram}</span>
                                        </div>
                                      )}
                                      <div className="flex justify-between font-mono text-[10px]">
                                        <span className="text-slate-500">Preferred Lang:</span>
                                        <span className="text-slate-300">{contact.preferred_language || "English"}</span>
                                      </div>
                                      <div className="flex justify-between font-mono text-[10px]">
                                        <span className="text-slate-500">Timezone:</span>
                                        <span className="text-slate-300">{contact.timezone || "UTC"}</span>
                                      </div>
                                    </div>
                                  </div>

                                  {/* Custom Fields (Unlimited!) */}
                                  <div className="space-y-2 bg-slate-950/40 p-3 rounded-xl border border-slate-900">
                                    <div className="flex justify-between items-center border-b border-slate-900 pb-1">
                                      <span className="text-[10px] font-bold text-slate-300 uppercase font-mono tracking-wider">
                                        🗃️ Tenant Custom Fields
                                      </span>
                                      <button
                                        onClick={() => {
                                          const key = prompt("Enter Custom Field Key Name (e.g. legacy_id, segment_owner):");
                                          if (!key) return;
                                          const val = prompt(`Enter Value for '${key}':`);
                                          if (!val) return;
                                          setContacts(prev => prev.map(c => {
                                            if (c.id === contact.id) {
                                              return { ...c, custom_fields: { ...c.custom_fields, [key]: val } };
                                            }
                                            return c;
                                          }));
                                          triggerEventLog("crm.contact.custom_fields_updated", contact.first_name + " " + contact.last_name);
                                          alert(`✓ Added custom field: ${key} = ${val}`);
                                        }}
                                        className="text-[9px] text-indigo-400 font-mono font-bold hover:underline"
                                      >
                                        + Add Field
                                      </button>
                                    </div>
                                    <div className="space-y-1.5 text-[10px] font-mono">
                                      {Object.entries(contact.custom_fields || {}).length === 0 ? (
                                        <span className="text-slate-600 block">No tenant-defined custom fields.</span>
                                      ) : (
                                        Object.entries(contact.custom_fields || {}).map(([key, val]: any) => (
                                          <div key={key} className="flex justify-between">
                                            <span className="text-slate-500 uppercase">{key}:</span>
                                            <span className="text-slate-300">{val}</span>
                                          </div>
                                        ))
                                      )}
                                    </div>
                                  </div>
                                </div>

                                {/* COLUMN 2: Granular Consent & Marketing Preferences */}
                                <div className="space-y-4">
                                  <div className="space-y-2">
                                    <h4 className="text-xs font-bold text-slate-300 border-b border-slate-900 pb-1.5 uppercase font-mono tracking-wider">
                                      🛡️ GDPR Consent Center (Granular)
                                    </h4>
                                    <div className="space-y-2 bg-slate-950/40 p-3 rounded-xl border border-slate-900">
                                      <div className="flex justify-between items-center text-xs">
                                        <span className="text-slate-400 font-mono">Email Campaign Consent</span>
                                        <button
                                          onClick={() => {
                                            const newVal = !contact.email_consent;
                                            setContacts(prev => prev.map(c => c.id === contact.id ? { ...c, email_consent: newVal, gdpr_consent_status: newVal ? "granted" : c.gdpr_consent_status } : c));
                                            triggerEventLog("crm.contact.consent_changed", `${contact.first_name} (Email: ${newVal ? 'Granted' : 'Revoked'})`);
                                          }}
                                          className={`px-2 py-0.5 rounded text-[9px] font-bold font-mono uppercase ${
                                            contact.email_consent ? "bg-emerald-950 text-emerald-400 border border-emerald-900" : "bg-slate-900 text-slate-500"
                                          }`}
                                        >
                                          {contact.email_consent ? "GRANTED" : "REVOKED"}
                                        </button>
                                      </div>
                                      <div className="flex justify-between items-center text-xs">
                                        <span className="text-slate-400 font-mono">WhatsApp Notifications</span>
                                        <button
                                          onClick={() => {
                                            const newVal = !contact.whatsapp_consent;
                                            setContacts(prev => prev.map(c => c.id === contact.id ? { ...c, whatsapp_consent: newVal } : c));
                                            triggerEventLog("crm.contact.consent_changed", `${contact.first_name} (WhatsApp: ${newVal ? 'Granted' : 'Revoked'})`);
                                          }}
                                          className={`px-2 py-0.5 rounded text-[9px] font-bold font-mono uppercase ${
                                            contact.whatsapp_consent ? "bg-emerald-950 text-emerald-400 border border-emerald-900" : "bg-slate-900 text-slate-500"
                                          }`}
                                        >
                                          {contact.whatsapp_consent ? "GRANTED" : "REVOKED"}
                                        </button>
                                      </div>
                                      <div className="flex justify-between items-center text-xs">
                                        <span className="text-slate-400 font-mono">SMS Broadcast Consent</span>
                                        <button
                                          onClick={() => {
                                            const newVal = !contact.do_not_sms;
                                            setContacts(prev => prev.map(c => c.id === contact.id ? { ...c, do_not_sms: newVal } : c));
                                            triggerEventLog("crm.contact.consent_changed", `${contact.first_name} (SMS block toggle)`);
                                          }}
                                          className={`px-2 py-0.5 rounded text-[9px] font-bold font-mono uppercase ${
                                            !contact.do_not_sms ? "bg-emerald-950 text-emerald-400 border border-emerald-900" : "bg-rose-950/40 text-rose-400 border border-rose-900/40"
                                          }`}
                                        >
                                          {!contact.do_not_sms ? "ALLOWED" : "BLOCKED"}
                                        </button>
                                      </div>
                                    </div>
                                  </div>

                                  {/* Direct Relationship Graph */}
                                  <div className="space-y-2">
                                    <h4 className="text-xs font-bold text-slate-300 border-b border-slate-900 pb-1.5 uppercase font-mono tracking-wider">
                                      🕸️ Relationship Hierarchy Graph
                                    </h4>
                                    <div className="bg-slate-950/40 p-3 rounded-xl border border-slate-900 text-xs font-mono space-y-2">
                                      <div className="flex justify-between items-center text-[10px]">
                                        <span className="text-slate-500">REPORTS TO (Manager):</span>
                                        <span className="text-indigo-400 font-bold">
                                          {contact.manager_id ? contacts.find(c => c.id === contact.manager_id)?.first_name + " " + contacts.find(c => c.id === contact.manager_id)?.last_name : "None assigned"}
                                        </span>
                                      </div>
                                      <div className="flex justify-between items-center text-[10px]">
                                        <span className="text-slate-500">DIRECT SUBORDINATES:</span>
                                        <span className="text-indigo-400 font-bold">
                                          {contacts.filter(c => c.manager_id === contact.id).map(c => c.first_name).join(", ") || "None"}
                                        </span>
                                      </div>
                                      {/* Prevent circular loop test */}
                                      <div className="pt-2 border-t border-slate-900">
                                        <button
                                          onClick={() => {
                                            const other = contacts.find(c => c.id !== contact.id);
                                            if (!other) return;
                                            
                                            // Circular graph detection simulation
                                            if (contact.manager_id === other.id || other.manager_id === contact.id) {
                                              alert("⚠️ CIRCULAR GRAPH ERROR: Establishing this manager hierarchy will cause a self-referencing relationship cycle. Action denied by ContactRelationship policy.");
                                              return;
                                            }

                                            setContacts(prev => prev.map(c => c.id === contact.id ? { ...c, manager_id: other.id } : c));
                                            triggerEventLog("crm.contact.relationship_updated", `${contact.first_name} manager established as ${other.first_name}`);
                                          }}
                                          className="w-full py-1 bg-slate-900 hover:bg-slate-850 border border-slate-800 text-[9px] text-slate-300 uppercase rounded text-center transition-colors font-bold"
                                        >
                                          🔗 Test Link Alternate Manager
                                        </button>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                              </div>

                              {/* Interactive Address Manager */}
                              <div className="space-y-2 pt-4 border-t border-slate-900">
                                <div className="flex justify-between items-center pb-1.5 border-b border-slate-900">
                                  <h4 className="text-xs font-bold text-slate-300 uppercase font-mono tracking-wider">
                                    📍 Address Manager
                                  </h4>
                                  <button
                                    onClick={() => {
                                      const type = prompt("Address Type (billing, shipping, home, office):", "shipping");
                                      if (!type) return;
                                      const street = prompt("Street details:");
                                      if (!street) return;
                                      const city = prompt("City:");
                                      if (!city) return;
                                      const country = prompt("Country:");
                                      if (!country) return;

                                      const newAddr = {
                                        id: "ADDR-" + Date.now(),
                                        type,
                                        is_primary: false,
                                        street,
                                        city,
                                        country,
                                        timezone: "UTC"
                                      };

                                      setContacts(prev => prev.map(c => {
                                        if (c.id === contact.id) {
                                          return { ...c, addresses: [...(c.addresses || []), newAddr] };
                                        }
                                        return c;
                                      }));
                                      triggerEventLog("crm.contact.updated", `Added address type: ${type} to ${contact.first_name}`);
                                    }}
                                    className="text-[9px] text-indigo-400 font-mono font-bold hover:underline"
                                  >
                                    + Add Address
                                  </button>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                  {(!contact.addresses || contact.addresses.length === 0) ? (
                                    <div className="sm:col-span-2 text-center py-4 font-mono text-[10px] text-slate-600">
                                      No address records mapped to this aggregate.
                                    </div>
                                  ) : (
                                    contact.addresses.map((addr: any) => (
                                      <div key={addr.id} className="bg-slate-950 p-3 rounded-xl border border-slate-900 relative">
                                        <button
                                          onClick={() => {
                                            setContacts(prev => prev.map(c => {
                                              if (c.id === contact.id) {
                                                return { ...c, addresses: c.addresses.filter((a: any) => a.id !== addr.id) };
                                              }
                                              return c;
                                            }));
                                            triggerEventLog("crm.contact.updated", `Removed address type: ${addr.type}`);
                                          }}
                                          className="absolute top-2.5 right-2.5 text-rose-500 hover:text-rose-400"
                                        >
                                          <Trash2 size={10} />
                                        </button>
                                        <div className="flex items-center gap-1.5 mb-1">
                                          <span className="text-[8px] font-mono font-bold uppercase px-1.5 py-0.5 rounded bg-indigo-950/40 text-indigo-400">
                                            {addr.type}
                                          </span>
                                          {addr.is_primary && (
                                            <span className="text-[7px] font-mono font-bold uppercase px-1 rounded bg-emerald-950 text-emerald-400">
                                              Primary
                                            </span>
                                          )}
                                        </div>
                                        <p className="text-[10px] text-slate-300 font-mono">{addr.street}</p>
                                        <p className="text-[9px] text-slate-500 font-mono">{addr.city}, {addr.country}</p>
                                      </div>
                                    ))
                                  )}
                                </div>
                              </div>

                              {/* Custom Health Breakdown Panel */}
                              <div className="space-y-3 pt-4 border-t border-slate-900">
                                <h4 className="text-xs font-bold text-slate-300 uppercase font-mono tracking-wider">
                                  🏥 Real-time Health Metrics & Breakdown (ContactHealthService)
                                </h4>
                                <div className="grid grid-cols-2 sm:grid-cols-6 gap-2 text-center text-slate-300 font-mono text-[9px]">
                                  <div className="bg-slate-950/60 p-2 border border-slate-900 rounded-lg">
                                    <span className="text-slate-500 block">ENGAGEMENT</span>
                                    <span className="text-emerald-400 font-bold">+{contact.health_breakdown.engagement}</span>
                                  </div>
                                  <div className="bg-slate-950/60 p-2 border border-slate-900 rounded-lg">
                                    <span className="text-slate-500 block">RESPONSIVE</span>
                                    <span className="text-emerald-400 font-bold">+{contact.health_breakdown.responsiveness}</span>
                                  </div>
                                  <div className="bg-slate-950/60 p-2 border border-slate-900 rounded-lg">
                                    <span className="text-slate-500 block">MEETINGS</span>
                                    <span className="text-emerald-400 font-bold">+{contact.health_breakdown.meeting_frequency}</span>
                                  </div>
                                  <div className="bg-slate-950/60 p-2 border border-slate-900 rounded-lg">
                                    <span className="text-slate-500 block">INFLUENCE</span>
                                    <span className="text-indigo-400 font-bold">+{contact.health_breakdown.sales_influence}</span>
                                  </div>
                                  <div className="bg-slate-950/60 p-2 border border-slate-900 rounded-lg">
                                    <span className="text-slate-500 block">GRAPH EDGE</span>
                                    <span className="text-indigo-400 font-bold">+{contact.health_breakdown.relationship_strength}</span>
                                  </div>
                                  <div className="bg-slate-950/60 p-2 border border-slate-900 rounded-lg">
                                    <span className="text-slate-500 block">COMPLETENESS</span>
                                    <span className="text-emerald-400 font-bold">+{contact.health_breakdown.profile_completeness || 15}</span>
                                  </div>
                                </div>

                                <div className="flex gap-2">
                                  <button
                                    onClick={() => {
                                      // Boost completed meeting points
                                      const newScore = Math.min(100, contact.health_score + 6);
                                      const newBreakdown = {
                                        ...contact.health_breakdown,
                                        meeting_frequency: contact.health_breakdown.meeting_frequency + 6
                                      };
                                      setContacts(prev => prev.map(c => c.id === contact.id ? { ...c, health_score: newScore, health_breakdown: newBreakdown, health_status: newScore >= 80 ? 'Healthy' : 'Warning' } : c));
                                      triggerEventLog("crm.contact.health_changed", `${contact.first_name} Health boosted to ${newScore}%`);
                                    }}
                                    className="flex-1 py-1 px-3 bg-indigo-600/20 text-indigo-400 border border-indigo-900/50 text-[9px] hover:bg-indigo-600/35 uppercase rounded font-mono font-bold transition-colors"
                                  >
                                     Log Successful Meeting (+6 Health)
                                  </button>
                                  <button
                                    onClick={() => {
                                      // Deduct outstanding tasks
                                      const newScore = Math.max(0, contact.health_score - 12);
                                      setContacts(prev => prev.map(c => c.id === contact.id ? { ...c, health_score: newScore, health_status: newScore < 50 ? 'Critical' : 'Warning' } : c));
                                      triggerEventLog("crm.contact.health_changed", `${contact.first_name} Health deteriorated to ${newScore}% due to overdue tasks`);
                                    }}
                                    className="flex-1 py-1 px-3 bg-rose-600/20 text-rose-400 border border-rose-900/50 text-[9px] hover:bg-rose-600/35 uppercase rounded font-mono font-bold transition-colors"
                                  >
                                     Inject Overdue Task Alert (-12 Health)
                                  </button>
                                </div>
                              </div>
                            </div>
                          </div>
                        );
                      })()}
                    </div>
                  </div>
                )}

                {/* Sub-view 2: Merge Wizard */}
                {contactViewMode === "merge" && (
                  <div className="bg-slate-900/20 border border-slate-900 p-6 rounded-2xl space-y-6">
                    <div>
                      <h4 className="text-sm font-bold text-white flex items-center gap-1.5">
                        🤝 Duplicate Contact Merge Wizard
                      </h4>
                      <p className="text-xs text-slate-400">Identify matching identities, select properties to consolidate, and perform atomic ledger merging.</p>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                      {/* Left: Select Master & Duplicate */}
                      <div className="space-y-4">
                        <div className="bg-slate-950 p-4 border border-slate-900 rounded-xl space-y-3">
                          <span className="text-[10px] font-mono text-indigo-400 font-extrabold uppercase block border-b border-slate-900 pb-1.5">
                            Step 1: Choose Master Record (Survivor)
                          </span>
                          <p className="text-[11px] text-slate-400 leading-relaxed">This contact remains active. Related methods, addresses, consents, and histories are automatically reparented to this ID.</p>
                          <select
                            onChange={(e) => {
                              // Select master
                            }}
                            className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-xs text-slate-200 focus:outline-none"
                          >
                            <option value="CONT-001">Caleb Kirui (CONT-001) - 85% Health [Tier A]</option>
                            <option value="CONT-002">Mary Kamau (CONT-002) - 72% Health [Tier B]</option>
                          </select>
                        </div>

                        <div className="bg-slate-950 p-4 border border-slate-900 rounded-xl space-y-3">
                          <span className="text-[10px] font-mono text-rose-400 font-extrabold uppercase block border-b border-slate-900 pb-1.5">
                            Step 2: Select Duplicate Records to Consolidate
                          </span>
                          <p className="text-[11px] text-slate-400 leading-relaxed">The following matches have been flagged automatically based on email similarities and levenshtein distance similarity scores.</p>
                          <div className="space-y-2">
                            <div className="flex items-center gap-2 bg-slate-900/60 p-2.5 rounded border border-slate-850">
                              <input type="checkbox" defaultChecked className="rounded border-slate-800 bg-slate-950 text-indigo-600" />
                              <div className="text-xs font-mono">
                                <div className="text-slate-200 font-bold">Caleb (Duplicate) (CONT-003)</div>
                                <div className="text-slate-500 text-[9px]">Match Confidence: 95% &bull; Matches: Email, Phone, Company</div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      {/* Right: Merged Field Consolidations Preview */}
                      <div className="space-y-4">
                        <div className="bg-slate-950 p-4 border border-slate-900 rounded-xl space-y-3 font-mono text-xs">
                          <span className="text-[10px] text-indigo-400 font-extrabold uppercase block border-b border-slate-900 pb-1.5">
                            Step 3: Fields Consolidation Review
                          </span>
                          
                          <div className="space-y-2 text-[10px] leading-relaxed">
                            <div className="flex justify-between pb-1.5 border-b border-slate-900">
                              <span className="text-slate-500">Master Record:</span>
                              <span className="text-slate-300">Caleb Kirui</span>
                            </div>
                            <div className="flex justify-between pb-1.5 border-b border-slate-900">
                              <span className="text-slate-500">Duplicate Record(s):</span>
                              <span className="text-slate-300">Caleb (Duplicate)</span>
                            </div>
                            <div className="flex justify-between pb-1.5 border-b border-slate-900">
                              <span className="text-slate-500">Reassigned Addresses:</span>
                              <span className="text-emerald-400">+2 Addresses consolidated</span>
                            </div>
                            <div className="flex justify-between pb-1.5 border-b border-slate-900">
                              <span className="text-slate-500">Reassigned Contact Methods:</span>
                              <span className="text-emerald-400">+1 Method mapped to master</span>
                            </div>
                            <div className="flex justify-between pb-1.5 border-b border-slate-900">
                              <span className="text-slate-500">GDPR Consents Consolidated:</span>
                              <span className="text-emerald-400">Synced to master outbox queue</span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-slate-500">Event Dispatched:</span>
                              <span className="text-indigo-400 font-bold">crm.contact.merged</span>
                            </div>
                          </div>

                          <button
                            onClick={() => {
                              // Perform the merge simulation
                              setContacts(prev => prev.filter(c => c.id !== "CONT-003"));
                              setSelectedContactId("CONT-001");
                              setContactViewMode("directory");
                              triggerEventLog("crm.contact.merged", "Caleb Kirui (Consolidated 1 duplicate)");
                              alert("✓ Merge Executed! Contact duplicate CONT-003 has been soft-deleted and all related methods, consents, and addresses have been atomically reassigned to Caleb Kirui (CONT-001).");
                            }}
                            className="w-full py-2 bg-indigo-600 hover:bg-indigo-5050 border border-indigo-500 text-[10px] text-white uppercase rounded text-center transition-colors font-bold"
                          >
                            🚀 Confirm & Execute Atomical Merge
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                )}

                {/* Sub-view 3: Import Wizard */}
                {contactViewMode === "import" && (
                  <div className="bg-slate-900/20 border border-slate-900 p-6 rounded-2xl space-y-6">
                    <div>
                      <h4 className="text-sm font-bold text-white flex items-center gap-1.5">
                        📥 Enterprise Import CSV/JSON Wizard
                      </h4>
                      <p className="text-xs text-slate-400">Validate incoming payload columns, analyze duplicate occurrences before applying, and support instant batch rollback.</p>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                      {/* Col 1: Pasting data */}
                      <div className="lg:col-span-1 bg-slate-950 p-4 border border-slate-900 rounded-xl space-y-3">
                        <span className="text-[10px] font-mono text-indigo-400 font-extrabold uppercase block border-b border-slate-900 pb-1.5">
                          Step 1: Paste CSV / JSON Records
                        </span>
                        <p className="text-[10px] text-slate-500">Paste JSON structure with First/Last Name, Email, and Phone to validate.</p>
                        <textarea
                          defaultValue={JSON.stringify([
                            { first_name: "James", last_name: "Mwangi", email: "james.mw@equity.co.ke", phone: "+254 711 999333" },
                            { first_name: "Caleb", last_name: "Kirui", email: "caleb@telecom.co.ke", phone: "+254 700 111222" }
                          ], null, 2)}
                          rows={6}
                          className="w-full bg-slate-900 border border-slate-800 text-[10px] p-2.5 font-mono rounded text-slate-300 focus:outline-none focus:border-indigo-500"
                        />
                        <button
                          onClick={() => {
                            alert("✓ Structural dry-run complete. Checked 2 records: \n- Row 0: Valid, No Duplicates\n- Row 1: DUPLICATE FOUND (Matches existing contact CONT-001 by Email/Phone).");
                          }}
                          className="w-full py-1.5 bg-slate-900 hover:bg-slate-850 border border-slate-800 text-[10px] font-bold text-indigo-400 rounded uppercase font-mono transition-colors"
                        >
                          🔍 Run Dry-run Validation
                        </button>
                      </div>

                      {/* Col 2: Import Parameters and Execute */}
                      <div className="lg:col-span-1 bg-slate-950 p-4 border border-slate-900 rounded-xl space-y-3">
                        <span className="text-[10px] font-mono text-indigo-400 font-extrabold uppercase block border-b border-slate-900 pb-1.5">
                          Step 2: Import Execution
                        </span>
                        <p className="text-[10px] text-slate-500">Configure parameters before committing records to the multi-tenant CRM tables.</p>
                        <div className="space-y-2 text-[10px] font-mono">
                          <label className="flex items-center gap-2">
                            <input type="checkbox" defaultChecked className="rounded border-slate-800 bg-slate-950 text-indigo-600" />
                            <span>Skip flagged duplicate records</span>
                          </label>
                          <label className="flex items-center gap-2">
                            <input type="checkbox" defaultChecked className="rounded border-slate-800 bg-slate-950 text-indigo-600" />
                            <span>Auto-verify contact emails</span>
                          </label>
                        </div>

                        <button
                          onClick={() => {
                            // Add imported record to contacts list
                            const newContact = {
                              id: "CONT-IMP-99",
                              first_name: "James",
                              middle_name: "",
                              last_name: "Mwangi",
                              preferred_name: "James",
                              email: "james.mw@equity.co.ke",
                              phone: "+254 711 999333",
                              job_title: "Head of Digital Channels",
                              company_name: "Equity Bank",
                              tier: "Tier B",
                              segment: "Enterprise",
                              lifecycle_stage: "SQL",
                              status: "Active",
                              health_score: 75,
                              health_status: "Healthy",
                              health_breakdown: { engagement: 5, responsiveness: 5, meeting_frequency: 5, sales_influence: 5, relationship_strength: 5, profile_completeness: 5 },
                              custom_fields: {},
                              addresses: [],
                              consents: [],
                              relationships: []
                            };

                            setContacts(prev => [...prev, newContact]);
                            setSelectedContactId("CONT-IMP-99");
                            triggerEventLog("crm.contact.created", "James Mwangi (Imported)");
                            alert("✓ 1 Contact imported successfully! Row 1 (Caleb Kirui) was skipped as duplicate based on parameters.");
                          }}
                          className="w-full py-2 bg-indigo-600 hover:bg-indigo-550 border border-indigo-500 text-[10px] font-bold text-white rounded uppercase font-mono transition-colors"
                        >
                          📥 Commit Import Records
                        </button>
                      </div>

                      {/* Col 3: Rollback Console */}
                      <div className="lg:col-span-1 bg-slate-950 p-4 border border-slate-900 rounded-xl space-y-3">
                        <span className="text-[10px] font-mono text-rose-400 font-extrabold uppercase block border-b border-slate-900 pb-1.5">
                          ⏪ Import Rollback Center
                        </span>
                        <p className="text-[10px] text-slate-500">Every import batch creates a transaction metadata point enabling complete reversal of records in the database.</p>
                        
                        <div className="space-y-1.5 font-mono text-[9px]">
                          <div className="p-2 bg-slate-900 rounded border border-slate-850 flex justify-between items-center">
                            <div>
                              <div className="text-slate-300 font-bold">BATCH #992 (Today)</div>
                              <div className="text-slate-500">1 Contact Imported</div>
                            </div>
                            <button
                              onClick={() => {
                                setContacts(prev => prev.filter(c => c.id !== "CONT-IMP-99"));
                                setSelectedContactId("CONT-001");
                                triggerEventLog("crm.contact.deleted", "James Mwangi (Reversed Import)");
                                alert("✓ Batch rollback completed! Reversals applied cleanly. James Mwangi (CONT-IMP-99) has been deleted from crm_contacts.");
                              }}
                              className="px-2 py-1 bg-rose-950/40 hover:bg-rose-950/60 text-rose-400 border border-rose-900/40 rounded uppercase font-bold text-[8px]"
                            >
                              Rollback
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                )}

                {/* VIEW 7: Proposals, Quotations & e-Contracts Bounded Context */}
                {activeTab === "proposals" && (
                  <div className="space-y-6">
                    {/* Header */}
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-slate-900/40 p-5 rounded-xl border border-slate-900">
                      <div>
                        <div className="flex items-center gap-2 mb-1.5">
                          <span className="bg-indigo-500/10 text-indigo-400 text-[9px] font-mono border border-indigo-500/20 px-2 py-0.5 rounded-full uppercase tracking-wider font-bold">
                            Phase F5.3 Bounded Context
                          </span>
                        </div>
                        <h3 className="text-sm font-bold text-white font-mono uppercase tracking-wider flex items-center gap-2">
                          📋 Client Proposal & e-Sign Contract Engine
                        </h3>
                        <p className="text-[11px] text-slate-400 mt-1">Draft, version-control, negotiate, and legally bind enterprise agreements using electronic signatures.</p>
                      </div>
                      <div className="flex gap-2">
                        {proposalViewMode === "list" && (
                          <button
                            onClick={() => setProposalViewMode("create")}
                            className="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-550 border border-indigo-500 text-[10px] font-bold text-white rounded uppercase font-mono transition-colors"
                          >
                            + Draft New Proposal
                          </button>
                        )}
                        {proposalViewMode !== "list" && (
                          <button
                            onClick={() => setProposalViewMode("list")}
                            className="px-3 py-1.5 bg-slate-900 hover:bg-slate-850 border border-slate-800 text-[10px] font-bold text-slate-400 rounded uppercase font-mono transition-colors"
                          >
                            &larr; Back to List
                          </button>
                        )}
                      </div>
                    </div>

                    {/* SUB-VIEW A: List of Proposals */}
                    {proposalViewMode === "list" && (
                      <div className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          {proposals.map((prop) => (
                            <div key={prop.id} className="p-5 bg-slate-900/20 border border-slate-900 rounded-xl space-y-4 hover:border-slate-800 transition-all">
                              <div className="flex justify-between items-start">
                                <div className="space-y-1">
                                  <div className="flex items-center gap-1.5">
                                    <span className="text-[9px] font-mono bg-slate-950 text-slate-500 px-1.5 py-0.5 rounded border border-slate-900 font-bold">
                                      {prop.id}
                                    </span>
                                    <span className={`text-[8px] font-mono font-bold uppercase px-2 py-0.5 rounded-full ${
                                      prop.status === "draft" ? "bg-slate-900 text-slate-400 border border-slate-800" :
                                      prop.status === "sent" ? "bg-indigo-950 text-indigo-400 border border-indigo-900" :
                                      prop.status === "signed" ? "bg-emerald-950 text-emerald-400 border border-emerald-900" :
                                      prop.status === "converted" ? "bg-teal-950 text-teal-400 border border-teal-900" : "bg-rose-950 text-rose-400 border border-rose-900"
                                    }`}>
                                      {prop.status}
                                    </span>
                                  </div>
                                  <h4 className="text-xs font-bold text-white leading-snug">{prop.title}</h4>
                                  <p className="text-[10px] text-slate-400 font-mono">Recipient: <span className="text-indigo-400">{prop.clientName}</span></p>
                                </div>
                                <div className="text-right">
                                  <span className="text-[9px] font-mono text-slate-500 block uppercase">BID VALUE</span>
                                  <span className="text-sm font-extrabold font-mono text-emerald-400">${prop.totalAmount.toLocaleString()}</span>
                                </div>
                              </div>

                              <div className="flex justify-between items-center pt-3 border-t border-slate-900 text-[10px]">
                                <span className="text-slate-500 font-mono">Expires: {prop.expiresAt}</span>
                                <div className="flex gap-2">
                                  <button
                                    onClick={() => {
                                      setSelectedProposalId(prop.id);
                                      setProposalViewMode("review");
                                    }}
                                    className="px-2.5 py-1 bg-indigo-600/20 hover:bg-indigo-600/35 text-indigo-400 border border-indigo-900/50 rounded uppercase font-mono font-bold transition-all text-[9px]"
                                  >
                                    Review & Sign Portal
                                  </button>
                                </div>
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}

                    {/* SUB-VIEW B: Create Draft Form */}
                    {proposalViewMode === "create" && (
                      <div className="bg-slate-900/10 p-5 rounded-xl border border-slate-900 space-y-6">
                        <div className="space-y-4">
                          <h4 className="text-xs font-bold text-slate-300 uppercase font-mono border-b border-slate-900 pb-2">
                            1. Metadata configuration
                          </h4>
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-1">
                              <label className="text-[9px] font-mono text-slate-500 uppercase font-bold block">Proposal Document Title</label>
                              <input
                                type="text"
                                value={newPropTitle}
                                onChange={(e) => setNewPropTitle(e.target.value)}
                                className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs text-white"
                              />
                            </div>
                            <div className="space-y-1">
                              <label className="text-[9px] font-mono text-slate-500 uppercase block font-bold">Payer Recipient Client</label>
                              <select
                                value={newPropClient}
                                onChange={(e) => setNewPropClient(e.target.value)}
                                className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs text-white"
                              >
                                <option value="Alice Wanjiru (Apex Digital)">Alice Wanjiru (Apex Digital)</option>
                                <option value="James Mwangi (Equity Bank)">James Mwangi (Equity Bank)</option>
                                <option value="Sarah Kemunto (Safiri Express)">Sarah Kemunto (Safiri Express)</option>
                              </select>
                            </div>
                            <div className="space-y-1">
                              <label className="text-[9px] font-mono text-slate-500 uppercase block font-bold">Validity/Expiration Constraint</label>
                              <input
                                type="date"
                                value={newPropExpires}
                                onChange={(e) => setNewPropExpires(e.target.value)}
                                className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-xs text-white font-mono"
                              />
                            </div>
                          </div>
                        </div>

                        {/* Sections list */}
                        <div className="space-y-4">
                          <div className="flex justify-between items-center border-b border-slate-900 pb-2">
                            <h4 className="text-xs font-bold text-slate-300 uppercase font-mono">
                              2. Custom Proposal Document Sections
                            </h4>
                            <button
                              onClick={() => setNewPropSections([...newPropSections, { title: "New Section Title", content: "" }])}
                              className="text-[10px] text-indigo-400 font-mono hover:underline font-bold"
                            >
                              + Add Scope Section Block
                            </button>
                          </div>
                          <div className="space-y-3">
                            {newPropSections.map((sec, idx) => (
                              <div key={idx} className="p-3 bg-slate-950 rounded border border-slate-900 relative space-y-2">
                                <button
                                  onClick={() => setNewPropSections(newPropSections.filter((_, sidx) => sidx !== idx))}
                                  className="absolute top-2 right-2 text-rose-500 hover:text-rose-400 text-xs"
                                >
                                  &times;
                                </button>
                                <input
                                  type="text"
                                  value={sec.title}
                                  onChange={(e) => {
                                    const updated = [...newPropSections];
                                    updated[idx].title = e.target.value;
                                    setNewPropSections(updated);
                                  }}
                                  className="w-full bg-slate-900 border border-slate-800 rounded p-1.5 text-xs text-white font-semibold font-mono"
                                  placeholder="e.g. 1. Technical Architecture"
                                />
                                <textarea
                                  value={sec.content}
                                  onChange={(e) => {
                                    const updated = [...newPropSections];
                                    updated[idx].content = e.target.value;
                                    setNewPropSections(updated);
                                  }}
                                  rows={2}
                                  className="w-full bg-slate-900 border border-slate-800 rounded p-1.5 text-xs text-slate-300"
                                  placeholder="Detailed section narrative content goes here..."
                                />
                              </div>
                            ))}
                          </div>
                        </div>

                        {/* Quote items table */}
                        <div className="space-y-4">
                          <div className="flex justify-between items-center border-b border-slate-900 pb-2">
                            <h4 className="text-xs font-bold text-slate-300 uppercase font-mono">
                              3. Quotation Line Items & Estimates
                            </h4>
                            <button
                              onClick={() => setNewPropItems([...newPropItems, { description: "", quantity: 1, unit_price: 0 }])}
                              className="text-[10px] text-indigo-400 font-mono hover:underline font-bold"
                            >
                              + Add Line Item Row
                            </button>
                          </div>
                          <table className="min-w-full divide-y divide-slate-900 text-[10px] font-mono text-left">
                            <thead>
                              <tr className="text-slate-500 uppercase">
                                <th className="py-2">Description</th>
                                <th className="py-2 w-20 text-right">Qty</th>
                                <th className="py-2 w-28 text-right">Unit Price ($)</th>
                                <th className="py-2 w-28 text-right font-bold">Total ($)</th>
                                <th className="py-2 w-10 text-center"></th>
                              </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-900">
                              {newPropItems.map((item, idx) => (
                                <tr key={idx} className="text-slate-300">
                                  <td className="py-2">
                                    <input
                                      type="text"
                                      value={item.description}
                                      onChange={(e) => {
                                        const updated = [...newPropItems];
                                        updated[idx].description = e.target.value;
                                        setNewPropItems(updated);
                                      }}
                                      className="w-full bg-slate-950 border border-slate-850 rounded p-1 text-[10px] text-white"
                                      placeholder="Service block description..."
                                    />
                                  </td>
                                  <td className="py-2 text-right">
                                    <input
                                      type="number"
                                      value={item.quantity}
                                      onChange={(e) => {
                                        const updated = [...newPropItems];
                                        updated[idx].quantity = parseFloat(e.target.value || "0");
                                        setNewPropItems(updated);
                                      }}
                                      className="w-16 bg-slate-950 border border-slate-850 rounded p-1 text-[10px] text-white text-right"
                                    />
                                  </td>
                                  <td className="py-2 text-right">
                                    <input
                                      type="number"
                                      value={item.unit_price}
                                      onChange={(e) => {
                                        const updated = [...newPropItems];
                                        updated[idx].unit_price = parseFloat(e.target.value || "0");
                                        setNewPropItems(updated);
                                      }}
                                      className="w-24 bg-slate-950 border border-slate-850 rounded p-1 text-[10px] text-white text-right"
                                    />
                                  </td>
                                  <td className="py-2 text-right text-emerald-400 font-bold align-middle">
                                    ${(item.quantity * item.unit_price).toLocaleString()}
                                  </td>
                                  <td className="py-2 text-center align-middle">
                                    <button
                                      onClick={() => setNewPropItems(newPropItems.filter((_, iidx) => iidx !== idx))}
                                      className="text-rose-500 hover:text-rose-400 font-bold text-xs"
                                    >
                                      &times;
                                    </button>
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>

                          <div className="flex justify-end pt-3 border-t border-slate-900">
                            <div className="text-right">
                              <span className="text-[9px] text-slate-500 block">TOTAL AGGREGATED QUOTATION VALUE</span>
                              <span className="text-xl font-bold font-mono text-emerald-400">
                                ${newPropItems.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0).toLocaleString()}
                              </span>
                            </div>
                          </div>
                        </div>

                        {/* Submit */}
                        <div className="flex justify-end gap-2 pt-4 border-t border-slate-900">
                          <button
                            onClick={() => setProposalViewMode("list")}
                            className="px-4 py-2 bg-slate-950 hover:bg-slate-900 border border-slate-850 text-xs font-bold text-slate-400 rounded uppercase font-mono"
                          >
                            Cancel
                          </button>
                          <button
                            onClick={() => {
                              const calcTotal = newPropItems.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
                              const newObj = {
                                id: "PROP-00" + (proposals.length + 1),
                                title: newPropTitle,
                                clientName: newPropClient,
                                status: "draft",
                                totalAmount: calcTotal,
                                expiresAt: newPropExpires,
                                sections: newPropSections,
                                items: newPropItems,
                                comments: [],
                                revisions: [{ version: 1, notes: "Initial drafting setup completed.", date: "Just now" }],
                                signature: null
                              };
                              setProposals([newObj, ...proposals]);
                              setProposalViewMode("list");
                              triggerEventLog("crm.proposal.created", `${newPropTitle} (${newPropClient})`);
                              alert("✓ Proposal draft created! Strongly-typed event 'crm.proposal.created' dispatched through transaction outbox.");
                            }}
                            className="px-5 py-2 bg-indigo-600 hover:bg-indigo-550 border border-indigo-500 text-xs font-bold text-white rounded uppercase font-mono"
                          >
                            Compile Draft & Dispatch Event
                          </button>
                        </div>
                      </div>
                    )}

                    {/* SUB-VIEW C: Client Review Portal */}
                    {proposalViewMode === "review" && (() => {
                      const prop = proposals.find(p => p.id === selectedProposalId) || proposals[0];
                      return (
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                          
                          {/* Document View Pane */}
                          <div className="lg:col-span-2 space-y-6">
                            <div className="bg-slate-950 p-6 rounded-2xl border border-slate-900 space-y-6">
                              <div className="flex justify-between items-center pb-4 border-b border-slate-900">
                                <div>
                                  <span className="text-[9px] font-mono text-indigo-400 uppercase font-bold tracking-wider">
                                    Official Legal Agreement Frame
                                  </span>
                                  <h4 className="text-sm font-bold text-white mt-0.5">{prop.title}</h4>
                                </div>
                                <span className="bg-indigo-950 text-indigo-400 text-[9px] font-mono border border-indigo-900 px-2 py-0.5 rounded font-bold uppercase">
                                  {prop.status}
                                </span>
                              </div>

                              {/* Sections */}
                              <div className="space-y-5 text-xs text-slate-300 leading-relaxed font-sans max-h-[350px] overflow-y-auto pr-2">
                                {prop.sections.map((sec: any, idx: number) => (
                                  <div key={idx} className="space-y-2">
                                    <h5 className="font-bold text-white border-b border-slate-900 pb-1 font-mono uppercase text-[10px] tracking-wider text-indigo-400">{sec.title}</h5>
                                    <p className="whitespace-pre-line text-slate-400">{sec.content}</p>
                                  </div>
                                ))}
                              </div>

                              {/* Line Items Table */}
                              <div className="pt-4 border-t border-slate-900 space-y-3">
                                <h5 className="text-[10px] font-bold uppercase text-slate-500 font-mono tracking-wider">Itemized Project Quotation</h5>
                                <table className="min-w-full text-[10px] font-mono text-left">
                                  <thead>
                                    <tr className="text-slate-500">
                                      <th className="py-1">Description</th>
                                      <th className="py-1 text-right">Qty</th>
                                      <th className="py-1 text-right">Unit Price ($)</th>
                                      <th className="py-1 text-right">Subtotal ($)</th>
                                    </tr>
                                  </thead>
                                  <tbody className="divide-y divide-slate-900">
                                    {prop.items.map((item: any, idx: number) => (
                                      <tr key={idx} className="text-slate-300">
                                        <td className="py-2 text-slate-400 font-medium">{item.description}</td>
                                        <td className="py-2 text-right">{item.quantity}</td>
                                        <td className="py-2 text-right">${item.unit_price.toLocaleString()}</td>
                                        <td className="py-2 text-right font-bold text-emerald-400">${(item.quantity * item.unit_price).toLocaleString()}</td>
                                      </tr>
                                    ))}
                                  </tbody>
                                </table>

                                <div className="flex justify-end pt-3 border-t border-slate-900">
                                  <div className="text-right">
                                    <span className="text-[8px] text-slate-500 block uppercase">Project Scope Budget Estimate</span>
                                    <span className="text-base font-extrabold font-mono text-emerald-400">${prop.totalAmount.toLocaleString()}</span>
                                  </div>
                                </div>
                              </div>
                            </div>

                            {/* Negotiation Comments Thread */}
                            <div className="bg-slate-900/10 p-5 rounded-2xl border border-slate-900 space-y-4">
                              <h4 className="text-xs font-bold text-slate-300 uppercase font-mono tracking-wider">
                                💬 Scope Negotiation & Collaboration Thread
                              </h4>

                              <div className="space-y-3">
                                {prop.comments.map((comm: any) => (
                                  <div key={comm.id} className="p-3 bg-slate-950 rounded-xl border border-slate-900 text-xs space-y-1">
                                    <div className="flex justify-between items-center text-[10px]">
                                      <span className="font-bold text-indigo-400 font-mono">{comm.user}</span>
                                      <span className="text-slate-500 text-[9px]">{comm.date}</span>
                                    </div>
                                    <p className="text-slate-300 leading-relaxed">{comm.text}</p>
                                  </div>
                                ))}
                                {prop.comments.length === 0 && (
                                  <p className="text-[10px] text-slate-600 italic">No feedback messages logs created. Post comments below to request revisions.</p>
                                )}
                              </div>

                              <div className="flex gap-2">
                                <input
                                  type="text"
                                  value={commentsInput}
                                  onChange={(e) => setCommentsInput(e.target.value)}
                                  placeholder="Type feedback, budget requests, or clause adjustments..."
                                  className="flex-1 bg-slate-950 border border-slate-800 rounded p-2 text-xs text-white"
                                />
                                <button
                                  onClick={() => {
                                    if (!commentsInput.trim()) return;
                                    const newComm = {
                                      id: "C" + Date.now(),
                                      user: "Alice Wanjiru (Client)",
                                      text: commentsInput,
                                      date: "Just now"
                                    };
                                    setProposals(prev => prev.map(p => {
                                      if (p.id === prop.id) {
                                        return {
                                          ...p,
                                          status: "negotiating",
                                          comments: [...p.comments, newComm]
                                        };
                                      }
                                      return p;
                                    }));
                                    setCommentsInput("");
                                    triggerEventLog("crm.proposal.negotiating", `${prop.title} Clause Review`);
                                    alert("✓ Discussion log committed. Proposal moved to 'negotiating' status.");
                                  }}
                                  className="px-4 py-2 bg-slate-800 hover:bg-slate-750 border border-slate-700 text-xs font-bold text-white rounded font-mono uppercase"
                                >
                                  Comment
                                </button>
                              </div>
                            </div>
                          </div>

                          {/* Signature Sidebar Column */}
                          <div className="space-y-6">
                            
                            {/* Electronic Signature Box */}
                            <div className="bg-slate-950 p-5 rounded-2xl border border-slate-900 space-y-4">
                              <h4 className="text-xs font-bold text-white uppercase font-mono tracking-wider">
                                🖋️ Legally Binding E-Signature
                              </h4>

                              {prop.status === "signed" || prop.status === "converted" ? (
                                <div className="p-4 bg-emerald-950/20 border border-emerald-900 rounded-xl text-center space-y-3">
                                  <div className="text-emerald-400 font-extrabold text-2xl animate-pulse">✓ CONTRACT SIGNED</div>
                                  <div className="text-[10px] text-slate-400 leading-relaxed font-mono text-left">
                                    Signed by: <span className="text-indigo-400 font-bold">{prop.signature?.signer}</span><br />
                                    Timestamp: {prop.signature?.timestamp}<br />
                                    IP Code: {prop.signature?.ip}<br />
                                    Format: {prop.signature?.format.toUpperCase()}
                                  </div>
                                  <div className="p-2 bg-emerald-900/10 border border-emerald-900/40 rounded text-[9px] text-slate-300">
                                    ⚙️ Automatic Project Conversion Active. Delivery pipelines triggered!
                                  </div>
                                </div>
                              ) : (
                                <div className="space-y-4">
                                  <div className="flex border border-slate-900 rounded p-0.5 bg-slate-950">
                                    <button
                                      onClick={() => setSigType("typed")}
                                      className={`flex-1 py-1.5 rounded text-[9px] font-mono font-bold uppercase transition-all ${
                                        sigType === "typed" ? "bg-indigo-600 text-white" : "text-slate-500"
                                      }`}
                                    >
                                      Type Name
                                    </button>
                                    <button
                                      onClick={() => {
                                        setSigType("drawn");
                                        setTimeout(() => {
                                          const canvas = canvasRef.current;
                                          if (canvas) {
                                            const ctx = canvas.getContext("2d");
                                            if (ctx) {
                                              ctx.strokeStyle = '#6366f1';
                                              ctx.lineWidth = 2.5;
                                              ctx.lineCap = 'round';
                                            }
                                          }
                                        }, 100);
                                      }}
                                      className={`flex-1 py-1.5 rounded text-[9px] font-mono font-bold uppercase transition-all ${
                                        sigType === "drawn" ? "bg-indigo-600 text-white" : "text-slate-500"
                                      }`}
                                    >
                                      Draw Signature
                                    </button>
                                  </div>

                                  {sigType === "typed" ? (
                                    <div className="space-y-2">
                                      <label className="text-[9px] font-mono text-slate-500 uppercase block font-bold">Authorized Signatory Name</label>
                                      <input
                                        type="text"
                                        value={typedName}
                                        onChange={(e) => setTypedName(e.target.value)}
                                        className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-xs text-white font-mono"
                                      />
                                      <div className="p-4 bg-slate-900 rounded-lg border border-slate-850 h-20 flex items-center justify-center">
                                        <span className="font-serif italic text-xl text-indigo-400 font-extrabold tracking-wide select-none">
                                          {typedName || "Enter Signature Name"}
                                        </span>
                                      </div>
                                    </div>
                                  ) : (
                                    <div className="space-y-2">
                                      <div className="flex justify-between items-center">
                                        <label className="text-[9px] font-mono text-slate-500 uppercase block font-bold">Interactive Drawing Pad</label>
                                        <button
                                          onClick={() => {
                                            const canvas = canvasRef.current;
                                            if (canvas) {
                                              const ctx = canvas.getContext("2d");
                                              if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
                                            }
                                          }}
                                          className="text-[9px] text-indigo-400 font-mono font-bold hover:underline"
                                        >
                                          Clear Pad
                                        </button>
                                      </div>
                                      <div className="bg-slate-900 border border-slate-850 rounded-lg h-32 overflow-hidden relative cursor-crosshair">
                                        <canvas
                                          ref={canvasRef}
                                          width={300}
                                          height={128}
                                          onMouseDown={(e) => {
                                            const canvas = canvasRef.current;
                                            if (!canvas) return;
                                            const ctx = canvas.getContext("2d");
                                            if (!ctx) return;
                                            setIsDrawing(true);
                                            const rect = canvas.getBoundingClientRect();
                                            ctx.beginPath();
                                            ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
                                          }}
                                          onMouseMove={(e) => {
                                            if (!isDrawing) return;
                                            const canvas = canvasRef.current;
                                            if (!canvas) return;
                                            const ctx = canvas.getContext("2d");
                                            if (!ctx) return;
                                            const rect = canvas.getBoundingClientRect();
                                            ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
                                            ctx.stroke();
                                          }}
                                          onMouseUp={() => setIsDrawing(false)}
                                          onMouseLeave={() => setIsDrawing(false)}
                                          onTouchStart={(e) => {
                                            const canvas = canvasRef.current;
                                            if (!canvas) return;
                                            const ctx = canvas.getContext("2d");
                                            if (!ctx) return;
                                            setIsDrawing(true);
                                            const rect = canvas.getBoundingClientRect();
                                            ctx.beginPath();
                                            const t = e.touches[0];
                                            ctx.moveTo(t.clientX - rect.left, t.clientY - rect.top);
                                          }}
                                          onTouchMove={(e) => {
                                            if (!isDrawing) return;
                                            const canvas = canvasRef.current;
                                            if (!canvas) return;
                                            const ctx = canvas.getContext("2d");
                                            if (!ctx) return;
                                            const rect = canvas.getBoundingClientRect();
                                            const t = e.touches[0];
                                            ctx.lineTo(t.clientX - rect.left, t.clientY - rect.top);
                                            ctx.stroke();
                                          }}
                                          onTouchEnd={() => setIsDrawing(false)}
                                          className="w-full h-full"
                                        />
                                      </div>
                                    </div>
                                  )}

                                  <div className="p-3 bg-slate-900 rounded text-[9px] text-slate-500 leading-relaxed border border-slate-850">
                                    <span className="font-bold text-slate-400 uppercase block mb-1">Audit Trail Logging Info</span>
                                    By signing, you capture electronic confirmation matching federal uniform transaction guidelines, attaching IP 197.248.88.192, timestamped at UTC.
                                  </div>

                                  <button
                                    onClick={() => {
                                      // Transition to Signed / Converted
                                      setProposals(prev => prev.map(p => {
                                        if (p.id === prop.id) {
                                          return {
                                            ...p,
                                            status: "converted",
                                            signature: {
                                              signer: sigType === "typed" ? typedName : "Drawn Client Representative Signature",
                                              timestamp: new Date().toUTCString(),
                                              ip: "197.248.88.192",
                                              format: sigType
                                            }
                                          };
                                        }
                                        return p;
                                      }));
                                      
                                      // Trigger event logs for CQRS Hexagonal Outbox showcase
                                      triggerEventLog("crm.proposal.signed", `${prop.title}`);
                                      triggerEventLog("crm.contract.created", `Contract generated for ${prop.clientName}`);
                                      triggerEventLog("project.created", `Project initialized: ${prop.title} ($${prop.totalAmount.toLocaleString()})`);

                                      alert(`✓ Success! Proposal Signed and Approved!\n\nAutomated Project Converter has:\n1. Created Project: "${prop.title}" with budget $${prop.totalAmount.toLocaleString()}\n2. Created Milestones & Tasks\n3. Set CRM Lead to WON!\n\nCheck out the Hexagonal Event Bus log below for the strongly typed event dispatch sequences.`);
                                    }}
                                    className="w-full py-2.5 bg-emerald-600 hover:bg-emerald-550 border border-emerald-500 text-xs font-bold text-white rounded uppercase font-mono transition-colors text-center block"
                                  >
                                    Certify & Electronic Sign Contract
                                  </button>
                                </div>
                              )}
                            </div>

                            {/* Revision logs */}
                            <div className="bg-slate-950 p-5 rounded-2xl border border-slate-900 space-y-3">
                              <h4 className="text-xs font-bold text-white uppercase font-mono tracking-wider">
                                🔄 Version History Controls
                              </h4>
                              <div className="space-y-2">
                                {prop.revisions.map((rev: any, idx: number) => (
                                  <div key={idx} className="p-2.5 bg-slate-900/60 border border-slate-900 rounded text-[10px] font-mono flex justify-between items-center">
                                    <div>
                                      <div className="font-bold text-indigo-400">VERSION v{rev.version}</div>
                                      <div className="text-slate-500 text-[9px] mt-0.5">{rev.notes}</div>
                                    </div>
                                    <span className="text-[8px] text-slate-600">{rev.date}</span>
                                  </div>
                                ))}
                              </div>
                            </div>
                          </div>

                        </div>
                      );
                    })()}

                  </div>
                )}

                {/* Strongly Typed Event Log specifically for CRM contacts */}
                <div className="bg-slate-950 p-4 border border-slate-900 rounded-xl space-y-2">
                  <div className="flex justify-between items-center pb-2 border-b border-slate-900">
                    <span className="text-[10px] font-mono text-emerald-400 uppercase tracking-wider font-extrabold block">
                      ⚡ HEXAGONAL EVENT BUS LOG (crm.contact.*)
                    </span>
                    <span className="text-[8px] font-mono bg-emerald-950 text-emerald-400 px-1.5 py-0.5 rounded animate-pulse">
                      EVENT_BUS ACTIVE
                    </span>
                  </div>
                  <div className="space-y-1.5 font-mono text-[9px] leading-relaxed max-h-[120px] overflow-y-auto">
                    {outboxEvents.map((evt) => (
                      <div key={evt.id} className="text-slate-400 flex justify-between gap-4">
                        <div>
                          <span className="text-emerald-500 font-bold">[DISPATCH]</span> <span className="text-slate-200 font-semibold">{evt.name}</span> &rarr; Target: <span className="text-indigo-400">{evt.target}</span>
                        </div>
                        <span className="text-slate-500 text-[8px]">{evt.ts}</span>
                      </div>
                    ))}
                    <div className="text-slate-500 text-[8px] italic pt-1 border-t border-slate-900">
                      * All domain contact operations dispatch strongly-typed events through EventBus, fully isolated by tenant organization limits.
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>

        </div>
      </div>
    </div>
  );
}

// 9. Admin Settings & Integrations Tab
function DeploymentTab() {
  const [appName, setAppName] = useState("JUANET Enterprise Services");
  const [currency, setCurrency] = useState("KES");
  const [supportEmail, setSupportEmail] = useState("support@juanet.co");

  const [mpesaKey, setMpesaKey] = useState("DARAJA_CONSUMER_KEY_SECURE_HASH");
  const [mpesaSecret, setMpesaSecret] = useState("DARAJA_SECRET_KEY_SECURE_HASH");
  const [mpesaShortcode, setMpesaShortcode] = useState("4023192");

  const [smtpHost, setSmtpHost] = useState("smtp.mailgun.org");
  const [smtpPort, setSmtpPort] = useState("587");
  const [smtpUser, setSmtpUser] = useState("postmaster@juanet.co");

  const [activeConfigTab, setActiveConfigTab] = useState("general");

  return (
    <div className="space-y-8">
      <div>
        <h3 className="text-xl font-display font-bold text-white flex items-center gap-2">
          <Settings size={22} className="text-indigo-400" />
          System Settings & Third-Party Integrations
        </h3>
        <p className="text-xs text-slate-400">Configure global metadata parameters, SMTP endpoints, and Safaricom Daraja credentials.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Navigation panel */}
        <div className="p-4 rounded-xl border border-slate-800 bg-slate-900/20 space-y-1.5 h-fit">
          <button
            onClick={() => setActiveConfigTab("general")}
            className={`w-full text-left px-3 py-2 rounded text-xs font-semibold font-mono transition-all uppercase flex items-center gap-1.5 ${
              activeConfigTab === "general" ? "bg-indigo-600 text-white" : "text-slate-400 hover:text-slate-200"
            }`}
          >
            <Activity size={14} /> General Settings
          </button>
          <button
            onClick={() => setActiveConfigTab("mpesa")}
            className={`w-full text-left px-3 py-2 rounded text-xs font-semibold font-mono transition-all uppercase flex items-center gap-1.5 ${
              activeConfigTab === "mpesa" ? "bg-indigo-600 text-white" : "text-slate-400 hover:text-slate-200"
            }`}
          >
            <CreditCard size={14} /> M-PESA Integration
          </button>
          <button
            onClick={() => setActiveConfigTab("smtp")}
            className={`w-full text-left px-3 py-2 rounded text-xs font-semibold font-mono transition-all uppercase flex items-center gap-1.5 ${
              activeConfigTab === "smtp" ? "bg-indigo-600 text-white" : "text-slate-400 hover:text-slate-200"
            }`}
          >
            <Mail size={14} /> SMTP Configuration
          </button>
          <button
            onClick={() => setActiveConfigTab("cicd")}
            className={`w-full text-left px-3 py-2 rounded text-xs font-semibold font-mono transition-all uppercase flex items-center gap-1.5 ${
              activeConfigTab === "cicd" ? "bg-indigo-600 text-white" : "text-slate-400 hover:text-slate-200"
            }`}
          >
            <GitBranch size={14} /> CI/CD Deployment
          </button>
        </div>

        {/* Action Panel Forms (3 cols) */}
        <div className="lg:col-span-3 p-6 rounded-xl border border-slate-800 bg-slate-900/20">
          {activeConfigTab === "general" && (
            <div className="space-y-4">
              <h4 className="text-sm font-bold text-slate-200 uppercase font-mono tracking-wide">General System Constants (`settings`)</h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-[10px] font-mono text-slate-400">APPLICATION NAME</label>
                  <input
                    type="text"
                    value={appName}
                    onChange={(e) => setAppName(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] font-mono text-slate-400">BASE CURRENCY</label>
                  <select
                    value={currency}
                    onChange={(e) => setCurrency(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
                  >
                    <option value="KES">KES (Kenyan Shilling)</option>
                    <option value="USD">USD (United States Dollar)</option>
                  </select>
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] font-mono text-slate-400">DEFAULT SUPPORT EMAIL</label>
                  <input
                    type="email"
                    value={supportEmail}
                    onChange={(e) => setSupportEmail(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
                  />
                </div>
              </div>
              <button className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-semibold transition-colors">
                Save General Settings
              </button>
            </div>
          )}

          {activeConfigTab === "mpesa" && (
            <div className="space-y-4">
              <h4 className="text-sm font-bold text-slate-200 uppercase font-mono tracking-wide">MPESA Daraja Configuration (`integrations`)</h4>
              <div className="space-y-3">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-1">
                    <label className="text-[10px] font-mono text-slate-400">CONSUMER KEY</label>
                    <input
                      type="password"
                      value={mpesaKey}
                      onChange={(e) => setMpesaKey(e.target.value)}
                      className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-slate-200 font-mono focus:outline-none focus:border-indigo-500"
                    />
                  </div>
                  <div className="space-y-1">
                    <label className="text-[10px] font-mono text-slate-400">CONSUMER SECRET</label>
                    <input
                      type="password"
                      value={mpesaSecret}
                      onChange={(e) => setMpesaSecret(e.target.value)}
                      className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-slate-200 font-mono focus:outline-none focus:border-indigo-500"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-1">
                    <label className="text-[10px] font-mono text-slate-400">PAYBILL / SHORTCODE</label>
                    <input
                      type="text"
                      value={mpesaShortcode}
                      onChange={(e) => setMpesaShortcode(e.target.value)}
                      className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-slate-200 font-mono focus:outline-none focus:border-indigo-500"
                    />
                  </div>
                  <div className="space-y-1">
                    <label className="text-[10px] font-mono text-slate-400">SANDBOX ENVIRONMENT SWITCH</label>
                    <select className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 font-mono">
                      <option value="true">SANDBOX ENABLED (TESTING)</option>
                      <option value="false">PRODUCTION (LIVE)</option>
                    </select>
                  </div>
                </div>
              </div>
              <button className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-semibold transition-colors">
                Save credentials
              </button>
            </div>
          )}

          {activeConfigTab === "smtp" && (
            <div className="space-y-4">
              <h4 className="text-sm font-bold text-slate-200 uppercase font-mono tracking-wide">SMTP Emailer parameters (`smtp_settings`)</h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-[10px] font-mono text-slate-400">SMTP SERVER HOST</label>
                  <input
                    type="text"
                    value={smtpHost}
                    onChange={(e) => setSmtpHost(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-slate-200 font-mono focus:outline-none focus:border-indigo-500"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] font-mono text-slate-400">SMTP SERVER PORT</label>
                  <input
                    type="text"
                    value={smtpPort}
                    onChange={(e) => setSmtpPort(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-slate-200 font-mono focus:outline-none focus:border-indigo-500"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] font-mono text-slate-400">SMTP ACCOUNT USERNAME</label>
                  <input
                    type="text"
                    value={smtpUser}
                    onChange={(e) => setSmtpUser(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs text-slate-200 font-mono focus:outline-none focus:border-indigo-500"
                  />
                </div>
              </div>
              <button className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-semibold transition-colors">
                Save SMTP mailer config
              </button>
            </div>
          )}

          {activeConfigTab === "cicd" && (
            <div className="space-y-4">
              <h4 className="text-sm font-bold text-slate-200 uppercase font-mono tracking-wide">GitHub Actions Automated Releases</h4>
              <div className="space-y-3 font-mono text-xs">
                <div className="flex items-center gap-3 bg-slate-950 p-2.5 rounded border border-slate-900">
                  <span className="text-[10px] text-indigo-400">01</span>
                  <span>TypeScript Compilation Checks</span>
                  <span className="ml-auto text-emerald-400 font-bold">✓ PASSED</span>
                </div>
                <div className="flex items-center gap-3 bg-slate-950 p-2.5 rounded border border-slate-900">
                  <span className="text-[10px] text-indigo-400">02</span>
                  <span>Build Container Image</span>
                  <span className="ml-auto text-emerald-400 font-bold">✓ DONE</span>
                </div>
                <div className="flex items-center gap-3 bg-slate-950 p-2.5 rounded border border-indigo-500/30">
                  <span className="text-[10px] text-indigo-400">03</span>
                  <span>Deploy to GCP Cloud Run</span>
                  <span className="ml-auto text-indigo-400 font-bold">✓ DEPLOYED</span>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

// 10. SaaS Architect AI Co-pilot Tab (Uses server-side Gemini route)
function CopilotTab() {
  const [promptInput, setPromptInput] = useState<string>("");
  const [messages, setMessages] = useState<{ role: "user" | "model"; content: string }[]>([
    {
      role: "model",
      content: `### JUANET SaaS Architect Console Co-Pilot

Hello, I am the **JUANET Senior SaaS Architect AI**. I have complete understanding of our redesigned Project Management, RBAC Employee Directory, SMTP Mailer Settings, and Lipa Na M-PESA payment flows.

Ask me any technical query about JUANET. For example:
- *Write a migration script to add automated invoices billing hooks.*
- *Explain how Safaricom Daraja's webhook callbacks are validated on the backend.*
- *How do we ensure row-level security on 'project_files' tables?*`
    }
  ]);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const scrollContainerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (scrollContainerRef.current) {
      scrollContainerRef.current.scrollTop = scrollContainerRef.current.scrollHeight;
    }
  }, [messages, isLoading]);

  const handleSendPrompt = async (textToSend?: string) => {
    const prompt = textToSend || promptInput;
    if (!prompt.trim()) return;

    setMessages(prev => [...prev, { role: "user", content: prompt }]);
    if (!textToSend) setPromptInput("");
    setIsLoading(true);

    try {
      const response = await fetch("/api/copilot", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message: prompt })
      });

      const data = await response.json();
      if (data.error) {
        setMessages(prev => [...prev, { role: "model", content: `**Error**: ${data.error}` }]);
      } else {
        setMessages(prev => [...prev, { role: "model", content: data.text }]);
      }
    } catch (err: any) {
      setMessages(prev => [...prev, { role: "model", content: `**Error**: Failed to connect to server backend. ${err.message}` }]);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h3 className="text-xl font-display font-bold text-white flex items-center gap-2">
          <Bot size={22} className="text-indigo-400" />
          Senior SaaS Architect Co-Pilot
        </h3>
        <p className="text-xs text-slate-400">Ask our virtual architect detailed system-level queries, write SQL procedures, or troubleshoot monorepo workspace issues.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Terminal Screen (2 cols) */}
        <div className="lg:col-span-2 p-4 rounded-xl border border-slate-800 bg-slate-950 font-mono text-xs flex flex-col h-[520px]">
          {/* Top terminal bar */}
          <div className="flex items-center gap-2 pb-3 border-b border-slate-900/80 mb-3 text-[10px] text-slate-500 shrink-0">
            <span className="w-2.5 h-2.5 rounded-full bg-red-500/80" />
            <span className="w-2.5 h-2.5 rounded-full bg-yellow-500/80" />
            <span className="w-2.5 h-2.5 rounded-full bg-emerald-500/80" />
            <span className="font-semibold text-slate-400 ml-2">juanet-saas-architect://terminal</span>
            <span className="ml-auto text-[9px] bg-slate-900 px-1.5 py-0.5 rounded border border-slate-800 text-indigo-400 font-bold">GEMINI 3.5 FLASH</span>
          </div>

          {/* Messages viewport */}
          <div ref={scrollContainerRef} className="flex-1 overflow-y-auto space-y-4 pr-1 scroll-smooth">
            {messages.map((m, idx) => (
              <div
                key={idx}
                className={`p-3 rounded-lg leading-relaxed ${
                  m.role === "user"
                    ? "bg-slate-900/60 border border-slate-800 text-indigo-300 ml-12"
                    : "bg-indigo-950/10 border border-indigo-500/10 text-slate-300 mr-12 text-xs"
                }`}
              >
                <div className="text-[9px] text-slate-500 mb-1 font-bold uppercase">
                  {m.role === "user" ? "USER_PROMPT" : "JUANET_ARCHITECT"}
                </div>
                <div className="whitespace-pre-line leading-relaxed text-slate-300 font-sans md:text-xs">
                  {m.content}
                </div>
              </div>
            ))}

            {isLoading && (
              <div className="flex items-center gap-2 p-3 text-slate-400">
                <RefreshCw size={14} className="animate-spin text-indigo-400" />
                <span className="italic">Architect is analyzing database schemas and RLS security boundaries...</span>
              </div>
            )}
          </div>

          {/* Prompt input */}
          <div className="mt-4 pt-3 border-t border-slate-900/80 flex gap-2 shrink-0">
            <span className="text-indigo-400 font-bold self-center text-sm pl-1">&gt;</span>
            <input
              type="text"
              value={promptInput}
              onChange={(e) => setPromptInput(e.target.value)}
              placeholder="Ask for custom migrations, auth payloads, or deployment scripts..."
              className="flex-1 bg-transparent border-none focus:outline-none text-slate-200 text-xs"
              onKeyDown={(e) => { if (e.key === "Enter") handleSendPrompt(); }}
              disabled={isLoading}
            />
            <button
              onClick={() => handleSendPrompt()}
              disabled={isLoading || !promptInput.trim()}
              className="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors font-semibold disabled:opacity-40"
            >
              SEND
            </button>
          </div>
        </div>

        {/* Quick query tags (1 col) */}
        <div className="p-5 rounded-xl border border-slate-800/80 bg-slate-900/40 space-y-4 text-xs h-fit">
          <div>
            <h4 className="font-semibold text-slate-200 font-mono">Architect Quick Inquiries</h4>
            <p className="text-[11px] text-slate-400 mt-0.5">Quickly consult with our architect on key design requirements using preset templates.</p>
          </div>

          <div className="space-y-2 flex flex-col">
            <button
              onClick={() => handleSendPrompt("Explain how our 5-table RBAC system handles user permission assertions.")}
              disabled={isLoading}
              className="text-left p-2.5 rounded-lg border border-slate-800/80 bg-slate-950 text-slate-300 hover:border-slate-700 transition-all font-mono text-[11px]"
            >
              &bull; RBAC Roles & Permissions Mapping
            </button>
            <button
              onClick={() => handleSendPrompt("Write the SQL trigger function for automatic payments clearing inside payments ledger table.")}
              disabled={isLoading}
              className="text-left p-2.5 rounded-lg border border-slate-800/80 bg-slate-950 text-slate-300 hover:border-slate-700 transition-all font-mono text-[11px]"
            >
              &bull; DB trigger for Ledger Payments
            </button>
            <button
              onClick={() => handleSendPrompt("Explain how Lipa Na M-PESA webhook callback signatures are computed & secured.")}
              disabled={isLoading}
              className="text-left p-2.5 rounded-lg border border-slate-800/80 bg-slate-950 text-slate-300 hover:border-slate-700 transition-all font-mono text-[11px]"
            >
              &bull; Daraja Callback Security Hash
            </button>
            <button
              onClick={() => handleSendPrompt("Write the complete Row-Level Security (RLS) policies for our project_files and client_dashboard tables.")}
              disabled={isLoading}
              className="text-left p-2.5 rounded-lg border border-slate-800/80 bg-slate-950 text-slate-300 hover:border-slate-700 transition-all font-mono text-[11px]"
            >
              &bull; Complete RLS for Project Vault
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

// Custom Markdown renderer helpers
const renderFormattedLine = (text: string) => {
  let parts: (string | React.ReactNode)[] = [text];
  
  if (text.includes("**")) {
    const newParts: (string | React.ReactNode)[] = [];
    parts.forEach(part => {
      if (typeof part === "string") {
        const splitParts = part.split("**");
        splitParts.forEach((subPart, idx) => {
          if (idx % 2 === 1) {
            newParts.push(<strong key={`b-${idx}`} className="text-white font-bold">{subPart}</strong>);
          } else {
            newParts.push(subPart);
          }
        });
      } else {
        newParts.push(part);
      }
    });
    parts = newParts;
  }
  
  if (text.includes("`")) {
    const newParts: (string | React.ReactNode)[] = [];
    parts.forEach(part => {
      if (typeof part === "string") {
        const splitParts = part.split("`");
        splitParts.forEach((subPart, idx) => {
          if (idx % 2 === 1) {
            newParts.push(<code key={`c-${idx}`} className="bg-slate-900 text-indigo-300 px-1.5 py-0.5 rounded font-mono text-[11px] border border-slate-800/60">{subPart}</code>);
          } else {
            newParts.push(subPart);
          }
        });
      } else {
        newParts.push(part);
      }
    });
    parts = newParts;
  }
  
  return <>{parts}</>;
};

function parseMarkdown(text: string) {
  const lines = text.split("\n");
  let inCodeBlock = false;
  let codeBlockContent: string[] = [];
  const renderedElements: React.ReactNode[] = [];
  
  lines.forEach((line, index) => {
    if (line.trim().startsWith("```")) {
      if (inCodeBlock) {
        renderedElements.push(
          <pre key={`code-${index}`} className="bg-slate-950 p-4 rounded-lg border border-slate-900 font-mono text-xs text-indigo-300 overflow-x-auto my-3 select-text">
            <code>{codeBlockContent.join("\n")}</code>
          </pre>
        );
        codeBlockContent = [];
        inCodeBlock = false;
      } else {
        inCodeBlock = true;
      }
      return;
    }
    
    if (inCodeBlock) {
      codeBlockContent.push(line);
      return;
    }
    
    const trimmed = line.trim();
    if (trimmed.startsWith("# ")) {
      renderedElements.push(<h1 key={index} className="text-2xl font-bold font-display text-white mt-6 mb-3 border-b border-slate-900 pb-2 select-text">{trimmed.substring(2)}</h1>);
    } else if (trimmed.startsWith("## ")) {
      renderedElements.push(<h2 key={index} className="text-xl font-semibold font-display text-indigo-300 mt-5 mb-2.5 select-text">{trimmed.substring(3)}</h2>);
    } else if (trimmed.startsWith("### ")) {
      renderedElements.push(<h3 key={index} className="text-lg font-medium font-display text-slate-200 mt-4 mb-2 select-text">{trimmed.substring(4)}</h3>);
    } else if (trimmed.startsWith("#### ")) {
      renderedElements.push(<h4 key={index} className="text-base font-medium font-display text-slate-300 mt-3 mb-1.5 select-text">{trimmed.substring(5)}</h4>);
    } else if (trimmed.startsWith("* ") || trimmed.startsWith("- ")) {
      renderedElements.push(<li key={index} className="ml-5 list-disc text-slate-300 text-xs my-1 select-text">{renderFormattedLine(trimmed.substring(2))}</li>);
    } else if (trimmed.startsWith("|")) {
      renderedElements.push(
        <div key={index} className="font-mono text-[11px] bg-slate-900/40 border-b border-slate-900 px-4 py-1.5 text-slate-300 flex select-all">
          {renderFormattedLine(trimmed)}
        </div>
      );
    } else if (trimmed === "") {
      renderedElements.push(<div key={index} className="h-2" />);
    } else {
      renderedElements.push(<p key={index} className="text-slate-300 text-xs leading-relaxed my-1.5 select-text">{renderFormattedLine(trimmed)}</p>);
    }
  });
  
  return renderedElements;
}

function SpecsExplorerTab() {
  const [docsList, setDocsList] = useState<{ name: string; path: string; category: string }[]>([]);
  const [selectedDoc, setSelectedDoc] = useState<{ name: string; path: string; category: string } | null>(null);
  const [docContent, setDocContent] = useState<string>("");
  const [loadingList, setLoadingList] = useState<boolean>(true);
  const [loadingContent, setLoadingContent] = useState<boolean>(false);
  const [searchQuery, setSearchQuery] = useState<string>("");

  useEffect(() => {
    async function fetchDocs() {
      try {
        setLoadingList(true);
        const res = await fetch("/api/docs");
        const data = await res.json();
        if (data.files) {
          setDocsList(data.files);
          const master = data.files.find((f: any) => f.path === "JUANET_Master_Specification.md");
          if (master) {
            setSelectedDoc(master);
          } else if (data.files.length > 0) {
            setSelectedDoc(data.files[0]);
          }
        }
      } catch (err) {
        console.error("Failed to load documents list:", err);
      } finally {
        setLoadingList(false);
      }
    }
    fetchDocs();
  }, []);

  useEffect(() => {
    if (!selectedDoc) return;
    async function fetchDocContent() {
      try {
        setLoadingContent(true);
        const res = await fetch(`/api/docs/content?path=${encodeURIComponent(selectedDoc.path)}`);
        const data = await res.json();
        if (data.content) {
          setDocContent(data.content);
        } else if (data.error) {
          setDocContent(`Error loading document: ${data.error}`);
        }
      } catch (err: any) {
        setDocContent(`Failed to connect to backend: ${err.message}`);
      } finally {
        setLoadingContent(false);
      }
    }
    fetchDocContent();
  }, [selectedDoc]);

  const filteredDocs = docsList.filter(d => 
    d.name.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className="space-y-6">
      <div>
        <h3 className="text-xl font-display font-bold text-white flex items-center gap-2">
          <FileText size={22} className="text-indigo-400" />
          SaaS Specification Documents & Phase Blueprints
        </h3>
        <p className="text-xs text-slate-400">
          Directly explore the architectural specifications, phase documentation, and database blueprints running in the JUANET environment.
        </p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 h-[700px]">
        {/* Left Sidebar Document Picker */}
        <div className="lg:col-span-1 bg-slate-900/20 border border-slate-800 rounded-xl p-4 flex flex-col h-full overflow-hidden">
          <div className="mb-4">
            <input
              type="text"
              placeholder="Search specifications..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-slate-950 border border-slate-800 rounded px-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div className="flex-1 overflow-y-auto space-y-4 pr-1">
            {loadingList ? (
              <div className="flex items-center justify-center py-10 text-xs text-slate-500 gap-2">
                <RefreshCw size={14} className="animate-spin text-indigo-400" />
                <span>Scanning workspace...</span>
              </div>
            ) : filteredDocs.length === 0 ? (
              <div className="text-center text-xs text-slate-600 py-10 font-mono">No documents found matching search query.</div>
            ) : (
              Array.from(new Set(filteredDocs.map(d => d.category))).sort().map((cat: string) => {
                const catDocs = filteredDocs.filter(d => d.category === cat);
                if (catDocs.length === 0) return null;
                const displayCategory = cat.replace(/^\d+\s+/, "");
                return (
                  <div key={cat} className="space-y-1">
                    <span className="text-[9px] font-mono font-bold text-indigo-400 uppercase tracking-widest block px-2 mb-1.5">
                      {displayCategory}
                    </span>
                    <div className="space-y-0.5">
                      {catDocs.map(doc => {
                        const isSelected = selectedDoc?.path === doc.path;
                        return (
                          <button
                            key={doc.path}
                            onClick={() => setSelectedDoc(doc)}
                            className={`w-full text-left text-xs px-2.5 py-2 rounded-lg transition-all border flex items-start gap-2 ${
                              isSelected
                                ? "bg-indigo-600/15 border-indigo-500 text-indigo-300 font-semibold"
                                : "bg-transparent border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-900/40"
                            }`}
                          >
                            <FileText size={14} className={`shrink-0 mt-0.5 ${isSelected ? "text-indigo-400" : "text-slate-500"}`} />
                            <span className="truncate">{doc.name}</span>
                          </button>
                        );
                      })}
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </div>

        {/* Right Content Viewer */}
        <div className="lg:col-span-3 bg-slate-950 border border-slate-800 rounded-xl flex flex-col h-full overflow-hidden">
          {/* Header */}
          <div className="px-5 py-4 border-b border-slate-900 flex justify-between items-center shrink-0 bg-slate-900/10">
            <div>
              <h4 className="text-sm font-bold text-slate-100 flex items-center gap-1.5 font-display">
                <Eye size={15} className="text-indigo-400" />
                {selectedDoc ? selectedDoc.name : "Document Reader"}
              </h4>
              <span className="text-[10px] font-mono text-slate-500 uppercase mt-0.5 block">
                Path: {selectedDoc ? selectedDoc.path : "N/A"}
              </span>
            </div>
            {selectedDoc && (
              <span className="text-[9px] font-mono bg-slate-900 text-indigo-400 px-2 py-0.5 rounded border border-slate-800 font-bold">
                {selectedDoc.category}
              </span>
            )}
          </div>

          {/* Reader Viewport */}
          <div className="flex-1 overflow-y-auto p-6 md:p-8 space-y-2 select-text selection:bg-indigo-600/30 scroll-smooth">
            {loadingContent ? (
              <div className="flex flex-col items-center justify-center h-full py-20 text-xs text-slate-500 gap-2">
                <RefreshCw size={18} className="animate-spin text-indigo-400" />
                <span>Reading document columns...</span>
              </div>
            ) : docContent ? (
              parseMarkdown(docContent)
            ) : (
              <div className="text-center text-slate-600 font-mono text-xs py-20">
                Select a blueprint document to parse and display its content.
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
