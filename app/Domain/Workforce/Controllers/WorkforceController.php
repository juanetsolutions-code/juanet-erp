<?php

namespace App\Domain\Workforce\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Workforce\Services\WorkforceService;
use App\Services\TenantContext;
use App\Domain\Workforce\Models\Team;
use App\Domain\Workforce\Models\Department;
use App\Domain\Workforce\Models\Position;
use App\Domain\Workforce\Models\EmployeeProfile;
use App\Domain\Workforce\Models\Skill;
use App\Domain\Workforce\Models\Assignment;
use App\Domain\Workforce\Models\TimeEntry;
use App\Domain\Workforce\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkforceController extends Controller
{
    protected WorkforceService $service;
    protected TenantContext $tenantContext;

    public function __construct(WorkforceService $service, TenantContext $tenantContext)
    {
        $this->service = $service;
        $this->tenantContext = $tenantContext;
    }

    private function getOrgId(): string
    {
        $orgId = $this->tenantContext->getTenantId();
        if (!$orgId) {
            // Fallback to first organization for testing/sandbox ease
            $user = Auth::user();
            if ($user && method_exists($user, 'organizations')) {
                $orgId = $user->organizations()->first()?->id;
            }
        }
        return $orgId ?? '00000000-0000-0000-0000-000000000000';
    }

    // ==========================================
    // DEPARTMENTS, POSITIONS, TEAMS
    // ==========================================

    public function getDepartments(): JsonResponse
    {
        $orgId = $this->getOrgId();
        $departments = Department::where('organization_id', $orgId)->with('manager')->get();
        return response()->json($departments);
    }

    public function createDepartment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'manager_id' => 'nullable|uuid|exists:users,id',
        ]);

        $dept = $this->service->createDepartment(
            $this->getOrgId(),
            $validated['name'],
            $validated['manager_id'] ?? null
        );

        return response()->json($dept, 21);
    }

    public function createPosition(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department_id' => 'nullable|uuid|exists:departments,id',
        ]);

        $pos = $this->service->createPosition(
            $this->getOrgId(),
            $validated['name'],
            $validated['department_id'] ?? null
        );

        return response()->json($pos, 21);
    }

    public function getTeams(): JsonResponse
    {
        $orgId = $this->getOrgId();
        $teams = Team::where('organization_id', $orgId)->with(['manager', 'employeeProfiles.user'])->get();
        return response()->json($teams);
    }

    public function createTeam(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'manager_id' => 'nullable|uuid|exists:users,id',
        ]);

        $team = $this->service->createTeam(
            $this->getOrgId(),
            $validated['name'],
            $validated['manager_id'] ?? null
        );

        return response()->json($team, 21);
    }

    // ==========================================
    // EMPLOYEE PROFILES
    // ==========================================

    public function getProfiles(Request $request): JsonResponse
    {
        $orgId = $this->getOrgId();
        $filters = $request->only(['department_id', 'status']);
        
        $profiles = EmployeeProfile::where('organization_id', $orgId)
            ->with(['user', 'department', 'position', 'teams', 'skills'])
            ->get();

        return response()->json($profiles);
    }

    public function createProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'department_id' => 'nullable|uuid|exists:departments,id',
            'position_id' => 'nullable|uuid|exists:positions,id',
            'reporting_to_id' => 'nullable|uuid|exists:employee_profiles,id',
        ]);

        // Check unique
        $exists = EmployeeProfile::where('user_id', $validated['user_id'])
            ->where('organization_id', $this->getOrgId())
            ->first();

        if ($exists) {
            return response()->json(['message' => 'Employee profile already exists for this user.'], 400);
        }

        $profile = $this->service->createEmployeeProfile(
            $this->getOrgId(),
            $validated['user_id'],
            $validated['department_id'] ?? null,
            $validated['position_id'] ?? null,
            $validated['reporting_to_id'] ?? null
        );

        return response()->json($profile, 21);
    }

    // ==========================================
    // SKILLS MATRIX
    // ==========================================

    public function getSkills(): JsonResponse
    {
        $orgId = $this->getOrgId();
        $skills = Skill::where('organization_id', $orgId)->get();
        return response()->json($skills);
    }

    public function createSkill(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
        ]);

        $skill = $this->service->createSkill(
            $this->getOrgId(),
            $validated['name'],
            $validated['category'] ?? null
        );

        return response()->json($skill, 21);
    }

    public function addSkillToEmployee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_profile_id' => 'required|uuid|exists:employee_profiles,id',
            'skill_id' => 'required|uuid|exists:skills,id',
            'type' => 'required|string|in:primary,secondary',
            'experience_level' => 'required|string|in:beginner,intermediate,advanced,expert',
            'certification' => 'nullable|string|max:255',
        ]);

        $employeeSkill = $this->service->addEmployeeSkill(
            $validated['employee_profile_id'],
            $validated['skill_id'],
            $validated['type'],
            $validated['experience_level'],
            $validated['certification'] ?? null
        );

        return response()->json($employeeSkill);
    }

    // ==========================================
    // ASSIGNMENTS
    // ==========================================

    public function createAssignment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_profile_id' => 'required|uuid|exists:employee_profiles,id',
            'role' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'project_id' => 'nullable|integer',
            'opportunity_id' => 'nullable|uuid|exists:crm_opportunities,id',
            'estimated_workload' => 'required|numeric|min:0',
        ]);

        $assignment = $this->service->assignEmployee(
            $this->getOrgId(),
            $validated['employee_profile_id'],
            $validated['role'],
            $validated['start_date'],
            $validated['end_date'] ?? null,
            $validated['project_id'] ?? null,
            $validated['opportunity_id'] ?? null,
            (float) $validated['estimated_workload']
        );

        return response()->json($assignment, 21);
    }

    public function removeAssignment(string $id): JsonResponse
    {
        $this->service->removeEmployeeFromAssignment($id);
        return response()->json(['message' => 'Employee removed from assignment successfully.']);
    }

    public function updateAssignmentWorkload(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'estimated_workload' => 'required|numeric|min:0',
        ]);

        $this->service->updateAssignmentWorkload($id, (float) $validated['estimated_workload']);
        return response()->json(['message' => 'Assignment workload updated successfully.']);
    }

    // ==========================================
    // RESOURCE PLANNER
    // ==========================================

    public function getPlanner(): JsonResponse
    {
        $orgId = $this->getOrgId();
        $plannerData = $this->service->getResourcePlannerData($orgId);
        return response()->json($plannerData);
    }

    // ==========================================
    // TIME TRACKING
    // ==========================================

    public function startTimer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_profile_id' => 'required|uuid|exists:employee_profiles,id',
            'assignment_id' => 'nullable|uuid|exists:assignments,id',
            'project_id' => 'nullable|integer',
        ]);

        $timer = $this->service->startTimer(
            $this->getOrgId(),
            $validated['employee_profile_id'],
            $validated['assignment_id'] ?? null,
            $validated['project_id'] ?? null
        );

        return response()->json($timer);
    }

    public function stopTimer(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'nullable|string',
        ]);

        $timer = $this->service->stopTimer($id, $validated['description'] ?? null);
        return response()->json($timer);
    }

    public function logTimeManual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_profile_id' => 'required|uuid|exists:employee_profiles,id',
            'duration_minutes' => 'required|integer|min:1',
            'date' => 'required|date',
            'assignment_id' => 'nullable|uuid|exists:assignments,id',
            'project_id' => 'nullable|integer',
            'is_billable' => 'required|boolean',
            'description' => 'nullable|string',
        ]);

        $entry = $this->service->logTimeManual(
            $this->getOrgId(),
            $validated['employee_profile_id'],
            (int) $validated['duration_minutes'],
            $validated['date'],
            $validated['assignment_id'] ?? null,
            $validated['project_id'] ?? null,
            $validated['is_billable'],
            $validated['description'] ?? null
        );

        return response()->json($entry, 21);
    }

    public function getTimeSummary(Request $request): JsonResponse
    {
        $employeeId = $request->query('employee_profile_id');
        $summary = $this->service->getTimeSummary($this->getOrgId(), $employeeId);
        return response()->json($summary);
    }

    // ==========================================
    // LEAVE MANAGEMENT
    // ==========================================

    public function requestLeave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_profile_id' => 'required|uuid|exists:employee_profiles,id',
            'type' => 'required|string|in:vacation,sick,emergency,remote_work',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        $leave = $this->service->requestLeave(
            $this->getOrgId(),
            $validated['employee_profile_id'],
            $validated['type'],
            $validated['start_date'],
            $validated['end_date'],
            $validated['reason'] ?? null
        );

        return response()->json($leave, 21);
    }

    public function approveLeave(Request $request, string $id): JsonResponse
    {
        $leave = $this->service->approveLeave($id, Auth::id() ?? '00000000-0000-0000-0000-000000000000');
        return response()->json($leave);
    }

    public function rejectLeave(Request $request, string $id): JsonResponse
    {
        $leave = $this->service->rejectLeave($id, Auth::id() ?? '00000000-0000-0000-0000-000000000000');
        return response()->json($leave);
    }
}
