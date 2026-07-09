import React, { useState, useEffect } from "react";
import {
  Users,
  Briefcase,
  Clock,
  Calendar,
  Award,
  Plus,
  Play,
  Square,
  TrendingUp,
  Sliders,
  CheckCircle,
  XCircle,
  PieChart,
  UserCheck,
  AlertCircle,
  ChevronRight,
  ShieldAlert,
  Search,
  Filter,
  Trash2,
  CalendarDays,
  FileText,
  DollarSign
} from "lucide-react";

// Types
interface Employee {
  id: string;
  name: string;
  avatar: string;
  role: string;
  department: string;
  status: "active" | "on_leave" | "inactive";
  availability: string; // e.g. "Available", "80% Workload", "On Vacation"
  skills_expert_score: number;
  primarySkills: string[];
  secondarySkills: string[];
  certifications: string[];
  email: string;
}

interface ProjectAssignment {
  id: string;
  employeeId: string;
  projectName: string;
  role: string;
  startDate: string;
  endDate: string;
  estimatedWorkload: number; // hours/week
  actualWorkload: number; // hours logged
  status: "active" | "completed" | "planned";
}

interface LeaveRequest {
  id: string;
  employeeId: string;
  employeeName: string;
  type: "vacation" | "sick" | "emergency" | "remote_work";
  startDate: string;
  endDate: string;
  status: "pending" | "approved" | "rejected";
  reason: string;
}

interface TimeEntry {
  id: string;
  employeeId: string;
  projectName: string;
  date: string;
  duration: number; // minutes
  isBillable: boolean;
  description: string;
}

export default function WorkforceTab() {
  const [activeSubTab, setActiveSubTab] = useState<"directory" | "kanban" | "planner" | "time" | "leave" | "crm">("directory");
  
  // High fidelity initial mock data representing real DB schemas
  const [employees, setEmployees] = useState<Employee[]>([
    {
      id: "EMP-001",
      name: "Juan Mwangi",
      avatar: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=120&h=120&q=80",
      role: "Lead Fullstack Architect",
      department: "Product Engineering",
      status: "active",
      availability: "35h committed (87%)",
      skills_expert_score: 85,
      primarySkills: ["Laravel", "PHP 8.4", "PostgreSQL", "DDD"],
      secondarySkills: ["React", "TypeScript", "Docker"],
      certifications: ["AWS Solutions Architect", "Laravel Certified Expert"],
      email: "juan@juanet.cloud"
    },
    {
      id: "EMP-002",
      name: "Amara Okeke",
      avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=120&h=120&q=80",
      role: "UI/UX Product Designer",
      department: "Design & UX Strategy",
      status: "active",
      availability: "20h committed (50%)",
      skills_expert_score: 92,
      primarySkills: ["Figma", "Design Systems", "Prototyping"],
      secondarySkills: ["React", "Tailwind CSS", "CSS Modules"],
      certifications: ["NN/g UX Master Certified"],
      email: "amara@juanet.cloud"
    },
    {
      id: "EMP-003",
      name: "Brian Otieno",
      avatar: "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=120&h=120&q=80",
      role: "Senior QA Engineer",
      department: "Quality Assurance",
      status: "active",
      availability: "40h committed (100%)",
      skills_expert_score: 78,
      primarySkills: ["Cypress", "PHPUnit", "CI/CD Automations"],
      secondarySkills: ["Docker", "Selenium", "API Testing"],
      certifications: ["ISTQB Advanced Technical Test Analyst"],
      email: "brian@juanet.cloud"
    },
    {
      id: "EMP-004",
      name: "Catherine Ndwiga",
      avatar: "https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=120&h=120&q=80",
      role: "SaaS Project Manager",
      department: "Project Management",
      status: "on_leave",
      availability: "0h committed (On Vacation)",
      skills_expert_score: 80,
      primarySkills: ["Agile Methodologies", "Risk Management", "Jira"],
      secondarySkills: ["Product Roadmap", "Stakeholder Comm"],
      certifications: ["PMP", "Scrum Alliance CSM"],
      email: "catherine@juanet.cloud"
    },
    {
      id: "EMP-005",
      name: "David Kimani",
      avatar: "https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=120&h=120&q=80",
      role: "Cloud DevOps Architect",
      department: "Infrastructure Operations",
      status: "active",
      availability: "15h committed (37%)",
      skills_expert_score: 88,
      primarySkills: ["Kubernetes", "AWS Cloud", "Terraform", "Nginx"],
      secondarySkills: ["CI/CD Automations", "Bash Shell", "Python"],
      certifications: ["AWS Certified DevOps Engineer Pro", "CKA"],
      email: "david@juanet.cloud"
    }
  ]);

  const [assignments, setAssignments] = useState<ProjectAssignment[]>([
    {
      id: "ASG-001",
      employeeId: "EMP-001",
      projectName: "M-PESA Settlement Gateway",
      role: "Lead Fullstack Architect",
      startDate: "2026-06-01",
      endDate: "2026-12-31",
      estimatedWorkload: 25,
      actualWorkload: 110,
      status: "active"
    },
    {
      id: "ASG-002",
      employeeId: "EMP-001",
      projectName: "Safaricom WiFi Survey Portal",
      role: "Database Consultant",
      startDate: "2026-07-01",
      endDate: "2026-09-30",
      estimatedWorkload: 10,
      actualWorkload: 15,
      status: "active"
    },
    {
      id: "ASG-003",
      employeeId: "EMP-002",
      projectName: "M-PESA Settlement Gateway",
      role: "Senior UI/UX Designer",
      startDate: "2026-06-01",
      endDate: "2026-10-15",
      estimatedWorkload: 20,
      actualWorkload: 85,
      status: "active"
    },
    {
      id: "ASG-004",
      employeeId: "EMP-003",
      projectName: "Safaricom WiFi Survey Portal",
      role: "QA Automation Lead",
      startDate: "2026-07-01",
      endDate: "2026-11-30",
      estimatedWorkload: 40,
      actualWorkload: 12,
      status: "active"
    },
    {
      id: "ASG-005",
      employeeId: "EMP-005",
      projectName: "Safaricom WiFi Survey Portal",
      role: "DevOps Engineer",
      startDate: "2026-07-01",
      endDate: "2026-10-31",
      estimatedWorkload: 15,
      actualWorkload: 8,
      status: "active"
    }
  ]);

  const [leaveRequests, setLeaveRequests] = useState<LeaveRequest[]>([
    {
      id: "LR-001",
      employeeId: "EMP-004",
      employeeName: "Catherine Ndwiga",
      type: "vacation",
      startDate: "2026-07-05",
      endDate: "2026-07-15",
      status: "approved",
      reason: "Annual family trip to Mombasa Coast"
    },
    {
      id: "LR-002",
      employeeId: "EMP-001",
      employeeName: "Juan Mwangi",
      type: "remote_work",
      startDate: "2026-07-10",
      endDate: "2026-07-12",
      status: "pending",
      reason: "Working remotely due to family event"
    },
    {
      id: "LR-003",
      employeeId: "EMP-003",
      employeeName: "Brian Otieno",
      type: "sick",
      startDate: "2026-07-20",
      endDate: "2026-07-22",
      status: "pending",
      reason: "Dental operation recovery"
    }
  ]);

  const [timeEntries, setTimeEntries] = useState<TimeEntry[]>([
    {
      id: "TE-001",
      employeeId: "EMP-001",
      projectName: "M-PESA Settlement Gateway",
      date: "2026-07-06",
      duration: 240, // 4 hours
      isBillable: true,
      description: "Optimized database index performance and CJS bundles in esbuild configuration."
    },
    {
      id: "TE-002",
      employeeId: "EMP-002",
      projectName: "M-PESA Settlement Gateway",
      date: "2026-07-06",
      duration: 180, // 3 hours
      isBillable: true,
      description: "Designed layout and spacing variations for workforce planner UI widgets."
    }
  ]);

  // Timer States
  const [isTimerRunning, setIsTimerRunning] = useState(false);
  const [timerSeconds, setTimerSeconds] = useState(0);
  const [timerAssignmentId, setTimerAssignmentId] = useState("ASG-001");
  const [timerDesc, setTimerDesc] = useState("");
  const timerIntervalRef = React.useRef<any>(null);

  // Form states
  const [searchTerm, setSearchTerm] = useState("");
  const [filterDept, setFilterDept] = useState("All");
  const [selectedStaff, setSelectedStaff] = useState<Employee | null>(employees[0]);
  
  // New assignment form
  const [newAsgStaff, setNewAsgStaff] = useState("EMP-001");
  const [newAsgProject, setNewAsgProject] = useState("M-PESA Settlement Gateway");
  const [newAsgRole, setNewAsgRole] = useState("Lead Fullstack Architect");
  const [newAsgWorkload, setNewAsgWorkload] = useState(20);

  // New leave request form
  const [newLeaveType, setNewLeaveType] = useState<"vacation" | "sick" | "emergency" | "remote_work">("vacation");
  const [newLeaveStart, setNewLeaveStart] = useState("2026-07-15");
  const [newLeaveEnd, setNewLeaveEnd] = useState("2026-07-20");
  const [newLeaveReason, setNewLeaveReason] = useState("");

  // CRM Resource Estimation Planner
  const [crmOppBudget, setCrmOppBudget] = useState(850000); // KES
  const [estDevelopers, setEstDevelopers] = useState(2);
  const [estDesigners, setEstDesigners] = useState(1);
  const [estQAs, setEstQAs] = useState(1);
  const [estWeeks, setEstWeeks] = useState(8);

  // Notification simulator
  const [notifications, setNotifications] = useState<string[]>([
    "System Alert: Catherine Ndwiga is currently on leave until 2026-07-15.",
    "Notification: You were assigned to Safaricom WiFi Survey Portal on 2026-07-01."
  ]);

  // Timer ticking logic
  useEffect(() => {
    if (isTimerRunning) {
      timerIntervalRef.current = setInterval(() => {
        setTimerSeconds((prev) => prev + 1);
      }, 1000);
    } else {
      if (timerIntervalRef.current) {
        clearInterval(timerIntervalRef.current);
      }
    }
    return () => {
      if (timerIntervalRef.current) clearInterval(timerIntervalRef.current);
    };
  }, [isTimerRunning]);

  const handleStartTimer = () => {
    setIsTimerRunning(true);
    setTimerSeconds(0);
    setNotifications((prev) => [
      `Timer started for ${assignments.find((a) => a.id === timerAssignmentId)?.projectName || "Project"}`,
      ...prev
    ]);
  };

  const handleStopTimer = () => {
    setIsTimerRunning(false);
    const loggedMinutes = Math.max(1, Math.round(timerSeconds / 60));
    const targetAssignment = assignments.find((a) => a.id === timerAssignmentId);
    
    const newEntry: TimeEntry = {
      id: `TE-00${timeEntries.length + 1}`,
      employeeId: "EMP-001", // Default current user
      projectName: targetAssignment ? targetAssignment.projectName : "General Project",
      date: new Date().toISOString().split("T")[0],
      duration: loggedMinutes,
      isBillable: true,
      description: timerDesc || "Time tracking timer stop."
    };

    setTimeEntries([newEntry, ...timeEntries]);
    
    // Update active workload in assignments
    if (targetAssignment) {
      setAssignments(
        assignments.map((asg) =>
          asg.id === targetAssignment.id
            ? { ...asg, actualWorkload: asg.actualWorkload + loggedMinutes / 60 }
            : asg
        )
      );
    }

    setTimerSeconds(0);
    setTimerDesc("");
    setNotifications((prev) => [
      `Logged ${loggedMinutes} minutes to ${newEntry.projectName} successfully. Event workforce.time.logged dispatched!`,
      ...prev
    ]);
  };

  const formatTimerTime = (totalSecs: number) => {
    const hrs = Math.floor(totalSecs / 3600);
    const mins = Math.floor((totalSecs % 3600) / 60);
    const secs = totalSecs % 60;
    return `${hrs.toString().padStart(2, "0")}:${mins.toString().padStart(2, "0")}:${secs.toString().padStart(2, "0")}`;
  };

  // Create Assignment
  const handleCreateAssignment = (e: React.FormEvent) => {
    e.preventDefault();
    const staff = employees.find((emp) => emp.id === newAsgStaff);
    if (!staff) return;

    const newAsg: ProjectAssignment = {
      id: `ASG-00${assignments.length + 1}`,
      employeeId: staff.id,
      projectName: newAsgProject,
      role: newAsgRole,
      startDate: new Date().toISOString().split("T")[0],
      endDate: "2026-12-31",
      estimatedWorkload: Number(newAsgWorkload),
      actualWorkload: 0,
      status: "active"
    };

    setAssignments([...assignments, newAsg]);

    // Update staff committed label
    const totalCommitted = assignments
      .filter((a) => a.employeeId === staff.id)
      .reduce((sum, current) => sum + current.estimatedWorkload, 0) + Number(newAsgWorkload);

    setEmployees(
      employees.map((emp) =>
        emp.id === staff.id
          ? { ...emp, availability: `${totalCommitted}h committed (${Math.round((totalCommitted / 40) * 100)}%)` }
          : emp
      )
    );

    setNotifications((prev) => [
      `Assigned ${staff.name} to ${newAsgProject} as ${newAsgRole}. Event workforce.employee.assigned dispatched!`,
      ...prev
    ]);
  };

  // Leave Request
  const handleRequestLeave = (e: React.FormEvent) => {
    e.preventDefault();
    const newReq: LeaveRequest = {
      id: `LR-00${leaveRequests.length + 1}`,
      employeeId: "EMP-001", // Current simulated user (Juan Mwangi)
      employeeName: "Juan Mwangi",
      type: newLeaveType,
      startDate: newLeaveStart,
      endDate: newLeaveEnd,
      status: "pending",
      reason: newLeaveReason || "Urgent personal matter."
    };

    setLeaveRequests([newReq, ...leaveRequests]);
    setNewLeaveReason("");
    setNotifications((prev) => [
      `Leave requested (${newLeaveType}) from ${newLeaveStart} to ${newLeaveEnd}. Event workforce.leave.requested dispatched!`,
      ...prev
    ]);
  };

  // Approve / Reject Leave
  const handleLeaveStatus = (id: string, status: "approved" | "rejected") => {
    setLeaveRequests(
      leaveRequests.map((req) => (req.id === id ? { ...req, status } : req))
    );

    const req = leaveRequests.find((r) => r.id === id);
    if (req) {
      if (status === "approved") {
        setEmployees(
          employees.map((emp) =>
            emp.id === req.employeeId ? { ...emp, status: "on_leave" } : emp
          )
        );
      }
      setNotifications((prev) => [
        `Leave request for ${req.employeeName} was ${status.toUpperCase()}. Event workforce.leave.approved dispatched!`,
        ...prev
      ]);
    }
  };

  // Filter logic
  const filteredEmployees = employees.filter((emp) => {
    const matchSearch = emp.name.toLowerCase().includes(searchTerm.toLowerCase()) || emp.role.toLowerCase().includes(searchTerm.toLowerCase());
    const matchDept = filterDept === "All" || emp.department === filterDept;
    return matchSearch && matchDept;
  });

  return (
    <div id="workforce-tab-root" className="bg-slate-900/90 text-slate-100 rounded-2xl border border-slate-800 p-6 shadow-2xl space-y-6">
      
      {/* Header and Summary Cards */}
      <div className="flex flex-col md:flex-row md:items-center justify-between border-b border-slate-800/80 pb-6 gap-4">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="p-1.5 bg-indigo-500/20 text-indigo-400 rounded-lg">
              <Users size={22} />
            </span>
            <h1 className="text-2xl font-bold tracking-tight text-white font-sans">
              Workforce Management & Enterprise Collaboration
            </h1>
          </div>
          <p className="text-sm text-slate-400 font-sans max-w-2xl">
            Centralized workforce allocations, smart skills matrix, capacity planners, and real-time time tracking. Built strictly to DDD & Hexagonal architecture standards.
          </p>
        </div>

        {/* Real-time Notifications Panel */}
        <div className="bg-slate-950/80 border border-slate-800/80 rounded-xl p-3 w-full md:w-80 h-24 overflow-y-auto font-mono text-[11px] text-slate-400 space-y-1">
          <div className="text-[10px] text-indigo-400 uppercase font-semibold border-b border-slate-800/60 pb-1 mb-1">
            ⚡ Transactional Outbox Log (EventBus)
          </div>
          {notifications.map((notif, index) => (
            <div key={index} className="flex gap-1 items-start leading-tight">
              <span className="text-indigo-400 font-extrabold">&raquo;</span>
              <span>{notif}</span>
            </div>
          ))}
        </div>
      </div>

      {/* Analytics Widgets Dashboard */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="bg-slate-950/50 border border-slate-800/80 rounded-xl p-4 flex items-center justify-between">
          <div>
            <div className="text-xs text-slate-500 uppercase font-semibold">Active Engineers</div>
            <div className="text-2xl font-bold text-white mt-1">
              {employees.filter((e) => e.status === "active").length} <span className="text-xs text-emerald-400 font-normal">({employees.length} total)</span>
            </div>
            <div className="text-[10px] text-indigo-400 font-mono mt-1">Tenant Isolation Scoped</div>
          </div>
          <div className="p-3 bg-indigo-500/10 text-indigo-400 rounded-lg">
            <UserCheck size={20} />
          </div>
        </div>

        <div className="bg-slate-950/50 border border-slate-800/80 rounded-xl p-4 flex items-center justify-between">
          <div>
            <div className="text-xs text-slate-500 uppercase font-semibold">Allocated Capacity</div>
            <div className="text-2xl font-bold text-white mt-1">74.2%</div>
            <div className="w-24 bg-slate-800 h-1.5 rounded-full mt-2 overflow-hidden">
              <div className="bg-indigo-500 h-full rounded-full" style={{ width: "74.2%" }} />
            </div>
          </div>
          <div className="p-3 bg-pink-500/10 text-pink-400 rounded-lg">
            <Sliders size={20} />
          </div>
        </div>

        <div className="bg-slate-950/50 border border-slate-800/80 rounded-xl p-4 flex items-center justify-between">
          <div>
            <div className="text-xs text-slate-500 uppercase font-semibold">Billable Hours logged</div>
            <div className="text-2xl font-bold text-white mt-1">7.0 hrs</div>
            <div className="text-[10px] text-slate-400 font-mono mt-1">Avg Rate: $125.00/hr</div>
          </div>
          <div className="p-3 bg-emerald-500/10 text-emerald-400 rounded-lg">
            <DollarSign size={20} />
          </div>
        </div>

        <div className="bg-slate-950/50 border border-slate-800/80 rounded-xl p-4 flex items-center justify-between">
          <div>
            <div className="text-xs text-slate-500 uppercase font-semibold">Pending Leaves</div>
            <div className="text-2xl font-bold text-amber-400 mt-1">
              {leaveRequests.filter((r) => r.status === "pending").length} requests
            </div>
            <div className="text-[10px] text-slate-500 font-mono mt-1">Requires approval workflow</div>
          </div>
          <div className="p-3 bg-amber-500/10 text-amber-400 rounded-lg">
            <CalendarDays size={20} />
          </div>
        </div>
      </div>

      {/* Sub Tabs Navigation */}
      <div className="flex flex-wrap gap-2 border-b border-slate-800/60 pb-3">
        <button
          onClick={() => setActiveSubTab("directory")}
          className={`flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold tracking-wide transition-all ${
            activeSubTab === "directory" ? "bg-indigo-600 text-white font-extrabold shadow-md" : "text-slate-400 hover:text-white bg-slate-950/40"
          }`}
        >
          <Users size={14} /> Team Directory & Profiles
        </button>
        <button
          onClick={() => setActiveSubTab("kanban")}
          className={`flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold tracking-wide transition-all ${
            activeSubTab === "kanban" ? "bg-indigo-600 text-white font-extrabold shadow-md" : "text-slate-400 hover:text-white bg-slate-950/40"
          }`}
        >
          <Sliders size={14} /> Kanban Workload Allocations
        </button>
        <button
          onClick={() => setActiveSubTab("time")}
          className={`flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold tracking-wide transition-all ${
            activeSubTab === "time" ? "bg-indigo-600 text-white font-extrabold shadow-md" : "text-slate-400 hover:text-white bg-slate-950/40"
          }`}
        >
          <Clock size={14} /> Interactive Time Tracker
        </button>
        <button
          onClick={() => setActiveSubTab("leave")}
          className={`flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold tracking-wide transition-all ${
            activeSubTab === "leave" ? "bg-indigo-600 text-white font-extrabold shadow-md" : "text-slate-400 hover:text-white bg-slate-950/40"
          }`}
        >
          <Calendar size={14} /> Leave & Vacation Approvals
        </button>
        <button
          onClick={() => setActiveSubTab("crm")}
          className={`flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold tracking-wide transition-all ${
            activeSubTab === "crm" ? "bg-indigo-600 text-white font-extrabold shadow-md" : "text-slate-400 hover:text-white bg-slate-950/40"
          }`}
        >
          <TrendingUp size={14} /> CRM Resource Estimator
        </button>
      </div>

      {/* Sub Tabs Content */}
      <div className="space-y-4">
        
        {/* TAB 1: TEAM DIRECTORY & PROFILES */}
        {activeSubTab === "directory" && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            {/* Staff list panel */}
            <div className="lg:col-span-2 space-y-4">
              <div className="flex flex-col sm:flex-row gap-3 items-center justify-between">
                <div className="relative w-full sm:w-72">
                  <span className="absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <Search size={14} />
                  </span>
                  <input
                    type="text"
                    placeholder="Search staff or skill..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="w-full bg-slate-950/60 border border-slate-800 rounded-lg pl-9 pr-4 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500 font-sans"
                  />
                </div>
                <div className="flex items-center gap-2 w-full sm:w-auto">
                  <span className="text-xs text-slate-400 flex items-center gap-1">
                    <Filter size={12} /> Dept:
                  </span>
                  <select
                    value={filterDept}
                    onChange={(e) => setFilterDept(e.target.value)}
                    className="bg-slate-950/60 border border-slate-800 text-xs rounded-lg py-2 px-3 focus:outline-none focus:ring-1 focus:ring-indigo-500 text-slate-200 font-sans"
                  >
                    <option value="All">All Departments</option>
                    <option value="Product Engineering">Product Engineering</option>
                    <option value="Design & UX Strategy">Design & UX Strategy</option>
                    <option value="Quality Assurance">Quality Assurance</option>
                    <option value="Project Management">Project Management</option>
                    <option value="Infrastructure Operations">Infrastructure Operations</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {filteredEmployees.map((emp) => (
                  <div
                    key={emp.id}
                    onClick={() => setSelectedStaff(emp)}
                    className={`p-4 rounded-xl border transition-all cursor-pointer flex flex-col justify-between ${
                      selectedStaff?.id === emp.id
                        ? "bg-slate-850 border-indigo-500/80 shadow-indigo-500/10 shadow-md"
                        : "bg-slate-950/40 border-slate-800/80 hover:bg-slate-850 hover:border-slate-700"
                    }`}
                  >
                    <div className="flex gap-3 items-start">
                      <img src={emp.avatar} alt={emp.name} className="w-12 h-12 rounded-full border border-slate-800 object-cover" />
                      <div className="space-y-0.5">
                        <div className="font-semibold text-sm text-white flex items-center gap-1.5">
                          {emp.name}
                          <span className={`w-2 h-2 rounded-full ${
                            emp.status === "active" ? "bg-emerald-400" : emp.status === "on_leave" ? "bg-amber-400" : "bg-slate-500"
                          }`} title={emp.status} />
                        </div>
                        <div className="text-xs text-slate-400">{emp.role}</div>
                        <div className="text-[10px] text-indigo-400 font-mono bg-indigo-500/5 px-1.5 py-0.5 rounded border border-indigo-500/10 inline-block">
                          {emp.department}
                        </div>
                      </div>
                    </div>

                    <div className="mt-4 pt-3 border-t border-slate-800/60 flex items-center justify-between text-xs text-slate-400">
                      <span className="font-mono text-[10px] text-slate-500">Expertise Score: {emp.skills_expert_score}</span>
                      <span className="text-[11px] font-medium text-slate-300">{emp.availability}</span>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Profile Detail Sidecard */}
            <div className="bg-slate-950/50 border border-slate-800 rounded-xl p-5 space-y-4">
              {selectedStaff ? (
                <>
                  <div className="text-center space-y-2 border-b border-slate-850 pb-4">
                    <img src={selectedStaff.avatar} alt={selectedStaff.name} className="w-20 h-20 rounded-full border-2 border-indigo-500/40 mx-auto object-cover" />
                    <h3 className="text-base font-bold text-white">{selectedStaff.name}</h3>
                    <p className="text-xs text-slate-400 leading-tight">{selectedStaff.role}</p>
                    <div className="text-[11px] text-slate-500">{selectedStaff.email}</div>
                  </div>

                  <div className="space-y-3">
                    <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
                      <Award size={12} className="text-indigo-400" /> Primary Skills
                    </h4>
                    <div className="flex flex-wrap gap-1.5">
                      {selectedStaff.primarySkills.map((s, idx) => (
                        <span key={idx} className="text-[10px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded px-2 py-0.5 font-mono">
                          {s}
                        </span>
                      ))}
                    </div>
                  </div>

                  <div className="space-y-3">
                    <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
                      <Award size={12} className="text-indigo-400" /> Secondary Skills
                    </h4>
                    <div className="flex flex-wrap gap-1.5">
                      {selectedStaff.secondarySkills.map((s, idx) => (
                        <span key={idx} className="text-[10px] bg-slate-800 text-slate-300 border border-slate-700 rounded px-2 py-0.5 font-mono">
                          {s}
                        </span>
                      ))}
                    </div>
                  </div>

                  <div className="space-y-3">
                    <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider">Certifications</h4>
                    <ul className="text-[11px] text-slate-300 space-y-1 pl-1">
                      {selectedStaff.certifications.map((c, idx) => (
                        <li key={idx} className="flex gap-1 items-center">
                          <CheckCircle size={10} className="text-indigo-400" /> {c}
                        </li>
                      ))}
                    </ul>
                  </div>

                  <div className="pt-3 border-t border-slate-850">
                    <h4 className="text-xs font-bold text-slate-400 mb-2">Committed Projects</h4>
                    <div className="space-y-2">
                      {assignments
                        .filter((a) => a.employeeId === selectedStaff.id)
                        .map((asg) => (
                          <div key={asg.id} className="bg-slate-900/60 p-2 rounded border border-slate-800/80 flex items-center justify-between text-xs">
                            <span className="font-semibold text-slate-200 truncate pr-2 max-w-40">{asg.projectName}</span>
                            <span className="font-mono text-indigo-400">{asg.estimatedWorkload}h/w</span>
                          </div>
                        ))}
                    </div>
                  </div>
                </>
              ) : (
                <div className="text-center text-slate-500 py-12 font-sans">
                  Select an employee to inspect profile and skill matrix.
                </div>
              )}
            </div>
          </div>
        )}

        {/* TAB 2: KANBAN WORKLOAD PLANNER */}
        {activeSubTab === "kanban" && (
          <div className="space-y-6">
            <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-slate-950/20 p-4 border border-slate-800 rounded-xl">
              <div>
                <h3 className="text-sm font-bold text-white mb-1">Interactive Project Resource Allocations</h3>
                <p className="text-xs text-slate-400 font-sans">
                  Allocate developers, designers, or QAs to active client projects. Tracks estimated vs actual workload to flag resource overburdens.
                </p>
              </div>
              
              {/* Assign resource inline form */}
              <form onSubmit={handleCreateAssignment} className="flex flex-wrap gap-2 items-center">
                <select
                  value={newAsgStaff}
                  onChange={(e) => setNewAsgStaff(e.target.value)}
                  className="bg-slate-950 border border-slate-800 text-xs rounded-lg py-2 px-3 text-slate-200 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                >
                  {employees.map((emp) => (
                    <option key={emp.id} value={emp.id}>{emp.name} ({emp.role})</option>
                  ))}
                </select>

                <select
                  value={newAsgProject}
                  onChange={(e) => setNewAsgProject(e.target.value)}
                  className="bg-slate-950 border border-slate-800 text-xs rounded-lg py-2 px-3 text-slate-200 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                >
                  <option value="M-PESA Settlement Gateway">M-PESA Settlement Gateway</option>
                  <option value="Safaricom WiFi Survey Portal">Safaricom WiFi Survey Portal</option>
                  <option value="SEO Optimization Audit">SEO Optimization Audit</option>
                </select>

                <input
                  type="number"
                  placeholder="hrs/week"
                  value={newAsgWorkload}
                  onChange={(e) => setNewAsgWorkload(Number(e.target.value))}
                  className="w-16 bg-slate-950 border border-slate-800 text-xs rounded-lg py-2 px-2 text-center focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                />

                <button
                  type="submit"
                  className="bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs px-3.5 py-2 rounded-lg flex items-center gap-1 transition-all shadow-md"
                >
                  <Plus size={14} /> Allocate
                </button>
              </form>
            </div>

            {/* Visual Workload Kanban Columns */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              
              {/* COL 1: Low Workload (<20h) */}
              <div className="bg-slate-950/40 border border-slate-800/80 rounded-xl p-4 space-y-4">
                <div className="flex items-center justify-between border-b border-slate-800 pb-2">
                  <span className="text-xs font-bold text-emerald-400 uppercase tracking-wider">Optimized / Underutilized</span>
                  <span className="bg-slate-900 border border-slate-800 text-[10px] text-slate-400 font-mono px-2 py-0.5 rounded-full">
                    {assignments.filter((a) => a.estimatedWorkload <= 20).length} items
                  </span>
                </div>
                <div className="space-y-3">
                  {assignments
                    .filter((a) => a.estimatedWorkload <= 20)
                    .map((asg) => {
                      const staff = employees.find((e) => e.id === asg.employeeId);
                      return (
                        <div key={asg.id} className="bg-slate-950/80 border border-slate-850 p-3.5 rounded-lg space-y-2">
                          <div className="flex justify-between items-start">
                            <span className="font-semibold text-xs text-white truncate max-w-40">{asg.projectName}</span>
                            <span className="text-[9px] bg-emerald-500/10 text-emerald-400 rounded-full px-2 py-0.5 font-semibold">
                              {asg.estimatedWorkload}h/w
                            </span>
                          </div>
                          <div className="flex items-center gap-2 text-xs text-slate-400">
                            <img src={staff?.avatar} alt={staff?.name} className="w-5 h-5 rounded-full object-cover" />
                            <span>{staff?.name} - <span className="text-[10px] text-slate-500">{asg.role}</span></span>
                          </div>
                        </div>
                      );
                    })}
                </div>
              </div>

              {/* COL 2: Medium Workload (20h-35h) */}
              <div className="bg-slate-950/40 border border-slate-800/80 rounded-xl p-4 space-y-4">
                <div className="flex items-center justify-between border-b border-slate-800 pb-2">
                  <span className="text-xs font-bold text-indigo-400 uppercase tracking-wider">Highly Productive</span>
                  <span className="bg-slate-900 border border-slate-800 text-[10px] text-slate-400 font-mono px-2 py-0.5 rounded-full">
                    {assignments.filter((a) => a.estimatedWorkload > 20 && a.estimatedWorkload <= 35).length} items
                  </span>
                </div>
                <div className="space-y-3">
                  {assignments
                    .filter((a) => a.estimatedWorkload > 20 && a.estimatedWorkload <= 35)
                    .map((asg) => {
                      const staff = employees.find((e) => e.id === asg.employeeId);
                      return (
                        <div key={asg.id} className="bg-slate-950/80 border border-slate-850 p-3.5 rounded-lg space-y-2">
                          <div className="flex justify-between items-start">
                            <span className="font-semibold text-xs text-white truncate max-w-40">{asg.projectName}</span>
                            <span className="text-[9px] bg-indigo-500/10 text-indigo-400 rounded-full px-2 py-0.5 font-semibold">
                              {asg.estimatedWorkload}h/w
                            </span>
                          </div>
                          <div className="flex items-center gap-2 text-xs text-slate-400">
                            <img src={staff?.avatar} alt={staff?.name} className="w-5 h-5 rounded-full object-cover" />
                            <span>{staff?.name} - <span className="text-[10px] text-slate-500">{asg.role}</span></span>
                          </div>
                        </div>
                      );
                    })}
                </div>
              </div>

              {/* COL 3: At Capacity (&gt;35h) */}
              <div className="bg-slate-950/40 border border-slate-800/80 rounded-xl p-4 space-y-4">
                <div className="flex items-center justify-between border-b border-slate-800 pb-2">
                  <span className="text-xs font-bold text-pink-400 uppercase tracking-wider">At Peak Capacity</span>
                  <span className="bg-slate-900 border border-slate-800 text-[10px] text-slate-400 font-mono px-2 py-0.5 rounded-full">
                    {assignments.filter((a) => a.estimatedWorkload > 35).length} items
                  </span>
                </div>
                <div className="space-y-3">
                  {assignments
                    .filter((a) => a.estimatedWorkload > 35)
                    .map((asg) => {
                      const staff = employees.find((e) => e.id === asg.employeeId);
                      return (
                        <div key={asg.id} className="bg-slate-950/80 border border-slate-850 p-3.5 rounded-lg space-y-2 relative overflow-hidden">
                          <div className="absolute top-0 right-0 left-0 h-0.5 bg-pink-500" />
                          <div className="flex justify-between items-start">
                            <span className="font-semibold text-xs text-white truncate max-w-40">{asg.projectName}</span>
                            <span className="text-[9px] bg-pink-500/10 text-pink-400 rounded-full px-2 py-0.5 font-semibold">
                              {asg.estimatedWorkload}h/w
                            </span>
                          </div>
                          <div className="flex items-center gap-2 text-xs text-slate-400">
                            <img src={staff?.avatar} alt={staff?.name} className="w-5 h-5 rounded-full object-cover" />
                            <span>{staff?.name} - <span className="text-[10px] text-pink-400">{asg.role}</span></span>
                          </div>
                          <div className="text-[10px] text-amber-400 font-sans flex items-center gap-1 mt-1 bg-amber-400/5 p-1 rounded">
                            <AlertCircle size={10} /> flag: close to overload.
                          </div>
                        </div>
                      );
                    })}
                </div>
              </div>

            </div>
          </div>
        )}

        {/* TAB 3: INTERACTIVE TIME TRACKER */}
        {activeSubTab === "time" && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            {/* Live Timer Widget */}
            <div className="bg-slate-950/60 border border-slate-800 rounded-xl p-5 space-y-4">
              <h3 className="text-sm font-bold text-white border-b border-slate-850 pb-2">
                Live Session Time Tracker
              </h3>

              <div className="space-y-3">
                <label className="text-xs text-slate-400 block font-semibold">Select Assigned Project</label>
                <select
                  value={timerAssignmentId}
                  onChange={(e) => setTimerAssignmentId(e.target.value)}
                  disabled={isTimerRunning}
                  className="w-full bg-slate-900 border border-slate-800 text-xs rounded-lg py-2 px-3 text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                >
                  {assignments
                    .filter((a) => a.employeeId === "EMP-001") // Only current user (Juan Mwangi)
                    .map((asg) => (
                      <option key={asg.id} value={asg.id}>
                        {asg.projectName} ({asg.role})
                      </option>
                    ))}
                </select>
              </div>

              <div className="space-y-2">
                <label className="text-xs text-slate-400 block font-semibold">Session Activity Note</label>
                <textarea
                  placeholder="What are you working on? (Optional)"
                  value={timerDesc}
                  onChange={(e) => setTimerDesc(e.target.value)}
                  disabled={isTimerRunning}
                  rows={2}
                  className="w-full bg-slate-900 border border-slate-800 text-xs rounded-lg p-2 text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-sans"
                />
              </div>

              {/* Big Clock display */}
              <div className="py-6 text-center">
                <div className="text-4xl font-mono font-bold tracking-widest text-indigo-400 bg-slate-950 border border-slate-850 rounded-2xl py-4 inline-block px-8">
                  {formatTimerTime(timerSeconds)}
                </div>
                <p className="text-[10px] text-slate-500 mt-2 font-mono">UTC: 2026-07-06 13:40:22</p>
              </div>

              {/* Timer Controls */}
              <div className="flex gap-2">
                {!isTimerRunning ? (
                  <button
                    onClick={handleStartTimer}
                    className="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs py-3 rounded-lg flex items-center justify-center gap-1.5 transition-all shadow-lg"
                  >
                    <Play size={14} /> Start Clock Timer
                  </button>
                ) : (
                  <button
                    onClick={handleStopTimer}
                    className="w-full bg-pink-600 hover:bg-pink-500 text-white font-extrabold text-xs py-3 rounded-lg flex items-center justify-center gap-1.5 transition-all shadow-lg animate-pulse"
                  >
                    <Square size={14} /> Stop &amp; Log Minutes
                  </button>
                )}
              </div>
            </div>

            {/* Manual time entries & summaries log */}
            <div className="lg:col-span-2 bg-slate-950/40 border border-slate-800 rounded-xl p-5 space-y-4">
              <div className="flex items-center justify-between border-b border-slate-850 pb-2">
                <h3 className="text-sm font-bold text-white">Recent Time Entries</h3>
                <span className="text-xs font-mono text-indigo-400">Total logged today: 7 hours</span>
              </div>

              <div className="space-y-3 max-h-80 overflow-y-auto">
                {timeEntries.map((entry) => {
                  const staff = employees.find((e) => e.id === entry.employeeId);
                  return (
                    <div key={entry.id} className="bg-slate-950/85 border border-slate-850 rounded-lg p-3.5 space-y-2 flex justify-between items-start">
                      <div className="space-y-1">
                        <div className="flex items-center gap-1.5">
                          <span className="font-semibold text-xs text-white">{entry.projectName}</span>
                          <span className={`text-[9px] px-1.5 py-0.5 rounded font-mono ${
                            entry.isBillable ? "bg-emerald-500/10 text-emerald-400" : "bg-slate-800 text-slate-400"
                          }`}>
                            {entry.isBillable ? "Billable" : "Non-billable"}
                          </span>
                        </div>
                        <p className="text-xs text-slate-300 font-sans">{entry.description}</p>
                        <div className="text-[10px] text-slate-500 font-sans flex items-center gap-2">
                          <img src={staff?.avatar} alt={staff?.name} className="w-4 h-4 rounded-full object-cover" />
                          <span>{staff?.name}</span>
                          <span>&bull;</span>
                          <span>{entry.date}</span>
                        </div>
                      </div>
                      <div className="text-right">
                        <span className="font-mono text-xs font-bold text-indigo-400">{entry.duration / 60} hrs</span>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

          </div>
        )}

        {/* TAB 4: LEAVE & VACATION APPROVALS */}
        {activeSubTab === "leave" && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            {/* Request Vacation Form */}
            <div className="bg-slate-950/60 border border-slate-800 rounded-xl p-5 space-y-4">
              <h3 className="text-sm font-bold text-white border-b border-slate-850 pb-2">
                Submit Leave / Remote Request
              </h3>

              <form onSubmit={handleRequestLeave} className="space-y-4">
                <div className="space-y-1">
                  <label className="text-xs text-slate-400 block font-semibold">Leave Category</label>
                  <select
                    value={newLeaveType}
                    onChange={(e: any) => setNewLeaveType(e.target.value)}
                    className="w-full bg-slate-900 border border-slate-800 text-xs rounded-lg py-2.5 px-3 text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  >
                    <option value="vacation">Paid Vacation</option>
                    <option value="sick">Sick Leave</option>
                    <option value="emergency">Emergency / Compassionate</option>
                    <option value="remote_work">Remote Work Agreement</option>
                  </select>
                </div>

                <div className="grid grid-cols-2 gap-2">
                  <div className="space-y-1">
                    <label className="text-xs text-slate-400 block font-semibold">Start Date</label>
                    <input
                      type="date"
                      value={newLeaveStart}
                      onChange={(e) => setNewLeaveStart(e.target.value)}
                      className="w-full bg-slate-900 border border-slate-800 text-xs rounded-lg py-2 px-2 text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                  </div>
                  <div className="space-y-1">
                    <label className="text-xs text-slate-400 block font-semibold">End Date</label>
                    <input
                      type="date"
                      value={newLeaveEnd}
                      onChange={(e) => setNewLeaveEnd(e.target.value)}
                      className="w-full bg-slate-900 border border-slate-800 text-xs rounded-lg py-2 px-2 text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                  </div>
                </div>

                <div className="space-y-1">
                  <label className="text-xs text-slate-400 block font-semibold">Reason &amp; Handover notes</label>
                  <textarea
                    placeholder="Provide details for your approval workflow..."
                    value={newLeaveReason}
                    onChange={(e) => setNewLeaveReason(e.target.value)}
                    rows={3}
                    className="w-full bg-slate-900 border border-slate-800 text-xs rounded-lg p-2.5 text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-sans"
                  />
                </div>

                <button
                  type="submit"
                  className="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs py-2.5 rounded-lg flex items-center justify-center gap-1 transition-all shadow-md"
                >
                  <Plus size={14} /> Submit Leave Request
                </button>
              </form>
            </div>

            {/* Leave Approvals Pipeline */}
            <div className="lg:col-span-2 bg-slate-950/40 border border-slate-800 rounded-xl p-5 space-y-4">
              <h3 className="text-sm font-bold text-white border-b border-slate-850 pb-2">
                Active Organization Leave Pipeline
              </h3>

              <div className="space-y-3">
                {leaveRequests.map((req) => (
                  <div key={req.id} className="bg-slate-950/85 border border-slate-850 rounded-lg p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div className="space-y-1.5">
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-xs text-white">{req.employeeName}</span>
                        <span className="text-[10px] text-slate-500 font-mono">({req.id})</span>
                        <span className={`text-[9px] font-mono px-1.5 py-0.5 rounded font-semibold ${
                          req.type === "vacation" ? "bg-indigo-500/10 text-indigo-400" : req.type === "sick" ? "bg-rose-500/10 text-rose-400" : "bg-emerald-500/10 text-emerald-400"
                        }`}>
                          {req.type.toUpperCase()}
                        </span>
                      </div>
                      <p className="text-xs text-slate-400 font-sans italic">"{req.reason}"</p>
                      <div className="text-[10px] text-slate-500 flex items-center gap-1 font-mono">
                        <Calendar size={10} /> {req.startDate} to {req.endDate}
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      {req.status === "pending" ? (
                        <>
                          <button
                            onClick={() => handleLeaveStatus(req.id, "approved")}
                            className="bg-emerald-600/20 hover:bg-emerald-600 text-emerald-400 hover:text-white border border-emerald-500/20 text-[10px] font-extrabold px-2.5 py-1.5 rounded transition-all flex items-center gap-1"
                          >
                            <CheckCircle size={10} /> Approve
                          </button>
                          <button
                            onClick={() => handleLeaveStatus(req.id, "rejected")}
                            className="bg-rose-600/20 hover:bg-rose-600 text-rose-400 hover:text-white border border-rose-500/20 text-[10px] font-extrabold px-2.5 py-1.5 rounded transition-all flex items-center gap-1"
                          >
                            <XCircle size={10} /> Reject
                          </button>
                        </>
                      ) : (
                        <span className={`text-xs font-bold font-sans flex items-center gap-1 ${
                          req.status === "approved" ? "text-emerald-400" : "text-rose-400"
                        }`}>
                          {req.status === "approved" ? <CheckCircle size={12} /> : <XCircle size={12} />}
                          {req.status.toUpperCase()}
                        </span>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>

          </div>
        )}

        {/* TAB 5: CRM RESOURCE ESTIMATOR */}
        {activeSubTab === "crm" && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            {/* Interactive sliders for opportunity resource estimator */}
            <div className="bg-slate-950/60 border border-slate-800 rounded-xl p-5 space-y-4">
              <h3 className="text-sm font-bold text-white border-b border-slate-850 pb-2">
                Estimate Required Resources
              </h3>
              <p className="text-xs text-slate-400 font-sans leading-relaxed">
                Estimate the direct staff allocation required to execute this CRM opportunity, automatically calculating project margins based on average developer cost.
              </p>

              <div className="space-y-4 pt-2">
                <div className="space-y-1.5">
                  <div className="flex justify-between text-xs font-semibold text-slate-300">
                    <span>Opportunity Estimated Budget</span>
                    <span className="font-mono text-indigo-400">KES {crmOppBudget.toLocaleString()}</span>
                  </div>
                  <input
                    type="range"
                    min={300000}
                    max={3000000}
                    step={50000}
                    value={crmOppBudget}
                    onChange={(e) => setCrmOppBudget(Number(e.target.value))}
                    className="w-full h-1 bg-slate-850 rounded-lg appearance-none cursor-pointer accent-indigo-500"
                  />
                </div>

                <div className="space-y-1.5">
                  <div className="flex justify-between text-xs font-semibold text-slate-300">
                    <span>Assigned Developers</span>
                    <span className="font-mono text-indigo-400">{estDevelopers} Devs</span>
                  </div>
                  <input
                    type="range"
                    min={1}
                    max={6}
                    step={1}
                    value={estDevelopers}
                    onChange={(e) => setEstDevelopers(Number(e.target.value))}
                    className="w-full h-1 bg-slate-850 rounded-lg appearance-none cursor-pointer accent-indigo-500"
                  />
                </div>

                <div className="space-y-1.5">
                  <div className="flex justify-between text-xs font-semibold text-slate-300">
                    <span>Assigned Designers</span>
                    <span className="font-mono text-indigo-400">{estDesigners} Designers</span>
                  </div>
                  <input
                    type="range"
                    min={0}
                    max={3}
                    step={1}
                    value={estDesigners}
                    onChange={(e) => setEstDesigners(Number(e.target.value))}
                    className="w-full h-1 bg-slate-850 rounded-lg appearance-none cursor-pointer accent-indigo-500"
                  />
                </div>

                <div className="space-y-1.5">
                  <div className="flex justify-between text-xs font-semibold text-slate-300">
                    <span>Project Duration</span>
                    <span className="font-mono text-indigo-400">{estWeeks} Weeks</span>
                  </div>
                  <input
                    type="range"
                    min={4}
                    max={24}
                    step={2}
                    value={estWeeks}
                    onChange={(e) => setEstWeeks(Number(e.target.value))}
                    className="w-full h-1 bg-slate-850 rounded-lg appearance-none cursor-pointer accent-indigo-500"
                  />
                </div>
              </div>
            </div>

            {/* Estimation output margin calculation */}
            <div className="lg:col-span-2 bg-slate-950/40 border border-slate-800 rounded-xl p-5 space-y-4">
              <h3 className="text-sm font-bold text-white border-b border-slate-850 pb-2 flex items-center gap-1.5">
                <PieChart size={16} className="text-indigo-400" /> Margin &amp; Cost Estimation Breakdown
              </h3>

              {(() => {
                const devWeeklyRate = 45000; // KES cost to company
                const designWeeklyRate = 35000;
                const devCost = estDevelopers * devWeeklyRate * estWeeks;
                const designCost = estDesigners * designWeeklyRate * estWeeks;
                const totalCost = devCost + designCost;
                const grossMargin = crmOppBudget - totalCost;
                const marginPercent = ((grossMargin / crmOppBudget) * 100).toFixed(1);

                return (
                  <div className="space-y-6">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                      <div className="bg-slate-950/80 p-3.5 rounded-lg border border-slate-850">
                        <div className="text-[10px] text-slate-500 uppercase font-semibold">Total Resource Cost</div>
                        <div className="text-lg font-bold text-white mt-1">KES {totalCost.toLocaleString()}</div>
                      </div>
                      <div className="bg-slate-950/80 p-3.5 rounded-lg border border-slate-850">
                        <div className="text-[10px] text-slate-500 uppercase font-semibold">Estimated Gross Margin</div>
                        <div className={`text-lg font-bold mt-1 ${grossMargin > 0 ? "text-emerald-400" : "text-rose-400"}`}>
                          KES {grossMargin.toLocaleString()}
                        </div>
                      </div>
                      <div className="bg-slate-950/80 p-3.5 rounded-lg border border-slate-850">
                        <div className="text-[10px] text-slate-500 uppercase font-semibold">Margin Percentage</div>
                        <div className={`text-lg font-bold mt-1 ${Number(marginPercent) > 40 ? "text-emerald-400" : "text-amber-400"}`}>
                          {marginPercent}%
                        </div>
                      </div>
                    </div>

                    <div className="bg-slate-900/40 p-4 border border-slate-800 rounded-lg space-y-3">
                      <h4 className="text-xs font-bold text-slate-300">Resource Planner Feasibility Recommendations</h4>
                      <ul className="text-xs text-slate-400 space-y-2 pl-1 font-sans">
                        <li className="flex gap-2 items-start">
                          <CheckCircle size={12} className="text-emerald-400 mt-0.5 shrink-0" />
                          <span>Staff Availability Check: Selected workforce profiles are estimated to have sufficient upcoming availability during this window.</span>
                        </li>
                        <li className="flex gap-2 items-start">
                          <CheckCircle size={12} className="text-emerald-400 mt-0.5 shrink-0" />
                          <span>Expertise Score match: The primary skills matches 100% of the opportunity parameters (Laravel backend, Figma prototypes).</span>
                        </li>
                      </ul>
                    </div>
                  </div>
                );
              })()}
            </div>

          </div>
        )}

      </div>
    </div>
  );
}
