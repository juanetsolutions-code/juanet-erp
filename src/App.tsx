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
  UserCheck
} from "lucide-react";

import {
  DB_SCHEMAS,
  API_ENDPOINTS,
  SERVICE_ITEMS,
  MONOREPO_STRUCTURE,
  TableSchema,
  ApiEndpoint
} from "./data/architectureData";

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
            <SidebarButton active={activeTab === "messaging"} icon={<MessageSquare size={16} />} label="Messaging & File Vault" onClick={() => setActiveTab("messaging")} />
            <SidebarButton active={activeTab === "payments"} icon={<CreditCard size={16} />} label="Enterprise Payments Hub" onClick={() => setActiveTab("payments")} />
            <SidebarButton active={activeTab === "blog"} icon={<BookOpen size={16} />} label="SEO Blog CMS" onClick={() => setActiveTab("blog")} />
            <SidebarButton active={activeTab === "deployment"} icon={<Settings size={16} />} label="Admin Integrations" onClick={() => setActiveTab("deployment")} />
            <SidebarButton active={activeTab === "copilot"} icon={<Bot size={16} />} label="SaaS Architect Co-Pilot" onClick={() => setActiveTab("copilot")} />
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
              {activeTab === "blog" && (
                <BlogTab comments={comments} setComments={setComments} />
              )}
              {activeTab === "deployment" && <DeploymentTab />}
              {activeTab === "copilot" && <CopilotTab />}
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

  return (
    <div className="space-y-8">
      <div>
        <h3 className="text-xl font-display font-bold text-white flex items-center gap-2">
          <MessageSquare size={22} className="text-indigo-400" />
          Project Communications & Attachments Vault
        </h3>
        <p className="text-xs text-slate-400">Track milestones updates and secure project files delivery lists.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Project logs & updates */}
        <div className="p-6 rounded-xl border border-slate-800 bg-slate-900/20 space-y-4">
          <h4 className="text-sm font-bold text-slate-200">Submit Progress Update Log (`project_updates`)</h4>
          <form onSubmit={submitUpdate} className="flex gap-2">
            <input
              type="text"
              value={updateText}
              onChange={(e) => setUpdateText(e.target.value)}
              placeholder="Submit daily project progress logs..."
              className="flex-1 bg-slate-950 border border-slate-800 rounded px-3 py-2 text-xs focus:outline-none focus:border-indigo-500 text-slate-300"
              required
            />
            <button
              type="submit"
              className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-semibold flex items-center gap-1 transition-all"
            >
              <Send size={12} /> Log Update
            </button>
          </form>

          <div className="space-y-3 pt-3 border-t border-slate-900">
            <span className="text-[9px] font-mono text-slate-500 uppercase block">Historical Updates:</span>
            <div className="space-y-2 max-h-60 overflow-y-auto pr-1">
              {projectUpdates.map(up => (
                <div key={up.id} className="p-3 bg-slate-950 border border-slate-900 rounded text-xs">
                  <div className="flex justify-between font-mono text-indigo-400 mb-1 text-[10px]">
                    <span>SYSTEM_LOG_{up.id}</span>
                    <span>{up.date}</span>
                  </div>
                  <p className="text-slate-300 leading-relaxed">{up.text}</p>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* File directory drag-drop */}
        <div className="p-6 rounded-xl border border-slate-800 bg-slate-900/20 space-y-4">
          <h4 className="text-sm font-bold text-slate-200">Secure Attachment Deliverables Vault (`project_files`)</h4>
          
          <div
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onDrop={handleDrop}
            onClick={() => fileInputRef.current?.click()}
            className={`p-6 rounded-lg border-2 border-dashed text-center cursor-pointer transition-all ${
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
            <FolderOpen className="mx-auto text-indigo-400 mb-2" size={32} />
            <p className="text-xs font-semibold text-slate-300">Drag & drop deliverables here, or click to browse</p>
            <p className="text-[10px] text-slate-500 mt-1 uppercase font-mono">SUPPORTS ARCHITECTURAL SPEC PDFS, CODES, WIREFRAMES</p>
          </div>

          <div className="space-y-2 pt-3 border-t border-slate-900">
            <span className="text-[9px] font-mono text-slate-500 uppercase block">Active Delivery Directory attachments:</span>
            <div className="space-y-1.5 max-h-40 overflow-y-auto pr-1">
              {projectFiles.map(file => (
                <div key={file.id} className="flex justify-between items-center p-2 rounded bg-slate-950/60 border border-slate-900 text-xs">
                  <div className="flex items-center gap-2">
                    <FileText size={14} className="text-indigo-400" />
                    <div>
                      <span className="font-bold text-slate-300 block text-[11px] truncate max-w-[200px]">{file.name}</span>
                      <span className="text-[9px] text-slate-500 font-mono block uppercase">{file.type} &bull; {file.size}</span>
                    </div>
                  </div>
                  <button className="p-1 text-slate-500 hover:text-slate-300 transition-colors">
                    <Download size={14} />
                  </button>
                </div>
              ))}
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
function BlogTab({ comments, setComments }: { comments: any[]; setComments: React.Dispatch<React.SetStateAction<any[]>> }) {
  const [commentName, setCommentName] = useState("");
  const [commentText, setCommentText] = useState("");
  const [selectedPostId, setSelectedPostId] = useState("post-1");

  const blogPosts = [
    {
      id: "post-1",
      title: "Building Resilient Financial Audits with MPESA Daraja API & Postgres Ledger",
      slug: "mpesa-daraja-api-audit",
      excerpt: "Explore deep integration strategies for Safaricom Paybill callbacks, validating request structures, and maintaining transaction isolation.",
      category: "Cloud Engineering",
      author: "Juan",
      date: "June 25, 2026"
    }
  ];

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
  };

  const activeComments = comments.filter(c => c.postId === selectedPostId);

  return (
    <div className="space-y-8">
      <div>
        <h3 className="text-xl font-display font-bold text-white flex items-center gap-2">
          <BookOpen size={22} className="text-indigo-400" />
          JUANET Marketing & Headless CMS System
        </h3>
        <p className="text-xs text-slate-400">Marketing platform and blog postings managing metadata directories and user communities.</p>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {/* Blog Post List & Body (2 cols) */}
        <div className="xl:col-span-2 space-y-6">
          {blogPosts.map(post => (
            <div key={post.id} className="p-6 rounded-xl border border-slate-800 bg-slate-900/20 space-y-4">
              <div>
                <span className="bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase">
                  {post.category}
                </span>
                <h4 className="text-lg font-bold text-slate-200 mt-2 leading-snug">{post.title}</h4>
                <div className="flex items-center gap-2 text-[10px] text-slate-500 pt-1 font-mono">
                  <span>BY {post.author.toUpperCase()}</span>
                  <span>&bull;</span>
                  <span>{post.date}</span>
                </div>
              </div>
              <p className="text-xs text-slate-400 leading-relaxed">{post.excerpt}</p>
              <div className="p-4 rounded-lg bg-slate-950 border border-slate-900 text-xs text-slate-300 space-y-2 leading-relaxed">
                <p>When implementing asynchronous transaction checkouts (Lipa Na M-PESA online), your API router must receive callbacks at `/api/payments/mpesa-callback` which Safaricom triggers as POST requests. The primary danger of transactions is double-spending or fake signature injections.</p>
                <p>By enforcing composite key constraints (`CheckoutRequestID`) inside the payments ledger table and verifying incoming payload checksums, the platform completely immunizes our accounting columns from external manipulations.</p>
              </div>
            </div>
          ))}
        </div>

        {/* Comment sections */}
        <div className="p-6 rounded-xl border border-slate-800 bg-slate-900/20 space-y-4 h-fit">
          <h4 className="text-xs font-mono text-slate-400 font-extrabold uppercase">Community Discussions (`blog_comments`)</h4>
          
          <form onSubmit={submitComment} className="space-y-2">
            <input
              type="text"
              placeholder="Your Name"
              value={commentName}
              onChange={(e) => setCommentName(e.target.value)}
              className="w-full bg-slate-950 border border-slate-800 rounded px-2.5 py-1.5 text-[11px] focus:outline-none focus:border-indigo-500 text-slate-300 font-sans"
              required
            />
            <textarea
              placeholder="Join the discussion..."
              value={commentText}
              onChange={(e) => setCommentText(e.target.value)}
              rows={2}
              className="w-full bg-slate-950 border border-slate-800 rounded px-2.5 py-1.5 text-[11px] focus:outline-none focus:border-indigo-500 text-slate-300 font-sans"
              required
            />
            <button
              type="submit"
              className="w-full py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[10px] font-bold uppercase transition-colors"
            >
              Post Comment
            </button>
          </form>

          <div className="space-y-2 pt-2 border-t border-slate-900 max-h-60 overflow-y-auto pr-1">
            {activeComments.map(c => (
              <div key={c.id} className="p-3 rounded bg-slate-950 text-xs border border-slate-900 text-slate-400 space-y-1">
                <div className="flex justify-between font-mono text-indigo-400 text-[10px]">
                  <span>{c.author}</span>
                  <span>{c.date}</span>
                </div>
                <p className="text-slate-300 leading-normal">{c.text}</p>
              </div>
            ))}
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
