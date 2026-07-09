<?php

namespace App\Domain\Workforce\Services;

use App\Contracts\EventBus;
use App\Domain\Notification\Services\NotificationService;
use App\Domain\Workforce\Events\EmployeeAssigned;
use App\Domain\Workforce\Events\AssignmentUpdated;
use App\Domain\Workforce\Events\TimeLogged;
use App\Domain\Workforce\Events\LeaveRequested;
use App\Domain\Workforce\Events\LeaveApproved;
use App\Domain\Workforce\Events\SkillUpdated;
use App\Domain\Workforce\Models\Team;
use App\Domain\Workforce\Models\Department;
use App\Domain\Workforce\Models\Position;
use App\Domain\Workforce\Models\EmployeeProfile;
use App\Domain\Workforce\Models\Skill;
use App\Domain\Workforce\Models\EmployeeSkill;
use App\Domain\Workforce\Models\Availability;
use App\Domain\Workforce\Models\WorkSchedule;
use App\Domain\Workforce\Models\LeaveRequest;
use App\Domain\Workforce\Models\Assignment;
use App\Domain\Workforce\Models\TimeEntry;
use App\Domain\Workforce\Repositories\WorkforceRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WorkforceService
{
    protected WorkforceRepositoryInterface $repository;
    protected EventBus $eventBus;
    protected NotificationService $notificationService;

    public function __construct(
        WorkforceRepositoryInterface $repository,
        EventBus $eventBus,
        NotificationService $notificationService
    ) {
        $this->repository = $repository;
        $this->eventBus = $eventBus;
        $this->notificationService = $notificationService;
    }

    // ==========================================
    // TEAM & WORKFORCE STRUCTURE
    // ==========================================

    public function createDepartment(string $orgId, string $name, ?string $managerId = null): Department
    {
        $dept = new Department([
            'organization_id' => $orgId,
            'name' => $name,
            'slug' => Str::slug($name),
            'manager_id' => $managerId,
        ]);
        return $this->repository->saveDepartment($dept);
    }

    public function createPosition(string $orgId, string $name, ?string $departmentId = null): Position
    {
        $pos = new Position([
            'organization_id' => $orgId,
            'department_id' => $departmentId,
            'name' => $name,
            'slug' => Str::slug($name),
        ]);
        return $this->repository->savePosition($pos);
    }

    public function createTeam(string $orgId, string $name, ?string $managerId = null): Team
    {
        $team = new Team([
            'organization_id' => $orgId,
            'name' => $name,
            'slug' => Str::slug($name),
            'manager_id' => $managerId,
        ]);
        return $this->repository->saveTeam($team);
    }

    public function createEmployeeProfile(
        string $orgId,
        string $userId,
        ?string $departmentId = null,
        ?string $positionId = null,
        ?string $reportingToId = null
    ): EmployeeProfile {
        $profile = new EmployeeProfile([
            'organization_id' => $orgId,
            'user_id' => $userId,
            'department_id' => $departmentId,
            'position_id' => $positionId,
            'reporting_to_id' => $reportingToId,
            'skills_expert_score' => 0,
            'status' => 'active',
        ]);
        return $this->repository->saveEmployeeProfile($profile);
    }

    public function assignEmployeeToTeam(string $employeeProfileId, string $teamId): void
    {
        $profile = $this->repository->findEmployeeProfile($employeeProfileId);
        if ($profile) {
            $profile->teams()->syncWithoutDetaching([$teamId]);
        }
    }

    // ==========================================
    // SKILLS MATRIX
    // ==========================================

    public function createSkill(string $orgId, string $name, ?string $category = null): Skill
    {
        $skill = new Skill([
            'organization_id' => $orgId,
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => $category,
        ]);
        return $this->repository->saveSkill($skill);
    }

    public function addEmployeeSkill(
        string $employeeProfileId,
        string $skillId,
        string $type = 'primary',
        string $experienceLevel = 'intermediate',
        ?string $certification = null
    ): EmployeeSkill {
        $employeeSkill = EmployeeSkill::updateOrCreate(
            [
                'employee_profile_id' => $employeeProfileId,
                'skill_id' => $skillId,
            ],
            [
                'type' => $type,
                'experience_level' => $experienceLevel,
                'certification' => $certification,
            ]
        );

        $this->recalculateExpertiseScore($employeeProfileId);

        $profile = $this->repository->findEmployeeProfile($employeeProfileId);
        $skill = $this->repository->findSkill($skillId);

        // Dispatch SkillUpdated event
        $this->eventBus->dispatch(new SkillUpdated([
            'employee_profile_id' => $employeeProfileId,
            'skill_id' => $skillId,
            'skill_name' => $skill ? $skill->name : '',
            'type' => $type,
            'experience_level' => $experienceLevel,
            'expert_score' => $profile ? $profile->skills_expert_score : 0,
        ], $profile ? $profile->organization_id : null));

        return $employeeSkill;
    }

    public function recalculateExpertiseScore(string $employeeProfileId): int
    {
        $profile = $this->repository->findEmployeeProfile($employeeProfileId);
        if (!$profile) {
            return 0;
        }

        $skills = EmployeeSkill::where('employee_profile_id', $employeeProfileId)->get();
        $score = 0;

        foreach ($skills as $skill) {
            $base = match ($skill->experience_level) {
                'beginner' => 2,
                'intermediate' => 5,
                'advanced' => 8,
                'expert' => 10,
                default => 5,
            };

            $multiplier = match ($skill->type) {
                'primary' => 1.5,
                'secondary' => 1.0,
                default => 1.0,
            };

            $score += (int) ($base * $multiplier);
        }

        $profile->skills_expert_score = $score;
        $this->repository->saveEmployeeProfile($profile);

        return $score;
    }

    // ==========================================
    // PROJECT ASSIGNMENTS
    // ==========================================

    public function assignEmployee(
        string $orgId,
        string $employeeProfileId,
        string $role,
        string $startDate,
        ?string $endDate = null,
        ?int $projectId = null,
        ?string $opportunityId = null,
        float $estimatedWorkload = 40.00
    ): Assignment {
        $assignment = new Assignment([
            'organization_id' => $orgId,
            'employee_profile_id' => $employeeProfileId,
            'project_id' => $projectId,
            'opportunity_id' => $opportunityId,
            'role' => $role,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'estimated_workload' => $estimatedWorkload,
            'actual_workload' => 0.00,
            'status' => 'active',
        ]);

        $this->repository->saveAssignment($assignment);

        $profile = $this->repository->findEmployeeProfile($employeeProfileId);
        if ($profile && $profile->user) {
            // Send In-App Notification using the NotificationCenter
            $projectName = $assignment->project ? $assignment->project->name : 'New Project';
            $this->notificationService->send(
                $profile->user->id,
                'Assigned to Project',
                "You have been assigned to project: {$projectName} as {$role}.",
                'info',
                'system',
                'high',
                $orgId
            );
        }

        // Dispatch EmployeeAssigned event
        $this->eventBus->dispatch(new EmployeeAssigned([
            'id' => $assignment->id,
            'employee_profile_id' => $employeeProfileId,
            'project_id' => $projectId,
            'opportunity_id' => $opportunityId,
            'role' => $role,
            'estimated_workload' => $estimatedWorkload,
        ], $orgId));

        return $assignment;
    }

    public function removeEmployeeFromAssignment(string $assignmentId): void
    {
        $assignment = $this->repository->findAssignment($assignmentId);
        if ($assignment) {
            $assignment->status = 'completed';
            $assignment->end_date = Carbon::now()->toDateString();
            $this->repository->saveAssignment($assignment);

            $profile = $this->repository->findEmployeeProfile($assignment->employee_profile_id);
            if ($profile && $profile->user) {
                $projectName = $assignment->project ? $assignment->project->name : 'Project';
                $this->notificationService->send(
                    $profile->user->id,
                    'Removed from Project',
                    "You have been removed from project: {$projectName}.",
                    'info',
                    'system',
                    'normal',
                    $assignment->organization_id
                );
            }

            // Dispatch AssignmentUpdated event
            $this->eventBus->dispatch(new AssignmentUpdated([
                'id' => $assignment->id,
                'employee_profile_id' => $assignment->employee_profile_id,
                'project_id' => $assignment->project_id,
                'role' => $assignment->role,
                'status' => 'completed',
            ], $assignment->organization_id));
        }
    }

    public function updateAssignmentWorkload(string $assignmentId, float $newEstimatedWorkload): void
    {
        $assignment = $this->repository->findAssignment($assignmentId);
        if ($assignment) {
            $oldWorkload = $assignment->estimated_workload;
            $assignment->estimated_workload = $newEstimatedWorkload;
            $this->repository->saveAssignment($assignment);

            $profile = $this->repository->findEmployeeProfile($assignment->employee_profile_id);
            if ($profile && $profile->user) {
                $projectName = $assignment->project ? $assignment->project->name : 'Project';
                $this->notificationService->send(
                    $profile->user->id,
                    'Workload Changed',
                    "Your estimated workload on project: {$projectName} has changed from {$oldWorkload}h/week to {$newEstimatedWorkload}h/week.",
                    'info',
                    'system',
                    'normal',
                    $assignment->organization_id
                );
            }

            // Dispatch AssignmentUpdated event
            $this->eventBus->dispatch(new AssignmentUpdated([
                'id' => $assignment->id,
                'employee_profile_id' => $assignment->employee_profile_id,
                'project_id' => $assignment->project_id,
                'role' => $assignment->role,
                'estimated_workload' => $newEstimatedWorkload,
                'status' => $assignment->status,
            ], $assignment->organization_id));
        }
    }

    // ==========================================
    // RESOURCE PLANNER
    // ==========================================

    public function getResourcePlannerData(string $orgId): Collection
    {
        $profiles = $this->repository->getEmployeeProfiles($orgId);
        $plannerData = collect();

        foreach ($profiles as $profile) {
            $currentAssignments = Assignment::where('employee_profile_id', $profile->id)
                ->where('status', 'active')
                ->get();

            $totalWorkload = $currentAssignments->sum('estimated_workload');
            $vacations = LeaveRequest::where('employee_profile_id', $profile->id)
                ->where('status', 'approved')
                ->where('end_date', '>=', Carbon::now()->toDateString())
                ->get();

            $plannerData->push([
                'employee' => [
                    'id' => $profile->id,
                    'name' => $profile->user ? $profile->user->name : 'Unknown',
                    'email' => $profile->user ? $profile->user->email : '',
                    'status' => $profile->status,
                    'department' => $profile->department ? $profile->department->name : null,
                    'position' => $profile->position ? $profile->position->name : null,
                    'skills_expert_score' => $profile->skills_expert_score,
                ],
                'current_workload' => $totalWorkload,
                'capacity' => 40.00, // standard 40h capacity
                'assignments' => $currentAssignments->map(fn($a) => [
                    'id' => $a->id,
                    'role' => $a->role,
                    'project_name' => $a->project ? $a->project->name : ($a->opportunity ? $a->opportunity->name : 'Opportunity'),
                    'workload' => $a->estimated_workload,
                    'start_date' => $a->start_date->toDateString(),
                    'end_date' => $a->end_date ? $a->end_date->toDateString() : null,
                ]),
                'leaves' => $vacations->map(fn($l) => [
                    'type' => $l->type,
                    'start_date' => $l->start_date->toDateString(),
                    'end_date' => $l->end_date->toDateString(),
                ]),
            ]);
        }

        return $plannerData;
    }

    // ==========================================
    // TIME TRACKING
    // ==========================================

    public function startTimer(string $orgId, string $employeeProfileId, ?string $assignmentId = null, ?int $projectId = null): TimeEntry
    {
        // Stop any currently running timers
        $running = TimeEntry::where('employee_profile_id', $employeeProfileId)
            ->whereNull('end_time')
            ->get();

        foreach ($running as $timer) {
            $this->stopTimer($timer->id);
        }

        $entry = new TimeEntry([
            'organization_id' => $orgId,
            'employee_profile_id' => $employeeProfileId,
            'assignment_id' => $assignmentId,
            'project_id' => $projectId,
            'start_time' => Carbon::now(),
            'end_time' => null,
            'duration_minutes' => 0,
            'is_billable' => true,
            'is_manual' => false,
        ]);

        return $this->repository->saveTimeEntry($entry);
    }

    public function stopTimer(string $timeEntryId, ?string $description = null): ?TimeEntry
    {
        $entry = $this->repository->findTimeEntry($timeEntryId);
        if ($entry && !$entry->end_time) {
            $entry->end_time = Carbon::now();
            $entry->duration_minutes = Carbon::parse($entry->start_time)->diffInMinutes($entry->end_time);
            if ($description !== null) {
                $entry->description = $description;
            }
            $this->repository->saveTimeEntry($entry);

            // Update assignment actual workload if applicable
            if ($entry->assignment_id) {
                $assignment = $this->repository->findAssignment($entry->assignment_id);
                if ($assignment) {
                    $totalMinutes = TimeEntry::where('assignment_id', $assignment->id)->sum('duration_minutes');
                    $assignment->actual_workload = round($totalMinutes / 60, 2);
                    $this->repository->saveAssignment($assignment);
                }
            }

            // Dispatch TimeLogged event
            $this->eventBus->dispatch(new TimeLogged([
                'id' => $entry->id,
                'employee_profile_id' => $entry->employee_profile_id,
                'project_id' => $entry->project_id,
                'duration_minutes' => $entry->duration_minutes,
                'is_billable' => $entry->is_billable,
            ], $entry->organization_id));

            return $entry;
        }

        return $entry;
    }

    public function logTimeManual(
        string $orgId,
        string $employeeProfileId,
        int $durationMinutes,
        string $date,
        ?string $assignmentId = null,
        ?int $projectId = null,
        bool $isBillable = true,
        ?string $description = null
    ): TimeEntry {
        $startTime = Carbon::parse($date)->startOfDay()->addHours(9); // Default manual logs start at 9:00 AM
        $endTime = (clone $startTime)->addMinutes($durationMinutes);

        $entry = new TimeEntry([
            'organization_id' => $orgId,
            'employee_profile_id' => $employeeProfileId,
            'assignment_id' => $assignmentId,
            'project_id' => $projectId,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'is_billable' => $isBillable,
            'is_manual' => true,
            'description' => $description,
        ]);

        $this->repository->saveTimeEntry($entry);

        // Update assignment actual workload
        if ($assignmentId) {
            $assignment = $this->repository->findAssignment($assignmentId);
            if ($assignment) {
                $totalMinutes = TimeEntry::where('assignment_id', $assignment->id)->sum('duration_minutes');
                $assignment->actual_workload = round($totalMinutes / 60, 2);
                $this->repository->saveAssignment($assignment);
            }
        }

        // Dispatch TimeLogged event
        $this->eventBus->dispatch(new TimeLogged([
            'id' => $entry->id,
            'employee_profile_id' => $employeeProfileId,
            'project_id' => $projectId,
            'duration_minutes' => $durationMinutes,
            'is_billable' => $isBillable,
            'is_manual' => true,
        ], $orgId));

        return $entry;
    }

    public function getTimeSummary(string $orgId, ?string $employeeId = null): array
    {
        $query = TimeEntry::where('organization_id', $orgId);
        if ($employeeId) {
            $query->where('employee_profile_id', $employeeId);
        }

        $entries = $query->get();
        $billable = $entries->where('is_billable', true)->sum('duration_minutes');
        $nonBillable = $entries->where('is_billable', false)->sum('duration_minutes');

        return [
            'total_hours' => round(($billable + $nonBillable) / 60, 2),
            'billable_hours' => round($billable / 60, 2),
            'non_billable_hours' => round($nonBillable / 60, 2),
            'total_entries' => $entries->count(),
        ];
    }

    // ==========================================
    // LEAVE MANAGEMENT
    // ==========================================

    public function requestLeave(
        string $orgId,
        string $employeeProfileId,
        string $type,
        string $startDate,
        string $endDate,
        ?string $reason = null
    ): LeaveRequest {
        $leave = new LeaveRequest([
            'organization_id' => $orgId,
            'employee_profile_id' => $employeeProfileId,
            'type' => $type, // vacation, sick, emergency, remote_work
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'pending',
            'reason' => $reason,
        ]);

        $this->repository->saveLeaveRequest($leave);

        // Dispatch LeaveRequested event
        $this->eventBus->dispatch(new LeaveRequested([
            'id' => $leave->id,
            'employee_profile_id' => $employeeProfileId,
            'type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], $orgId));

        // Notify Managers or Department Head if applicable
        $profile = $this->repository->findEmployeeProfile($employeeProfileId);
        if ($profile && $profile->department && $profile->department->manager_id) {
            $employeeName = $profile->user ? $profile->user->name : 'An employee';
            $this->notificationService->send(
                $profile->department->manager_id,
                'New Leave Request',
                "{$employeeName} has requested {$type} from {$startDate} to {$endDate}.",
                'info',
                'system',
                'normal',
                $orgId
            );
        }

        return $leave;
    }

    public function approveLeave(string $leaveId, string $approvedByUserId): LeaveRequest
    {
        $leave = $this->repository->findLeaveRequest($leaveId);
        if ($leave) {
            $leave->status = 'approved';
            $leave->approved_by_id = $approvedByUserId;
            $this->repository->saveLeaveRequest($leave);

            // Update EmployeeProfile status to 'on_leave' if currently active in date range
            $today = Carbon::now()->toDateString();
            if ($today >= $leave->start_date->toDateString() && $today <= $leave->end_date->toDateString()) {
                $profile = $this->repository->findEmployeeProfile($leave->employee_profile_id);
                if ($profile) {
                    $profile->status = 'on_leave';
                    $this->repository->saveEmployeeProfile($profile);
                }
            }

            // Also create Availabilities as unavailable/partially_available for dates
            $start = Carbon::parse($leave->start_date);
            $end = Carbon::parse($leave->end_date);
            while ($start->lte($end)) {
                Availability::updateOrCreate(
                    [
                        'employee_profile_id' => $leave->employee_profile_id,
                        'date' => $start->toDateString(),
                    ],
                    [
                        'status' => 'unavailable',
                        'capacity_percentage' => 0,
                        'notes' => 'On approved ' . $leave->type,
                    ]
                );
                $start->addDay();
            }

            // Notify user
            $profile = $this->repository->findEmployeeProfile($leave->employee_profile_id);
            if ($profile && $profile->user) {
                $this->notificationService->send(
                    $profile->user->id,
                    'Leave Request Approved',
                    "Your requested leave ({$leave->type}) from {$leave->start_date->toDateString()} to {$leave->end_date->toDateString()} has been APPROVED.",
                    'success',
                    'system',
                    'high',
                    $leave->organization_id
                );
            }

            // Dispatch LeaveApproved event
            $this->eventBus->dispatch(new LeaveApproved([
                'id' => $leave->id,
                'employee_profile_id' => $leave->employee_profile_id,
                'type' => $leave->type,
                'start_date' => $leave->start_date->toDateString(),
                'end_date' => $leave->end_date->toDateString(),
            ], $leave->organization_id));
        }

        return $leave;
    }

    public function rejectLeave(string $leaveId, string $rejectedByUserId): LeaveRequest
    {
        $leave = $this->repository->findLeaveRequest($leaveId);
        if ($leave) {
            $leave->status = 'rejected';
            $leave->approved_by_id = $rejectedByUserId;
            $this->repository->saveLeaveRequest($leave);

            // Notify user
            $profile = $this->repository->findEmployeeProfile($leave->employee_profile_id);
            if ($profile && $profile->user) {
                $this->notificationService->send(
                    $profile->user->id,
                    'Leave Request Rejected',
                    "Your requested leave ({$leave->type}) from {$leave->start_date->toDateString()} to {$leave->end_date->toDateString()} has been REJECTED.",
                    'error',
                    'system',
                    'high',
                    $leave->organization_id
                );
            }
        }

        return $leave;
    }
}
