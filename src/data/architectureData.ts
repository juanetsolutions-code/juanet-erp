export interface TableSchema {
  name: string;
  description: string;
  columns: { name: string; type: string; constraints: string; desc: string }[];
  indexes: string[];
  rlsPolicies: string[];
}

export interface ApiEndpoint {
  method: "GET" | "POST" | "PUT" | "DELETE" | "PATCH";
  path: string;
  description: string;
  roles: string[];
  requestBody?: string;
  responseBody: string;
}

export const MONOREPO_STRUCTURE = [
  {
    name: "JUANET Enterprise Operating System (Monorepo)",
    type: "root",
    children: [
      {
        name: "apps",
        type: "folder",
        children: [
          { name: "marketing-site", type: "app", desc: "Next.js 14 / Tailwind public portal featuring headless Website CMS, Portfolio Showcase, Blog, and CRM Lead generation." },
          { name: "client-dashboard", type: "app", desc: "React / Vite customer workspace. Project milestone approval, Central File vault, Ticket support, Invoice clearing, and MPESA checkouts." },
          { name: "admin-dashboard", type: "app", desc: "React / Vite agency Command Center. RBAC controls, CRM leads, Workflow engines, AI proposal generators, and SMTP mailer logs." },
          { name: "api", type: "app", desc: "Node.js / Express backend server handling routing, auth integrations, webhooks, and real-time WebSockets." }
        ]
      },
      {
        name: "services",
        type: "folder",
        children: [
          { name: "ai-service", type: "service", desc: "Independent backend microservice orchestrating Gemini routing, prompt templates versioning, context compression, and fallbacks." },
          { name: "notification-service", type: "service", desc: "Handles multi-channel notification dispatch queues (In-app, SMTP Email, Webhooks, and SMS)." },
          { name: "billing-service", type: "service", desc: "Manages ledger transactions, invoices clearance triggers, tax rates, and secure Daraja M-PESA webhook callbacks." },
          { name: "storage-service", type: "service", desc: "Co-ordinates global file upload pipelines, version indexing, temporary asset link signatures, and background antivirus scans." },
          { name: "workflow-service", type: "service", desc: "Automated event-trigger-action scheduler processing conditional delays and system notifications." }
        ]
      },
      {
        name: "packages",
        type: "folder",
        children: [
          { name: "ui", type: "package", desc: "Shared enterprise Tailwind components and design tokens supporting dark/light UI frameworks." },
          { name: "utils", type: "package", desc: "Shared helpers containing M-PESA SHA256 checksum generators, math engines, and security validators." },
          { name: "types", type: "package", desc: "Shared database schema entities and request/response API validation types." }
        ]
      },
      { name: "turbo.json", type: "file", desc: "Turborepo remote pipeline cache rules." },
      { name: "package.json", type: "file", desc: "Universal dependencies resolution map." }
    ]
  }
];

export const DB_SCHEMAS: TableSchema[] = [
  // ==========================================
  // SECTION 1: ORGANIZATIONS & TENANCY (Core)
  // ==========================================
  {
    name: "organizations",
    description: "Multi-tenant tenant profiles enabling future SaaS company partitioning across all tables.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Unique tenant company ID" },
      { name: "name", type: "varchar(255)", constraints: "NOT NULL", desc: "Company name (e.g., JUANET Tech)" },
      { name: "slug", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "Subdomain or URL identifier" },
      { name: "logo_url", type: "text", constraints: "NULL", desc: "Public link of corporate branding asset" },
      { name: "settings", type: "jsonb", constraints: "NOT NULL DEFAULT '{}'::jsonb", desc: "Default localization, workspace configurations" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Tenancy enrollment date" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX org_slug_idx ON public.organizations(slug);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Members can view their own organization\" ON public.organizations FOR SELECT USING (EXISTS (SELECT 1 FROM public.organization_members WHERE organization_id = id AND user_id = auth.uid()));",
      "CREATE POLICY \"Superadmins can manage organizations\" ON public.organizations TO superadmin USING (true);"
    ]
  },
  {
    name: "organization_members",
    description: "Junction table mapping system profiles to organizations with membership scopes.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Junction record ID" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Linked tenant company" },
      { name: "user_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE", desc: "Linked system user profile" },
      { name: "role", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'member' CHECK (role IN ('owner', 'admin', 'member'))", desc: "Administrative authority in the company scope" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Affiliation record date" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX org_user_composite_idx ON public.organization_members(organization_id, user_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Members view colleagues in shared tenant\" ON public.organization_members FOR SELECT USING (organization_id = ANY (SELECT organization_id FROM public.organization_members WHERE user_id = auth.uid()));"
    ]
  },

  // ==========================================
  // SECTION 2: IDENTITY & PROFILE LAYERS
  // ==========================================
  {
    name: "profiles",
    description: "SaaS profile layer separating business details from core Supabase auth attributes.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE", desc: "Supabase authentication identity link" },
      { name: "full_name", type: "varchar(255)", constraints: "NOT NULL", desc: "Full display name" },
      { name: "avatar_url", type: "text", constraints: "NULL", desc: "Central avatar graphic storage link" },
      { name: "timezone", type: "varchar(100)", constraints: "NOT NULL DEFAULT 'Africa/Nairobi'", desc: "User local time zone offset" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Onboarding completed timestamp" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Profiles are readable by organization colleagues\" ON public.profiles FOR SELECT USING (true);",
      "CREATE POLICY \"Users can update their own profile details\" ON public.profiles FOR UPDATE USING (id = auth.uid());"
    ]
  },
  {
    name: "user_preferences",
    description: "Saves individual custom client and staff UI states, notifications filters, and theme settings.",
    columns: [
      { name: "user_id", type: "uuid", constraints: "PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE", desc: "Target system user" },
      { name: "theme", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'dark' CHECK (theme IN ('light', 'dark', 'system'))", desc: "Workspace preference" },
      { name: "email_alerts", type: "boolean", constraints: "NOT NULL DEFAULT true", desc: "Permission indicator for transactional notifications" },
      { name: "push_alerts", type: "boolean", constraints: "NOT NULL DEFAULT true", desc: "Real-time socket alerts permission flag" },
      { name: "updated_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Time of last preference modification" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Users manage own preferences\" ON public.user_preferences USING (user_id = auth.uid());"
    ]
  },
  {
    name: "user_sessions",
    description: "Security record logging user login attempts, devices, geo-IP metadata, and session tokens.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Session record ID" },
      { name: "user_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE", desc: "Associated user profile" },
      { name: "ip_address", type: "varchar(45)", constraints: "NULL", desc: "Originating network address" },
      { name: "user_agent", type: "text", constraints: "NULL", desc: "Browser fingerprinting signature" },
      { name: "is_active", type: "boolean", constraints: "NOT NULL DEFAULT true", desc: "Current token session validity indicator" },
      { name: "last_active_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Activity heartbeat" }
    ],
    indexes: [
      "CREATE INDEX session_user_idx ON public.user_sessions(user_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Users see own active sessions\" ON public.user_sessions FOR SELECT USING (user_id = auth.uid());"
    ]
  },
  {
    name: "api_keys",
    description: "Developer and corporate tokens enabling secure client system calls.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Token key record token" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Owner tenant context" },
      { name: "key_prefix", type: "varchar(10)", constraints: "NOT NULL", desc: "Visible token prefix (e.g. jn_live_)" },
      { name: "key_hash", type: "varchar(255)", constraints: "NOT NULL UNIQUE", desc: "Cryptographically hashed API key value" },
      { name: "description", type: "varchar(255)", constraints: "NULL", desc: "System description (e.g., M-PESA Callback Integration)" },
      { name: "is_active", type: "boolean", constraints: "NOT NULL DEFAULT true", desc: "Active toggle" },
      { name: "expires_at", type: "timestamp with time zone", constraints: "NULL", desc: "Optional token validity end" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX key_hash_idx ON public.api_keys(key_hash);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Admins can manage api keys\" ON public.api_keys USING (EXISTS (SELECT 1 FROM public.organization_members WHERE organization_id = public.api_keys.organization_id AND user_id = auth.uid() AND role IN ('owner', 'admin')));"
    ]
  },

  // ==========================================
  // SECTION 3: CENTRAL FILE SYSTEM
  // ==========================================
  {
    name: "folders",
    description: "Directories managing nested cloud storage buckets and project documentation structures.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Directory ID" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Parent organization context" },
      { name: "parent_id", type: "uuid", constraints: "NULL REFERENCES public.folders(id) ON DELETE CASCADE", desc: "Recursive reference for unlimited folder nesting" },
      { name: "name", type: "varchar(255)", constraints: "NOT NULL", desc: "Folder name" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Folder generation timestamp" }
    ],
    indexes: [
      "CREATE INDEX folder_org_idx ON public.folders(organization_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Access folders based on organization membership\" ON public.folders USING (EXISTS (SELECT 1 FROM public.organization_members WHERE organization_id = public.folders.organization_id AND user_id = auth.uid()));"
    ]
  },
  {
    name: "files",
    description: "Metadata database indexing uploads, scanning status, sizes, and bucket hashes.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Central asset metadata ID" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Billing tenant" },
      { name: "folder_id", type: "uuid", constraints: "NULL REFERENCES public.folders(id) ON DELETE SET NULL", desc: "Filing system directory" },
      { name: "uploader_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Author profile" },
      { name: "name", type: "varchar(255)", constraints: "NOT NULL", desc: "File name with extension" },
      { name: "file_path", type: "text", constraints: "NOT NULL", desc: "Raw bucket filepath reference" },
      { name: "size", type: "bigint", constraints: "NOT NULL", desc: "File volume in bytes" },
      { name: "mime_type", type: "varchar(150)", constraints: "NOT NULL", desc: "Content-type metadata" },
      { name: "virus_scan", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'pending' CHECK (virus_scan IN ('pending', 'clean', 'infected'))", desc: "Security gateway checker status" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Upload log date" }
    ],
    indexes: [
      "CREATE INDEX file_folder_idx ON public.files(folder_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Manage files based on shared organization tenancy\" ON public.files USING (EXISTS (SELECT 1 FROM public.organization_members WHERE organization_id = public.files.organization_id AND user_id = auth.uid()));"
    ]
  },
  {
    name: "file_versions",
    description: "Keeps a history of file replacements and iterations for designers and developers.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "History record ID" },
      { name: "file_id", type: "uuid", constraints: "NOT NULL REFERENCES public.files(id) ON DELETE CASCADE", desc: "Linked parent file" },
      { name: "editor_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Staff or client author of change" },
      { name: "file_path", type: "text", constraints: "NOT NULL", desc: "Older archive path inside the bucket" },
      { name: "version_number", type: "integer", constraints: "NOT NULL DEFAULT 1", desc: "Incremental version number" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Log timestamp" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Read file history in the org\" ON public.file_versions USING (EXISTS (SELECT 1 FROM public.files JOIN public.organization_members ON files.organization_id = organization_members.organization_id WHERE files.id = file_id AND organization_members.user_id = auth.uid()));"
    ]
  },

  // ==========================================
  // SECTION 4: CRM (Leads, Companies, Contacts)
  // ==========================================
  {
    name: "leads",
    description: "Database recording incoming client sales pitches, source vectors, and pipeline steps.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Lead identifier" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Owning company scope" },
      { name: "source", type: "varchar(100)", constraints: "NOT NULL DEFAULT 'website' CHECK (source IN ('website', 'email', 'referral', 'linkedin', 'direct'))", desc: "Funnel entry point source" },
      { name: "company_name", type: "varchar(255)", constraints: "NULL", desc: "Company target name" },
      { name: "contact_name", type: "varchar(255)", constraints: "NOT NULL", desc: "Main decision maker name" },
      { name: "email", type: "varchar(255)", constraints: "NOT NULL", desc: "Lead email" },
      { name: "budget", type: "numeric(12,2)", constraints: "NULL", desc: "Estimated lead budget value" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'new' CHECK (status IN ('new', 'contacted', 'qualified', 'proposal_sent', 'lost', 'won'))", desc: "Sales lifecycle pipeline status" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Lead creation date" }
    ],
    indexes: [
      "CREATE INDEX lead_status_idx ON public.leads(status);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Employees view CRM leads\" ON public.leads TO authenticated USING (true);"
    ]
  },
  {
    name: "companies",
    description: "Enterprise catalog of active corporate accounts and billing profiles.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Company entity ID" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Owner tenant scope" },
      { name: "name", type: "varchar(255)", constraints: "NOT NULL UNIQUE", desc: "Official business name" },
      { name: "website", type: "varchar(255)", constraints: "NULL", desc: "Domain URI" },
      { name: "billing_address", type: "text", constraints: "NULL", desc: "Tax/billing physical coordinates" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Registration date" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Employees view business accounts\" ON public.companies TO authenticated USING (true);"
    ]
  },
  {
    name: "contacts",
    description: "Database recording employees and contacts working inside registered client companies.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Contact unique ID" },
      { name: "company_id", type: "uuid", constraints: "NOT NULL REFERENCES public.companies(id) ON DELETE CASCADE", desc: "Parent business entity" },
      { name: "first_name", type: "varchar(100)", constraints: "NOT NULL", desc: "Given name" },
      { name: "last_name", type: "varchar(100)", constraints: "NOT NULL", desc: "Surname" },
      { name: "email", type: "varchar(255)", constraints: "NOT NULL UNIQUE", desc: "Communication endpoint" },
      { name: "phone_number", type: "varchar(50)", constraints: "NULL", desc: "Mobile number" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Record log" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"CRM contacts visible to employees\" ON public.contacts TO authenticated USING (true);"
    ]
  },
  {
    name: "lead_activities",
    description: "CRM timeline tracing phone calls, emails, notes, and progress updates for client proposals.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Activity record identifier" },
      { name: "lead_id", type: "uuid", constraints: "NOT NULL REFERENCES public.leads(id) ON DELETE CASCADE", desc: "Linked pipeline lead" },
      { name: "author_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Assigned sales engineer profile ID" },
      { name: "activity_type", type: "varchar(50)", constraints: "NOT NULL CHECK (activity_type IN ('email', 'call', 'meeting', 'note', 'status_change'))", desc: "Action category" },
      { name: "notes", type: "text", constraints: "NOT NULL", desc: "Verbatim meeting or summary logs" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Log date" }
    ],
    indexes: [
      "CREATE INDEX activity_lead_idx ON public.lead_activities(lead_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Employees read activity timeline\" ON public.lead_activities TO authenticated USING (true);"
    ]
  },

  // ==========================================
  // SECTION 5: PROPOSALS & QUOTATIONS
  // ==========================================
  {
    name: "proposal_templates",
    description: "Blueprints storing scope blocks, SLA clauses, and estimated financial templates.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Template template ID" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Owner tenant context" },
      { name: "title", type: "varchar(255)", constraints: "NOT NULL", desc: "Scope template name" },
      { name: "content", type: "text", constraints: "NOT NULL", desc: "Rich structural layout and markdown details" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Creation record log" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Templates managed inside the organization\" ON public.proposal_templates USING (EXISTS (SELECT 1 FROM public.organization_members WHERE organization_id = public.proposal_templates.organization_id AND user_id = auth.uid()));"
    ]
  },
  {
    name: "proposals",
    description: "Professional service bids, consultation estimates, and SLAs issued for client validation.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Contract spec ID" },
      { name: "lead_id", type: "uuid", constraints: "NULL REFERENCES public.leads(id) ON DELETE SET NULL", desc: "Linked pipeline lead" },
      { name: "client_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Client recipient" },
      { name: "title", type: "varchar(255)", constraints: "NOT NULL", desc: "Title (e.g. WiFi Deployment Scope)" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'revised', 'accepted', 'expired', 'declined'))", desc: "Quotation status" },
      { name: "total_amount", type: "numeric(12,2)", constraints: "NOT NULL", desc: "Aggregate proposal valuation" },
      { name: "expires_at", type: "date", constraints: "NOT NULL", desc: "Quotation expiration constraint" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Date of compilation" }
    ],
    indexes: [
      "CREATE INDEX proposal_client_idx ON public.proposals(client_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Clients see proposals assigned to them\" ON public.proposals FOR SELECT USING (client_id = auth.uid() OR EXISTS (SELECT 1 FROM public.employees WHERE user_id = auth.uid()));"
    ]
  },
  {
    name: "proposal_items",
    description: "Line-by-line service estimations mapped inside active proposals.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Item record token" },
      { name: "proposal_id", type: "uuid", constraints: "NOT NULL REFERENCES public.proposals(id) ON DELETE CASCADE", desc: "Parent proposal scope" },
      { name: "description", type: "text", constraints: "NOT NULL", desc: "Work block description" },
      { name: "quantity", type: "numeric(10,2)", constraints: "NOT NULL DEFAULT 1", desc: "Units" },
      { name: "unit_price", type: "numeric(12,2)", constraints: "NOT NULL", desc: "Unit price valuation" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Items accessible through parent proposals\" ON public.proposal_items FOR SELECT USING (EXISTS (SELECT 1 FROM public.proposals WHERE id = proposal_id AND (client_id = auth.uid() OR EXISTS (SELECT 1 FROM public.employees WHERE user_id = auth.uid()))));"
    ]
  },

  // ==========================================
  // SECTION 6: PROJECTS, TASKS & WORKFLOWS
  // ==========================================
  {
    name: "project_requests",
    description: "Tracks incoming design, consultation, and development client pitches waiting for review.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Unique request identifier" },
      { name: "client_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE", desc: "Submitting customer ID" },
      { name: "title", type: "varchar(255)", constraints: "NOT NULL", desc: "Desired engagement headline" },
      { name: "description", type: "text", constraints: "NOT NULL", desc: "Scope summary, wireframe links, or specification" },
      { name: "estimated_budget", type: "numeric(12,2)", constraints: "NULL", desc: "Client self-reported budget scale" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected'))", desc: "Review state" },
      { name: "created_at", type: "timestamp with time zone", constraints: "NOT NULL DEFAULT now()", desc: "Creation record log" }
    ],
    indexes: [
      "CREATE INDEX proj_req_client_idx ON public.project_requests(client_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Clients manage their own requests\" ON public.project_requests USING (auth.uid() = client_id);"
    ]
  },
  {
    name: "projects",
    description: "Enterprise project records tracking active timelines, budgets, and operational progress.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Core project ID" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Multi-tenant tenant company" },
      { name: "client_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Project owner client profile" },
      { name: "name", type: "varchar(255)", constraints: "NOT NULL", desc: "Official project name" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'scoping' CHECK (status IN ('scoping', 'active', 'paused', 'completed', 'terminated'))", desc: "Current execution status" },
      { name: "total_budget", type: "numeric(12,2)", constraints: "NOT NULL", desc: "Agreed target cost" },
      { name: "start_date", type: "date", constraints: "NULL", desc: "kickoff date" },
      { name: "end_date", type: "date", constraints: "NULL", desc: "Delivery deadline" }
    ],
    indexes: [
      "CREATE INDEX projects_client_idx ON public.projects(client_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Clients read projects assigned to them\" ON public.projects FOR SELECT USING (auth.uid() = client_id);"
    ]
  },
  {
    name: "project_milestones",
    description: "Granular delivery phases, checkpoints, and payment milestones linked to parent project.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Milestone ID" },
      { name: "project_id", type: "uuid", constraints: "NOT NULL REFERENCES public.projects(id) ON DELETE CASCADE", desc: "Parent project identifier" },
      { name: "title", type: "varchar(255)", constraints: "NOT NULL", desc: "Milestone name" },
      { name: "due_date", type: "date", constraints: "NULL", desc: "Timeline checkpoint" },
      { name: "progress_percentage", type: "integer", constraints: "NOT NULL DEFAULT 0", desc: "Aggregate calculation of completed tasks" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'in_progress', 'under_review', 'completed'))", desc: "State of execution" }
    ],
    indexes: [
      "CREATE INDEX PM_project_idx ON public.project_milestones(project_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Read milestones in projects\" ON public.project_milestones FOR SELECT USING (EXISTS (SELECT 1 FROM public.projects WHERE id = project_id AND client_id = auth.uid()));"
    ]
  },
  {
    name: "project_tasks",
    description: "Atomic workflow assignments mapped to developers and designers with deadlines.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Task record ID" },
      { name: "milestone_id", type: "uuid", constraints: "NOT NULL REFERENCES public.project_milestones(id) ON DELETE CASCADE", desc: "Parent delivery phase" },
      { name: "assignee_id", type: "uuid", constraints: "NULL REFERENCES auth.users(id) ON DELETE SET NULL", desc: "Staff developer UUID" },
      { name: "title", type: "varchar(255)", constraints: "NOT NULL", desc: "Action headline" },
      { name: "priority", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'critical'))", desc: "Deadline indicator" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'todo' CHECK (status IN ('todo', 'in_progress', 'review', 'done'))", desc: "Kanban coordinate" },
      { name: "due_date", type: "date", constraints: "NULL", desc: "Action constraint deadline" }
    ],
    indexes: [
      "CREATE INDEX task_milestone_idx ON public.project_tasks(milestone_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Users can view project tasks\" ON public.project_tasks FOR SELECT USING (true);"
    ]
  },
  {
    name: "project_subtasks",
    description: "Checklist components nested inside parent development tasks.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Subtask identifier" },
      { name: "task_id", type: "uuid", constraints: "NOT NULL REFERENCES public.project_tasks(id) ON DELETE CASCADE", desc: "Parent development task" },
      { name: "title", type: "varchar(255)", constraints: "NOT NULL", desc: "Checklist detail name" },
      { name: "is_completed", type: "boolean", constraints: "NOT NULL DEFAULT false", desc: "State flag" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Users can read checklist items\" ON public.project_subtasks FOR SELECT USING (true);"
    ]
  },
  {
    name: "project_time_logs",
    description: "Tracks developer working hours, consultative hourly logs, and timeline billing.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Record unique ID" },
      { name: "task_id", type: "uuid", constraints: "NOT NULL REFERENCES public.project_tasks(id) ON DELETE CASCADE", desc: "Working task scope" },
      { name: "user_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Assigned staff member" },
      { name: "hours", type: "numeric(5,2)", constraints: "NOT NULL", desc: "Logged hours count" },
      { name: "notes", type: "text", constraints: "NULL", desc: "Activities description" },
      { name: "logged_at", type: "date", constraints: "NOT NULL DEFAULT CURRENT_DATE", desc: "Work date" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Employees manage their time logs\" ON public.project_time_logs USING (user_id = auth.uid());"
    ]
  },
  {
    name: "project_updates",
    description: "Weekly milestone digests submitted by staff to clients.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Update record ID" },
      { name: "project_id", type: "uuid", constraints: "NOT NULL REFERENCES public.projects(id) ON DELETE CASCADE", desc: "Target project" },
      { name: "author_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Staff author" },
      { name: "update_text", type: "text", constraints: "NOT NULL", desc: "Weekly summaries" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Publication log" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Project owners read logs\" ON public.project_updates FOR SELECT USING (EXISTS (SELECT 1 FROM public.projects WHERE id = project_id AND client_id = auth.uid()));"
    ]
  },

  // ==========================================
  // SECTION 7: SUPPORT TICKETS & KB
  // ==========================================
  {
    name: "support_tickets",
    description: "Support ticket portal routing client technical requests directly to specialized IT staff.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Ticket index ID" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Multi-tenant context" },
      { name: "client_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Submitting customer ID" },
      { name: "assignee_id", type: "uuid", constraints: "NULL REFERENCES auth.users(id)", desc: "Assigned employee" },
      { name: "subject", type: "varchar(255)", constraints: "NOT NULL", desc: "Inquiry topic summary" },
      { name: "priority", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'critical'))", desc: "Urgency categorization" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'in_progress', 'resolved', 'closed'))", desc: "Fulfillment coordinate" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Filing log date" }
    ],
    indexes: [
      "CREATE INDEX ticket_status_idx ON public.support_tickets(status);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Clients view their own support tickets\" ON public.support_tickets FOR SELECT USING (client_id = auth.uid() OR EXISTS (SELECT 1 FROM public.employees WHERE user_id = auth.uid()));"
    ]
  },
  {
    name: "ticket_messages",
    description: "Structured discussion boards nested inside active customer support tickets.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Message unique ID" },
      { name: "ticket_id", type: "uuid", constraints: "NOT NULL REFERENCES public.support_tickets(id) ON DELETE CASCADE", desc: "Parent support ticket" },
      { name: "sender_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Author (client or staff)" },
      { name: "message", type: "text", constraints: "NOT NULL", desc: "Message markdown text" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Dispatch timestamp" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Read discussion messages on tickets\" ON public.ticket_messages FOR SELECT USING (EXISTS (SELECT 1 FROM public.support_tickets WHERE id = ticket_id AND (client_id = auth.uid() OR EXISTS (SELECT 1 FROM public.employees WHERE user_id = auth.uid()))));"
    ]
  },
  {
    name: "kb_categories",
    description: "Categories archiving self-help articles, WiFi tutorials, and API documentation.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Category ID" },
      { name: "name", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "Category title" },
      { name: "slug", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "SEO folder url route extension" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Public read support categories\" ON public.kb_categories FOR SELECT USING (true);"
    ]
  },
  {
    name: "kb_articles",
    description: "Troubleshooting articles and self-help manuals accessible to both visitors and portal clients.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Article record identifier" },
      { name: "category_id", type: "uuid", constraints: "NULL REFERENCES public.kb_categories(id) ON DELETE SET NULL", desc: "Filing category" },
      { name: "title", type: "varchar(255)", constraints: "NOT NULL", desc: "Article title" },
      { name: "slug", type: "varchar(255)", constraints: "NOT NULL UNIQUE", desc: "SEO path identifier" },
      { name: "content", type: "text", constraints: "NOT NULL", desc: "Markdown self-help body" },
      { name: "is_featured", type: "boolean", constraints: "NOT NULL DEFAULT false", desc: "Homepage feature indicator" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Creation date" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX kb_slug_idx ON public.kb_articles(slug);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"KB Articles are visible publicly\" ON public.kb_articles FOR SELECT USING (true);"
    ]
  },

  // ==========================================
  // SECTION 8: FINANCE & ACCOUNTS
  // ==========================================
  {
    name: "financial_accounts",
    description: "Double-entry charts of accounts tracking corporate revenue channels and balances.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Ledger unique ID" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Owner tenant company context" },
      { name: "name", type: "varchar(100)", constraints: "NOT NULL", desc: "Account moniker (e.g., M-PESA Operations, Bank Deposit)" },
      { name: "type", type: "varchar(50)", constraints: "NOT NULL CHECK (type IN ('asset', 'liability', 'equity', 'revenue', 'expense'))", desc: "Account type classification" },
      { name: "balance", type: "numeric(15,2)", constraints: "NOT NULL DEFAULT 0", desc: "Current reconciled balance" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Account establishment date" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Financial records visible to accountants and admins\" ON public.financial_accounts TO authenticated USING (true);"
    ]
  },
  {
    name: "financial_transactions",
    description: "Atomic balance ledger logging credits, debits, and transfers in double-entry bookkeeping.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Transaction ledger key" },
      { name: "account_id", type: "uuid", constraints: "NOT NULL REFERENCES public.financial_accounts(id) ON DELETE RESTRICT", desc: "Linked chart account" },
      { name: "amount", type: "numeric(12,2)", constraints: "NOT NULL", desc: "Transaction value" },
      { name: "type", type: "varchar(50)", constraints: "NOT NULL CHECK (type IN ('credit', 'debit'))", desc: "Bookkeeping entry classification" },
      { name: "description", type: "varchar(255)", constraints: "NOT NULL", desc: "Activity details (e.g. Cleared Milestone 1 Payment)" },
      { name: "recorded_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Creation log" }
    ],
    indexes: [
      "CREATE INDEX ledger_account_idx ON public.financial_transactions(account_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Transactions restricted to authorized roles\" ON public.financial_transactions TO authenticated USING (true);"
    ]
  },
  {
    name: "expenses",
    description: "Database tracking corporate expenses, material hardware procurement, and third-party bills.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Expense record ID" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Multi-tenant context" },
      { name: "payee", type: "varchar(255)", constraints: "NOT NULL", desc: "Hardware vendor, supplier, or employee recipient" },
      { name: "amount", type: "numeric(12,2)", constraints: "NOT NULL", desc: "Capital expended" },
      { name: "category", type: "varchar(100)", constraints: "NOT NULL CHECK (category IN ('procurement', 'subcontracting', 'infrastructure', 'marketing', 'travel', 'salaries'))", desc: "Financial grouping classification" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'paid', 'rejected'))", desc: "Reimbursement status" },
      { name: "incurred_at", type: "date", constraints: "NOT NULL", desc: "Bill clearance timestamp constraint" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Manage expenses inside org\" ON public.expenses USING (EXISTS (SELECT 1 FROM public.organization_members WHERE organization_id = public.expenses.organization_id AND user_id = auth.uid()));"
    ]
  },

  // ==========================================
  // SECTION 9: BILLING, INVOICES & MPESA
  // ==========================================
  {
    name: "invoices",
    description: "Structured double-entry client billings for project milestones or consultations.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Unique invoice ID" },
      { name: "project_id", type: "uuid", constraints: "NULL REFERENCES public.projects(id) ON DELETE SET NULL", desc: "Parent project scope" },
      { name: "client_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Recipient client" },
      { name: "invoice_number", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "Formatted billing ID (e.g. INV-2026-0001)" },
      { name: "total_amount", type: "numeric(12,2)", constraints: "NOT NULL", desc: "Sum amount of line items" },
      { name: "due_date", type: "date", constraints: "NOT NULL", desc: "Payment target deadline" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'unpaid' CHECK (status IN ('unpaid', 'partially_paid', 'paid', 'voided'))", desc: "State of collection" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX inv_number_idx ON public.invoices(invoice_number);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Clients read own invoices\" ON public.invoices FOR SELECT USING (auth.uid() = client_id);"
    ]
  },
  {
    name: "invoice_items",
    description: "Detailed description of scope and itemized bills inside a single invoice.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Line item ID" },
      { name: "invoice_id", type: "uuid", constraints: "NOT NULL REFERENCES public.invoices(id) ON DELETE CASCADE", desc: "Parent invoice" },
      { name: "description", type: "varchar(255)", constraints: "NOT NULL", desc: "Service description" },
      { name: "quantity", type: "numeric(10,2)", constraints: "NOT NULL DEFAULT 1", desc: "Units" },
      { name: "unit_price", type: "numeric(12,2)", constraints: "NOT NULL", desc: "Single unit cost" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"View line items on invoices\" ON public.invoice_items FOR SELECT USING (EXISTS (SELECT 1 FROM public.invoices WHERE id = invoice_id AND client_id = auth.uid()));"
    ]
  },
  {
    name: "payment_gateways",
    description: "Multi-tenant payment provider configurations managed inside the Super Admin Dashboard (supports Safaricom Daraja, PayHero, Pesapal, Paystack, Stripe, PayPal, etc.).",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Unique gateway configuration ID" },
      { name: "organization_id", type: "uuid", constraints: "NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Linked organization (NULL indicates global/system gateway)" },
      { name: "provider", type: "varchar(100)", constraints: "NOT NULL", desc: "Provider key (e.g., 'safaricom_daraja', 'payhero', 'pesapal', 'paystack', 'stripe', 'paypal')" },
      { name: "name", type: "varchar(255)", constraints: "NOT NULL", desc: "Display label (e.g., 'Safaricom Daraja M-PESA')" },
      { name: "environment", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'sandbox' CHECK (environment IN ('sandbox', 'production'))", desc: "Active gateway operation mode" },
      { name: "credentials", type: "jsonb", constraints: "NOT NULL", desc: "Encrypted API credentials (keys, secrets, shortcodes, cert hashes)" },
      { name: "webhook_secret", type: "text", constraints: "NULL", desc: "Secret token for webhook signature verification" },
      { name: "settings", type: "jsonb", constraints: "NOT NULL DEFAULT '{}'::jsonb", desc: "Retry loops, currency filters, country restrictions, priorities, and timeouts" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'healthy' CHECK (status IN ('healthy', 'degraded', 'offline'))", desc: "Gateway connection status" },
      { name: "is_active", type: "boolean", constraints: "NOT NULL DEFAULT true", desc: "Active toggle" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Gateway installation log date" }
    ],
    indexes: [
      "CREATE INDEX gateway_org_idx ON public.payment_gateways(organization_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Admins can manage gateway settings\" ON public.payment_gateways USING (EXISTS (SELECT 1 FROM public.organization_members WHERE organization_id = public.payment_gateways.organization_id AND user_id = auth.uid() AND role IN ('owner', 'admin')));"
    ]
  },
  {
    name: "payment_intents",
    description: "Provider-agnostic active payment sessions created when an invoice or store purchase is requested.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Unified payment intent ID" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Owning tenant" },
      { name: "invoice_id", type: "uuid", constraints: "NULL REFERENCES public.invoices(id) ON DELETE SET NULL", desc: "Associated invoice (if applicable)" },
      { name: "gateway_id", type: "uuid", constraints: "NOT NULL REFERENCES public.payment_gateways(id)", desc: "Selected gateway provider config" },
      { name: "amount", type: "numeric(12,2)", constraints: "NOT NULL", desc: "Transaction amount" },
      { name: "currency", type: "varchar(10)", constraints: "NOT NULL DEFAULT 'KES'", desc: "Transaction ISO currency (e.g. KES, USD, NGN)" },
      { name: "payment_method", type: "varchar(50)", constraints: "NOT NULL", desc: "Selected method (e.g., 'mpesa_stk', 'mpesa_paybill', 'card_visa', 'card_mastercard', 'paypal', 'bank_transfer')" },
      { name: "provider_transaction_id", type: "varchar(255)", constraints: "NULL", desc: "External provider ID (e.g. Stripe PaymentIntent ID, Daraja CheckoutID)" },
      { name: "reference_number", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "Idempotent tracking and ledger reference code" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'))", desc: "Agnostic transaction state" },
      { name: "callback_payload", type: "jsonb", constraints: "NULL", desc: "Raw validated webhook callback JSON payload returned by provider" },
      { name: "created_at", type: "timestamp with time zone", constraints: "NOT NULL DEFAULT now()", desc: "Transaction initiation time" },
      { name: "completed_at", type: "timestamp with time zone", constraints: "NULL", desc: "Settlement or termination time" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX pay_intent_ref_idx ON public.payment_intents(reference_number);",
      "CREATE INDEX pay_intent_provider_idx ON public.payment_intents(provider_transaction_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Clients view their own payment intents\" ON public.payment_intents FOR SELECT USING (EXISTS (SELECT 1 FROM public.invoices WHERE id = invoice_id AND client_id = auth.uid()));"
    ]
  },
  {
    name: "payment_receipts",
    description: "Normalized transaction settlements stored after external gateway callbacks are cryptographically verified and posted to double-entry ledger charts.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Central settlement entry ID" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Associated tenant context" },
      { name: "intent_id", type: "uuid", constraints: "NOT NULL REFERENCES public.payment_intents(id) ON DELETE RESTRICT", desc: "Verified payment intent" },
      { name: "receipt_number", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "System-wide unique receipt reference (e.g. Safaricom Receipt, Stripe Charge ID)" },
      { name: "amount", type: "numeric(12,2)", constraints: "NOT NULL", desc: "Gross cleared payment amount" },
      { name: "fees", type: "numeric(12,2)", constraints: "NOT NULL DEFAULT 0.00", desc: "Payment processor transactional fees" },
      { name: "net_amount", type: "numeric(12,2)", constraints: "NOT NULL", desc: "Net revenue posted to business charts (gross minus fees)" },
      { name: "payer_identity", type: "varchar(255)", constraints: "NOT NULL", desc: "Payer identity index (e.g. MSISDN phone number or Stripe email address)" },
      { name: "ledger_posted", type: "boolean", constraints: "NOT NULL DEFAULT false", desc: "True if double-entry ledger triggers have executed" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Receipt creation date" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX payment_receipt_num_idx ON public.payment_receipts(receipt_number);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Clients view receipt records\" ON public.payment_receipts FOR SELECT USING (EXISTS (SELECT 1 FROM public.payment_intents JOIN public.invoices ON invoices.id = payment_intents.invoice_id WHERE payment_intents.id = intent_id AND invoices.client_id = auth.uid()));"
    ]
  },

  // ==========================================
  // SECTION 10: AUTOMATION & WORKFLOWS
  // ==========================================
  {
    name: "workflows",
    description: "Visual logic blueprints defining trigger event listeners, step parameters, and scheduled rules.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Workflow identifier" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Owning company scope" },
      { name: "name", type: "varchar(255)", constraints: "NOT NULL", desc: "Workflow label (e.g. Invoice Autoclear Trigger)" },
      { name: "is_active", type: "boolean", constraints: "NOT NULL DEFAULT true", desc: "Status toggle" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Creation timestamp" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Manage workflows inside org\" ON public.workflows USING (EXISTS (SELECT 1 FROM public.organization_members WHERE organization_id = public.workflows.organization_id AND user_id = auth.uid()));"
    ]
  },
  {
    name: "workflow_triggers",
    description: "Event models listening for state modifications inside the database.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Trigger record token" },
      { name: "workflow_id", type: "uuid", constraints: "NOT NULL REFERENCES public.workflows(id) ON DELETE CASCADE", desc: "Parent workflow" },
      { name: "event_type", type: "varchar(100)", constraints: "NOT NULL", desc: "Target event hooks (e.g., invoice.paid, ticket.created, lead.won)" },
      { name: "conditions", type: "jsonb", constraints: "NOT NULL DEFAULT '{}'::jsonb", desc: "JSON parameters filtering triggers" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Access triggers on active workflows\" ON public.workflow_triggers FOR SELECT USING (true);"
    ]
  },
  {
    name: "workflow_actions",
    description: "Step-by-step commands executed asynchronously when workflow events trigger.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Action execution step ID" },
      { name: "workflow_id", type: "uuid", constraints: "NOT NULL REFERENCES public.workflows(id) ON DELETE CASCADE", desc: "Parent visual logic block" },
      { name: "action_type", type: "varchar(100)", constraints: "NOT NULL", desc: "Execution command code (e.g. smtp.send_email, gemini.summarize)" },
      { name: "payload_template", type: "jsonb", constraints: "NOT NULL DEFAULT '{}'::jsonb", desc: "Dynamic payload templates" },
      { name: "step_order", type: "integer", constraints: "NOT NULL DEFAULT 1", desc: "Execution order coordinate" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Access actions on workflows\" ON public.workflow_actions FOR SELECT USING (true);"
    ]
  },
  {
    name: "workflow_executions",
    description: "Immutable history tracking async workflow automation jobs, runs, statuses, and failed retries.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Run unique execution ID" },
      { name: "workflow_id", type: "uuid", constraints: "NOT NULL REFERENCES public.workflows(id) ON DELETE CASCADE", desc: "Parent automation layout" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL CHECK (status IN ('running', 'success', 'failed', 'retrying'))", desc: "Execution stage status" },
      { name: "logs", type: "text", constraints: "NULL", desc: "Step validation errors or output stacks" },
      { name: "executed_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Record log date" }
    ],
    indexes: [
      "CREATE INDEX exec_workflow_idx ON public.workflow_executions(workflow_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"View audit execution logs\" ON public.workflow_executions TO authenticated USING (true);"
    ]
  },

  // ==========================================
  // SECTION 11: AI PLATFORM LAYER
  // ==========================================
  {
    name: "ai_conversations",
    description: "Saves individual client or staff interactive chat sessions with the custom assistant.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Chat session ID" },
      { name: "user_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE", desc: "Author profile" },
      { name: "context_summary", type: "text", constraints: "NULL", desc: "AI-generated chat summary for layout headers" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "First prompt date" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Users read own chat histories\" ON public.ai_conversations FOR SELECT USING (user_id = auth.uid());"
    ]
  },
  {
    name: "ai_messages",
    description: "Discussion transcript indexing queries and model responses.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Interaction record identifier" },
      { name: "conversation_id", type: "uuid", constraints: "NOT NULL REFERENCES public.ai_conversations(id) ON DELETE CASCADE", desc: "Parent discussion workspace" },
      { name: "role", type: "varchar(50)", constraints: "NOT NULL CHECK (role IN ('user', 'model', 'system'))", desc: "Dialogue author indicator" },
      { name: "content", type: "text", constraints: "NOT NULL", desc: "Rich text dialog or query parameters" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Message timestamp" }
    ],
    indexes: [
      "CREATE INDEX msg_conv_idx ON public.ai_messages(conversation_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Users access messages on their own conversations\" ON public.ai_messages FOR SELECT USING (EXISTS (SELECT 1 FROM public.ai_conversations WHERE id = conversation_id AND user_id = auth.uid()));"
    ]
  },
  {
    name: "ai_prompts",
    description: "Centralized prompt library managing specialized system scopes (Blog Writer, Emailer, etc.).",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Prompt template ID" },
      { name: "title", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "Specialization (e.g. Sales Proposal, DB Architect)" },
      { name: "system_instruction", type: "text", constraints: "NOT NULL", desc: "Model configurations" },
      { name: "temperature", type: "numeric(3,2)", constraints: "NOT NULL DEFAULT 0.7", desc: "Model creativity slider" },
      { name: "updated_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Timestamp" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"View prompt templates catalog\" ON public.ai_prompts FOR SELECT TO authenticated USING (true);"
    ]
  },

  // ==========================================
  // SECTION 12: CONTRACTS, DOCUMENTS & SIGNS
  // ==========================================
  {
    name: "contracts",
    description: "Formal legally-binding agreements, non-disclosure contracts, and project estimates.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Contract index ID" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Multi-tenant company context" },
      { name: "client_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Corporate client profile" },
      { name: "title", type: "varchar(255)", constraints: "NOT NULL", desc: "Legal agreement title" },
      { name: "document_url", type: "text", constraints: "NOT NULL", desc: "Fully qualified PDF storage link" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'signed', 'expired'))", desc: "Fulfillment coordinate" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Compiling record timestamp" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"View contracts for signed company members\" ON public.contracts FOR SELECT USING (client_id = auth.uid() OR EXISTS (SELECT 1 FROM public.employees WHERE user_id = auth.uid()));"
    ]
  },
  {
    name: "signatures",
    description: "Immutable digital logs capturing cryptographic signatures and legal validation.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Signature record token" },
      { name: "contract_id", type: "uuid", constraints: "NOT NULL REFERENCES public.contracts(id) ON DELETE CASCADE", desc: "Linked legal agreement" },
      { name: "signer_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Signing client user profile" },
      { name: "ip_address", type: "varchar(45)", constraints: "NOT NULL", desc: "Signing network address origin" },
      { name: "signature_hash", type: "varchar(255)", constraints: "NOT NULL UNIQUE", desc: "Cryptographic identifier seal representing the signature" },
      { name: "signed_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Legal signing timestamp" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX sig_hash_idx ON public.signatures(signature_hash);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"View signatures on shared contracts\" ON public.signatures FOR SELECT USING (EXISTS (SELECT 1 FROM public.contracts WHERE id = contract_id AND (client_id = auth.uid() OR EXISTS (SELECT 1 FROM public.employees WHERE user_id = auth.uid()))));"
    ]
  },

  // ==========================================
  // SECTION 13: DIGITAL MARKETPLACE
  // ==========================================
  {
    name: "product_categories",
    description: "Grouping catalog products, design templates, and software code licenses.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Category ID" },
      { name: "name", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "Category name" },
      { name: "slug", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "Slug route" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Public read marketplace categories\" ON public.product_categories FOR SELECT USING (true);"
    ]
  },
  {
    name: "products",
    description: "Catalog of pre-built code bases, custom dashboard frames, SaaS presets, and UI kits.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Product ID" },
      { name: "category_id", type: "uuid", constraints: "NULL REFERENCES public.product_categories(id) ON DELETE SET NULL", desc: "Taxonomy directory" },
      { name: "name", type: "varchar(255)", constraints: "NOT NULL", desc: "Software title" },
      { name: "price", type: "numeric(10,2)", constraints: "NOT NULL", desc: "Pricing scale in KES/USD" },
      { name: "file_url", type: "text", constraints: "NOT NULL", desc: "Supabase Secure bucket path for file bundle distribution" },
      { name: "is_active", type: "boolean", constraints: "NOT NULL DEFAULT true", desc: "Active toggle" }
    ],
    indexes: [
      "CREATE INDEX products_active_idx ON public.products(is_active) WHERE is_active = true;"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Public reads active products catalog\" ON public.products FOR SELECT USING (is_active = true);"
    ]
  },
  {
    name: "product_orders",
    description: "Fulfillment logs tracing single asset transactions for digital purchases.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Transaction ID" },
      { name: "client_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Purchasing profile" },
      { name: "product_id", type: "uuid", constraints: "NOT NULL REFERENCES public.products(id)", desc: "Target software package" },
      { name: "amount_paid", type: "numeric(10,2)", constraints: "NOT NULL", desc: "Amount paid" },
      { name: "payment_status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'pending' CHECK (payment_status IN ('pending', 'completed', 'failed'))", desc: "Checkout stage" }
    ],
    indexes: [
      "CREATE INDEX prod_orders_client_idx ON public.product_orders(client_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Purchasers see historical orders\" ON public.product_orders FOR SELECT USING (auth.uid() = client_id);"
    ]
  },
  {
    name: "purchase_codes",
    description: "Unique cryptographic keys issued to verify valid licenses upon checkout.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Crypto record ID" },
      { name: "order_id", type: "uuid", constraints: "NOT NULL REFERENCES public.product_orders(id) ON DELETE CASCADE", desc: "Receipt" },
      { name: "code_hash", type: "varchar(255)", constraints: "NOT NULL UNIQUE", desc: "License string" },
      { name: "is_used", type: "boolean", constraints: "NOT NULL DEFAULT false", desc: "Activation indicator" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX code_hash_idx ON public.purchase_codes(code_hash);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Purchasers see valid codes\" ON public.purchase_codes FOR SELECT USING (EXISTS (SELECT 1 FROM public.product_orders WHERE id = order_id AND client_id = auth.uid()));"
    ]
  },
  {
    name: "downloads",
    description: "Usage logs tracking download requests, securing digital assets against license abuses.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Log ID" },
      { name: "purchase_code_id", type: "uuid", constraints: "NOT NULL REFERENCES public.purchase_codes(id) ON DELETE CASCADE", desc: "Coupon" },
      { name: "ip_address", type: "varchar(45)", constraints: "NULL", desc: "Fraud origin tracer" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Track client downloads only\" ON public.downloads FOR SELECT USING (EXISTS (SELECT 1 FROM public.purchase_codes JOIN public.product_orders ON product_orders.id = purchase_codes.order_id WHERE purchase_codes.id = purchase_code_id AND product_orders.client_id = auth.uid()));"
    ]
  },

  // ==========================================
  // SECTION 14: WEBSITE CMS & PORTFOLIO
  // ==========================================
  {
    name: "cms_pages",
    description: "Database powering editable static marketing layouts, FAQs, and SEO landing zones.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Page database ID" },
      { name: "title", type: "varchar(255)", constraints: "NOT NULL", desc: "Menu display title" },
      { name: "slug", type: "varchar(255)", constraints: "NOT NULL UNIQUE", desc: "SEO folder route (e.g. /wifi-solutions)" },
      { name: "layout_schema", type: "jsonb", constraints: "NOT NULL DEFAULT '{}'::jsonb", desc: "Editable JSON sections and hero texts" },
      { name: "meta_description", type: "varchar(255)", constraints: "NULL", desc: "SEO description" },
      { name: "updated_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Timestamp" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX page_slug_idx ON public.cms_pages(slug);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Public reads marketing layouts\" ON public.cms_pages FOR SELECT USING (true);"
    ]
  },
  {
    name: "portfolio_items",
    description: "Case studies showcasing completed fiber deployment projects and software platforms.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Case study ID" },
      { name: "title", type: "varchar(255)", constraints: "NOT NULL", desc: "Showcase title" },
      { name: "client_name", type: "varchar(255)", constraints: "NOT NULL", desc: "Company credit line" },
      { name: "technologies", type: "varchar(100)[]", constraints: "NOT NULL", desc: "Tech stacks array used (e.g., [React, Fiber, Mikrotik])" },
      { name: "case_study", type: "text", constraints: "NOT NULL", desc: "Markdown body detailing solutions" },
      { name: "is_featured", type: "boolean", constraints: "NOT NULL DEFAULT false", desc: "Agency homepage highlight toggle" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Public reads agency showcase\" ON public.portfolio_items FOR SELECT USING (true);"
    ]
  },
  {
    name: "testimonials",
    description: "Formal feedback quotes and client references published to establish brand authority.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Review ID" },
      { name: "client_name", type: "varchar(255)", constraints: "NOT NULL", desc: "Reviewer full name" },
      { name: "designation", type: "varchar(100)", constraints: "NOT NULL", desc: "Reviewer role (e.g., CTO, HR Manager)" },
      { name: "company", type: "varchar(255)", constraints: "NOT NULL", desc: "Corporate credential" },
      { name: "quote", type: "text", constraints: "NOT NULL", desc: "Review text" },
      { name: "rating", type: "integer", constraints: "NOT NULL DEFAULT 5 CHECK (rating BETWEEN 1 AND 5)", desc: "Verification scale" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Public reads testimonials\" ON public.testimonials FOR SELECT USING (true);"
    ]
  },

  // ==========================================
  // SECTION 15: BLOG CMS (Marketing Blog)
  // ==========================================
  {
    name: "blog_categories",
    description: "Filing groups mapping articles into technical specializations.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Category ID" },
      { name: "name", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "Directory handle" },
      { name: "slug", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "Slug route" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Public read category directories\" ON public.blog_categories FOR SELECT USING (true);"
    ]
  },
  {
    name: "blog_posts",
    description: "SEO marketing articles, announcements, and developer guides written by Juan.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Unique article ID" },
      { name: "category_id", type: "uuid", constraints: "NULL REFERENCES public.blog_categories(id) ON DELETE SET NULL", desc: "Parent category folder" },
      { name: "author_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Author profile" },
      { name: "title", type: "varchar(255)", constraints: "NOT NULL", desc: "Display title" },
      { name: "slug", type: "varchar(255)", constraints: "NOT NULL UNIQUE", desc: "SEO Slug" },
      { name: "content", type: "text", constraints: "NOT NULL", desc: "Rich markdown document body" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published'))", desc: "Publication state" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX blog_post_slug_idx ON public.blog_posts(slug);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Public reads published posts\" ON public.blog_posts FOR SELECT USING (status = 'published');"
    ]
  },

  // ==========================================
  // SECTION 16: BACKGROUND SERVICES & FEED
  // ==========================================
  {
    name: "job_queue",
    description: "Robust asynchronous background job dispatch ledger executing PDF, email, and scan tasks.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Job ledger ID" },
      { name: "queue_name", type: "varchar(100)", constraints: "NOT NULL DEFAULT 'default'", desc: "Task queue division (e.g. storage, mailer, billing)" },
      { name: "payload", type: "jsonb", constraints: "NOT NULL DEFAULT '{}'::jsonb", desc: "Dynamic target arguments parsed" },
      { name: "status", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'queued' CHECK (status IN ('queued', 'processing', 'completed', 'failed'))", desc: "Asynchronous pipeline coordinate" },
      { name: "attempts", type: "integer", constraints: "NOT NULL DEFAULT 0", desc: "Count of execution runs" },
      { name: "run_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Asynchronous scheduled lock timestamp" }
    ],
    indexes: [
      "CREATE INDEX job_status_run_idx ON public.job_queue(status, run_at);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Platform service role access only for jobs\" ON public.job_queue TO authenticated USING (true);"
    ]
  },
  {
    name: "timeline_events",
    description: "Aggregates key agency milestone triggers in real-time (e.g., invoice.cleared, proposal.accepted).",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Feed event unique ID" },
      { name: "organization_id", type: "uuid", constraints: "NOT NULL REFERENCES public.organizations(id) ON DELETE CASCADE", desc: "Multi-tenant company context" },
      { name: "user_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE", desc: "Profile initiator UUID" },
      { name: "event_code", type: "varchar(100)", constraints: "NOT NULL", desc: "Dynamic event taxonomy code" },
      { name: "description", type: "text", constraints: "NOT NULL", desc: "Natural language description (e.g., Alice accepted proposal JN-WIFI)" },
      { name: "metadata", type: "jsonb", constraints: "NOT NULL DEFAULT '{}'::jsonb", desc: "Related records hashes (e.g. invoice_id, ticket_id)" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Trigger date" }
    ],
    indexes: [
      "CREATE INDEX timeline_org_idx ON public.timeline_events(organization_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Shared tenant colleagues read event timeline\" ON public.timeline_events FOR SELECT USING (EXISTS (SELECT 1 FROM public.organization_members WHERE organization_id = public.timeline_events.organization_id AND user_id = auth.uid()));"
    ]
  },
  {
    name: "audit_logs",
    description: "Immutable enterprise record of all administrative user actions, source IPs, and previous/new values.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Log identifier" },
      { name: "user_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id)", desc: "Actor" },
      { name: "ip_address", type: "varchar(45)", constraints: "NOT NULL", desc: "Origin network address" },
      { name: "browser_agent", type: "text", constraints: "NOT NULL", desc: "Operating device details" },
      { name: "module", type: "varchar(100)", constraints: "NOT NULL", desc: "Target application section (e.g. finance, rbac)" },
      { name: "action", type: "varchar(100)", constraints: "NOT NULL", desc: "Activity (e.g., ASSIGN_ROLE, REFUND_INVOICE)" },
      { name: "previous_state", type: "jsonb", constraints: "NULL", desc: "Audit rollback state before change" },
      { name: "new_state", type: "jsonb", constraints: "NULL", desc: "Audit tracking state after change" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Timestamp" }
    ],
    indexes: [
      "CREATE INDEX audit_module_idx ON public.audit_logs(module);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Audit log access restricted to security leads\" ON public.audit_logs TO authenticated USING (true);"
    ]
  },

  // ==========================================
  // SECTION 17: NOTIFICATION CENTER
  // ==========================================
  {
    name: "notifications",
    description: "Real-time user feedback portal pushing transaction and operational status updates.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Notification identifier" },
      { name: "user_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE", desc: "Recipient" },
      { name: "title", type: "varchar(255)", constraints: "NOT NULL", desc: "Alert header text" },
      { name: "content", type: "text", constraints: "NOT NULL", desc: "Alert detailed descriptive block" },
      { name: "is_read", type: "boolean", constraints: "NOT NULL DEFAULT false", desc: "Read status flag" },
      { name: "created_at", type: "timestamp with time zone", constraints: "DEFAULT now()", desc: "Timestamp" }
    ],
    indexes: [
      "CREATE INDEX notification_recipient_idx ON public.notifications(user_id) WHERE is_read = false;"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Users read own notifications\" ON public.notifications FOR SELECT USING (auth.uid() = user_id);"
    ]
  },

  // ==========================================
  // SECTION 18: STAFF & RBAC (Security)
  // ==========================================
  {
    name: "employees",
    description: "Extends user authentication specifically for agency personnel, consultants, and contractors.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Employee profile ID" },
      { name: "user_id", type: "uuid", constraints: "NOT NULL UNIQUE REFERENCES auth.users(id) ON DELETE CASCADE", desc: "Supabase authentication UUID" },
      { name: "specialization", type: "varchar(100)", constraints: "NOT NULL", desc: "Primary skill" },
      { name: "salary", type: "numeric(12,2)", constraints: "NOT NULL", desc: "Base compensation" },
      { name: "contract_type", type: "varchar(50)", constraints: "NOT NULL DEFAULT 'full-time' CHECK (contract_type IN ('full-time', 'part-time', 'contractor'))", desc: "Contract structure" },
      { name: "date_hired", type: "date", constraints: "NOT NULL DEFAULT CURRENT_DATE", desc: "Hire date" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX employee_user_idx ON public.employees(user_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Staff profile readings\" ON public.employees FOR SELECT USING (true);"
    ]
  },
  {
    name: "roles",
    description: "Fulfillment roles configuring core capabilities for staff members.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Unique role identifier" },
      { name: "name", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "System authorization handle (e.g. super_admin, developer)" },
      { name: "description", type: "text", constraints: "NULL", desc: "Brief operational boundary of role" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Read roles config\" ON public.roles FOR SELECT TO authenticated USING (true);"
    ]
  },
  {
    name: "permissions",
    description: "System capability strings determining backend router entry clearances.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Unique identifier" },
      { name: "code", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "Explicit code boundary" },
      { name: "description", type: "text", constraints: "NULL", desc: "Detailed capabilities" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Read permissions layout\" ON public.permissions FOR SELECT TO authenticated USING (true);"
    ]
  },
  {
    name: "role_permissions",
    description: "Association bridge mapping core capabilities to specialized organizational roles.",
    columns: [
      { name: "role_id", type: "uuid", constraints: "NOT NULL REFERENCES public.roles(id) ON DELETE CASCADE", desc: "Target system role" },
      { name: "permission_id", type: "uuid", constraints: "NOT NULL REFERENCES public.permissions(id) ON DELETE CASCADE", desc: "Target capability string" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX role_perm_composite ON public.role_permissions(role_id, permission_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Read role mappings\" ON public.role_permissions FOR SELECT TO authenticated USING (true);"
    ]
  },
  {
    name: "user_roles",
    description: "Assigns profiles directly to custom security groups for RBAC API validation.",
    columns: [
      { name: "user_id", type: "uuid", constraints: "NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE", desc: "Profile target link" },
      { name: "role_id", type: "uuid", constraints: "NOT NULL REFERENCES public.roles(id) ON DELETE CASCADE", desc: "Group membership identifier" }
    ],
    indexes: [
      "CREATE UNIQUE INDEX user_roles_composite ON public.user_roles(user_id, role_id);"
    ],
    rlsPolicies: [
      "CREATE POLICY \"Read security clearances\" ON public.user_roles FOR SELECT TO authenticated USING (true);"
    ]
  },
  {
    name: "settings",
    description: "Application environment presets and company constants.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Parameter token" },
      { name: "key", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "Target key constant" },
      { name: "value", type: "text", constraints: "NOT NULL", desc: "Config string value" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Read global settings constants\" ON public.settings FOR SELECT USING (true);"
    ]
  },
  {
    name: "integrations",
    description: "M-PESA Daraja keys, consumer secrets, and external webhooks (Encrypted at Rest).",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Integration token ID" },
      { name: "name", type: "varchar(100)", constraints: "NOT NULL UNIQUE", desc: "Service (M-PESA, Google, AWS)" },
      { name: "consumer_key", type: "text", constraints: "NULL", desc: "Daraja client key" },
      { name: "consumer_secret", type: "text", constraints: "NULL", desc: "Daraja credentials secret" },
      { name: "shortcode", type: "varchar(50)", constraints: "NULL", desc: "M-PESA Paybill / Till identifier" },
      { name: "is_sandbox", type: "boolean", constraints: "NOT NULL DEFAULT true", desc: "API endpoint switcher" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Superadmin access only for integrations\" ON public.integrations TO authenticated USING (true);"
    ]
  },
  {
    name: "smtp_settings",
    description: "SMTP mail server configurations for dispatching automated invoices and notices.",
    columns: [
      { name: "id", type: "uuid", constraints: "PRIMARY KEY DEFAULT gen_random_uuid()", desc: "Mailer ID" },
      { name: "host", type: "varchar(255)", constraints: "NOT NULL", desc: "SMTP server hostname" },
      { name: "port", type: "integer", constraints: "NOT NULL DEFAULT 587", desc: "Port configuration" },
      { name: "username", type: "varchar(255)", constraints: "NOT NULL", desc: "Login credentials" },
      { name: "password", type: "text", constraints: "NOT NULL", desc: "Encrypted password key" },
      { name: "default_from_email", type: "varchar(255)", constraints: "NOT NULL", desc: "Sender default address" }
    ],
    indexes: [],
    rlsPolicies: [
      "CREATE POLICY \"Mailer settings admin reading only\" ON public.smtp_settings TO authenticated USING (true);"
    ]
  }
];

export const API_ENDPOINTS: ApiEndpoint[] = [
  // SECTION 1: SAAS CORE & MULTI-TENANCY API
  {
    method: "POST",
    path: "/api/organizations",
    description: "Registers a new organization tenant, creating default configurations and tying the creator as Owner.",
    roles: ["Super Admin", "SaaS Subscriber"],
    requestBody: "{\n  \"name\": \"Hustle Hub Kenya Ltd\",\n  \"slug\": \"hustle-hub\"\n}",
    responseBody: "{\n  \"status\": \"success\",\n  \"orgId\": \"b25ef332-901c-439d-b8c3-e822ea915ff3\",\n  \"message\": \"Tenant space instantiated successfully.\"\n}"
  },
  {
    method: "GET",
    path: "/api/profiles/me",
    description: "Retrieves the currently authenticated user profile, timezone preferences, and active organization credentials.",
    roles: ["Client", "Staff"],
    responseBody: "{\n  \"id\": \"7a1bf22b-2a2b-45c1-8419-f029ea91100b\",\n  \"full_name\": \"Joseph Omwamba\",\n  \"organizations\": [\n    { \"id\": \"b25ef332...\", \"role\": \"owner\", \"name\": \"Hustle Hub\" }\n  ],\n  \"preferences\": { \"theme\": \"dark\", \"email_alerts\": true }\n}"
  },

  // SECTION 2: GLOBAL FILES SYSTEM API
  {
    method: "POST",
    path: "/api/storage/upload",
    description: "Uploads a raw binary asset chunk inside the multi-tenant directory, automatically triggering background antivirus scans.",
    roles: ["Client", "Staff"],
    requestBody: "FormData: { \"file\": Binary, \"folder_id\": \"fd_456...\" }",
    responseBody: "{\n  \"status\": \"success\",\n  \"fileId\": \"f98ea432-1b1c-4cf2-bd82-99caef880193\",\n  \"scanStatus\": \"pending\",\n  \"url\": \"https://supabase.co/storage/v1/object/public/central/hustle-hub/fd_456/blueprint.pdf\"\n}"
  },

  // SECTION 3: CRM & PROPOSAL AUTOMATIONS
  {
    method: "POST",
    path: "/api/crm/leads",
    description: "Receives marketing landing forms, instantiating a sales lead in the database and triggering lead assignment workflows.",
    roles: ["Visitor (Public)", "Lead Architect"],
    requestBody: "{\n  \"contact_name\": \"Alice Wanjiru\",\n  \"company_name\": \"Apex Digital\",\n  \"email\": \"alice@apex.co.ke\",\n  \"budget\": 12000.00\n}",
    responseBody: "{\n  \"status\": \"success\",\n  \"leadId\": \"123ea34e-781d-48ef-be23-99ca828954de\"\n}"
  },
  {
    method: "POST",
    path: "/api/proposals",
    description: "Generates custom client quotation estimates dynamically, parsing template blocks.",
    roles: ["Lead Architect", "Sales Manager"],
    requestBody: "{\n  \"leadId\": \"123ea34e...\",\n  \"clientId\": \"client-uuid-here\",\n  \"title\": \"Enterprise Fiber & Wi-Fi Backhaul Solution\",\n  \"items\": [\n    { \"description\": \"Outdoor Mikrotik Access Points\", \"quantity\": 4, \"unit_price\": 12000 }\n  ]\n}",
    responseBody: "{\n  \"status\": \"success\",\n  \"proposalId\": \"p980ea23-112c-4cf1-bd93-cde29ea155fc\",\n  \"totalAmount\": 48000.00\n}"
  },

  // SECTION 4: CLIENT WORKSPACE & PROJECT DELIVERY API
  {
    method: "GET",
    path: "/api/projects/:id/tasks",
    description: "Fetches structured delivery tasks, backlog statuses, and assignees. Mobile-ready JSON API format.",
    roles: ["Client", "Developer", "Lead Architect"],
    responseBody: "[\n  {\n    \"id\": \"t879a12c-12bc-4fa3-bd92-998822998aab\",\n    \"title\": \"Cabling Backbone Conduit Installation\",\n    \"priority\": \"high\",\n    \"status\": \"in_progress\",\n    \"assignee_name\": \"Developer Alpha\"\n  }\n]"
  },
  {
    method: "POST",
    path: "/api/contracts/:id/sign",
    description: "Captures formal user agreement, recording signing IP coordinates and generating a unique cryptographic signature.",
    roles: ["Client"],
    requestBody: "{\n  \"signer_name\": \"Alice Wanjiru\"\n}",
    responseBody: "{\n  \"status\": \"success\",\n  \"signatureHash\": \"sig_sha256_b2c58eef71a238cd6a93b4991bc8eef1284fa933\",\n  \"signedAt\": \"2026-06-25T15:58:00Z\"\n}"
  },

  // SECTION 5: AI PLATFORM TRIGGERS
  {
    method: "POST",
    path: "/api/ai/copilot",
    description: "Dispatches a chat message or administrative requirement context to the Gemini LLM platform gateway.",
    roles: ["Client", "Staff"],
    requestBody: "{\n  \"message\": \"Explain how the Mikrotik automatic trigger handles invoice payments upon Daraja callback completion.\"\n}",
    responseBody: "{\n  \"text\": \"### Reconcile Payment Automated System Logic\\n\\nUpon receiving Safaricom's Callback payload...\"\n}"
  },

  // SECTION 6: ENTERPRISE PAYMENT GATEWAY ADAPTER APIs
  {
    method: "POST",
    path: "/api/payments/intents",
    description: "Registers a provider-agnostic payment session inside JUANET. Determines routing rules and initializes the selected external adapter.",
    roles: ["Client", "Customer Checkout"],
    requestBody: "{\n  \"invoiceId\": \"3b7d4bad...\",\n  \"amount\": 45000.00,\n  \"currency\": \"KES\",\n  \"paymentMethod\": \"mpesa_stk\",\n  \"payerPhone\": \"254712345678\"\n}",
    responseBody: "{\n  \"status\": \"success\",\n  \"intentId\": \"pi_550ea31c-d78a-4cf1-8bc9-22a1599aef5b\",\n  \"gateway\": \"safaricom_daraja\",\n  \"referenceNumber\": \"TXN-KES-2026-89A12\",\n  \"providerPayload\": {\n    \"MerchantRequestID\": \"1283-398231-1\",\n    \"CheckoutRequestID\": \"ws_CO_25062026111455_99A1\",\n    \"CustomerMessage\": \"Success. Request accepted for processing.\"\n  }\n}"
  },
  {
    method: "POST",
    path: "/api/payments/webhooks/:provider",
    description: "Central entry point receiving asymmetric webhook callbacks from active gateway providers (e.g., safaricom_daraja, stripe, paystack, pesapal). Performs signature validation, replay-attack checking, and idempotent balance logging.",
    roles: ["External Gateway Provider Service"],
    requestBody: "{\n  \"provider_headers\": { \"x-webhook-signature\": \"sha256=...\", \"x-idempotency-key\": \"...\" },\n  \"payload_body\": { ...Raw Provider Webhook Callback JSON... }\n}",
    responseBody: "{\n  \"status\": \"acknowledged\",\n  \"ledgerPosted\": true,\n  \"receiptId\": \"rcpt_220ca31e-448c-4cf2-89a1-00029ea956ba\"\n}"
  },
  {
    method: "GET",
    path: "/api/payments/verify/:intentId",
    description: "Actively queries status from the external provider's API endpoint via the Gateway Provider Adapter in cases of delayed webhooks or active client polling.",
    roles: ["Client Dashboard", "Finance Officer", "Super Admin"],
    responseBody: "{\n  \"intentId\": \"pi_550ea31c-d78a-4cf1-8bc9-22a1599aef5b\",\n  \"provider\": \"safaricom_daraja\",\n  \"status\": \"completed\",\n  \"providerTransactionId\": \"ws_CO_25062026111455_99A1\",\n  \"reconciledAt\": \"2026-06-26T14:52:18Z\"\n}"
  },
  {
    method: "POST",
    path: "/api/payments/refund/:intentId",
    description: "Initiates a refund request through the adapter framework. Supports reverse payment routes and ledger reversal auditing logs.",
    roles: ["Finance Officer", "Super Admin"],
    requestBody: "{\n  \"amount\": 45000.00,\n  \"reason\": \"Overpayment reconciliation\"\n}",
    responseBody: "{\n  \"status\": \"refund_initiated\",\n  \"refundId\": \"ref_882ca12c-47bc-4fa1-8da1-99882200aabb\",\n  \"providerRefundId\": \"re_stripe_99A12bc\"\n}"
  }
];

export const SERVICE_ITEMS = [
  { name: "Website Development", category: "Development", rate: "KES 150,000+ / proj", icon: "Globe" },
  { name: "SaaS Development", category: "Development", rate: "KES 500,000+ / proj", icon: "Layers" },
  { name: "Custom Software Development", category: "Development", rate: "KES 3,500 / hr", icon: "Code" },
  { name: "Web App Development", category: "Development", rate: "KES 300,000+ / proj", icon: "Laptop" },
  { name: "AI Integration Services", category: "Development", rate: "KES 250,000+ / proj", icon: "Cpu" },
  { name: "Graphics Design", category: "Design", rate: "KES 50,000+ / pack", icon: "Palette" },
  { name: "Social Media Management", category: "Marketing", rate: "KES 100,000+ / mo", icon: "Share2" },
  { name: "Website Management", category: "Marketing", rate: "KES 50,000+ / mo", icon: "ShieldCheck" },
  { name: "Software Consultation", category: "Consulting", rate: "KES 15,000 / hr", icon: "HelpCircle" },
  { name: "Custom Dashboards", category: "Development", rate: "KES 200,000+ / proj", icon: "LayoutGrid" },
  { name: "Tech Adoption Consultation", category: "Consulting", rate: "KES 15,000 / hr", icon: "Lightbulb" },
  { name: "WiFi Installation", category: "IT Support", rate: "Survey Custom Quote", icon: "Wifi" },
  { name: "Server Management", category: "IT Support", rate: "KES 30,000+ / mo", icon: "Server" },
  { name: "Outsourced IT Personnel", category: "IT Support", rate: "KES 120,000+ / mo", icon: "Users" },
  { name: "Hardware and Software Procurement", category: "IT Support", rate: "Cost + 10% Fee", icon: "ShoppingCart" }
];
