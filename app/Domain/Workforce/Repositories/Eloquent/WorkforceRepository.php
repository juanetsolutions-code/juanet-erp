<?php

namespace App\Domain\Workforce\Repositories\Eloquent;

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
use Illuminate\Support\Collection;

class WorkforceRepository implements WorkforceRepositoryInterface
{
    public function findEmployeeProfile(string $id): ?EmployeeProfile
    {
        return EmployeeProfile::with(['user', 'department', 'position', 'teams', 'skills'])->find($id);
    }

    public function findEmployeeProfileByUserId(string $userId, string $orgId): ?EmployeeProfile
    {
        return EmployeeProfile::where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->with(['user', 'department', 'position', 'teams', 'skills'])
            ->first();
    }

    public function getEmployeeProfiles(string $orgId, array $filters = []): Collection
    {
        $query = EmployeeProfile::where('organization_id', $orgId)->with(['user', 'department', 'position', 'teams', 'skills']);

        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    public function saveEmployeeProfile(EmployeeProfile $profile): EmployeeProfile
    {
        $profile->save();
        return $profile;
    }

    public function findTeam(string $id): ?Team
    {
        return Team::with(['manager', 'employeeProfiles.user'])->find($id);
    }

    public function getTeams(string $orgId): Collection
    {
        return Team::where('organization_id', $orgId)->with(['manager', 'employeeProfiles.user'])->get();
    }

    public function saveTeam(Team $team): Team
    {
        $team->save();
        return $team;
    }

    public function findDepartment(string $id): ?Department
    {
        return Department::with('manager')->find($id);
    }

    public function getDepartments(string $orgId): Collection
    {
        return Department::where('organization_id', $orgId)->with('manager')->get();
    }

    public function saveDepartment(Department $department): Department
    {
        $department->save();
        return $department;
    }

    public function findPosition(string $id): ?Position
    {
        return Position::with('department')->find($id);
    }

    public function getPositions(string $orgId): Collection
    {
        return Position::where('organization_id', $orgId)->with('department')->get();
    }

    public function savePosition(Position $position): Position
    {
        $position->save();
        return $position;
    }

    public function findSkill(string $id): ?Skill
    {
        return Skill::find($id);
    }

    public function getSkills(string $orgId): Collection
    {
        return Skill::where('organization_id', $orgId)->get();
    }

    public function saveSkill(Skill $skill): Skill
    {
        $skill->save();
        return $skill;
    }

    public function saveEmployeeSkill(EmployeeSkill $employeeSkill): EmployeeSkill
    {
        $employeeSkill->save();
        return $employeeSkill;
    }

    public function findLeaveRequest(string $id): ?LeaveRequest
    {
        return LeaveRequest::with(['employeeProfile.user', 'approvedBy'])->find($id);
    }

    public function getLeaveRequests(string $orgId, ?string $employeeId = null): Collection
    {
        $query = LeaveRequest::where('organization_id', $orgId)->with(['employeeProfile.user', 'approvedBy']);

        if ($employeeId) {
            $query->where('employee_profile_id', $employeeId);
        }

        return $query->get();
    }

    public function saveLeaveRequest(LeaveRequest $request): LeaveRequest
    {
        $request->save();
        return $request;
    }

    public function findAssignment(string $id): ?Assignment
    {
        return Assignment::with(['employeeProfile.user', 'project', 'opportunity'])->find($id);
    }

    public function getAssignments(string $orgId, array $filters = []): Collection
    {
        $query = Assignment::where('organization_id', $orgId)->with(['employeeProfile.user', 'project', 'opportunity']);

        if (!empty($filters['employee_profile_id'])) {
            $query->where('employee_profile_id', $filters['employee_profile_id']);
        }

        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (!empty($filters['opportunity_id'])) {
            $query->where('opportunity_id', $filters['opportunity_id']);
        }

        return $query->get();
    }

    public function saveAssignment(Assignment $assignment): Assignment
    {
        $assignment->save();
        return $assignment;
    }

    public function findTimeEntry(string $id): ?TimeEntry
    {
        return TimeEntry::with(['employeeProfile.user', 'assignment', 'project'])->find($id);
    }

    public function getTimeEntries(string $orgId, ?string $employeeId = null): Collection
    {
        $query = TimeEntry::where('organization_id', $orgId)->with(['employeeProfile.user', 'assignment', 'project']);

        if ($employeeId) {
            $query->where('employee_profile_id', $employeeId);
        }

        return $query->get();
    }

    public function saveTimeEntry(TimeEntry $entry): TimeEntry
    {
        $entry->save();
        return $entry;
    }

    public function getAvailabilities(string $employeeProfileId, string $startDate, string $endDate): Collection
    {
        return Availability::where('employee_profile_id', $employeeProfileId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();
    }

    public function saveAvailability(Availability $availability): Availability
    {
        $availability->save();
        return $availability;
    }

    public function getWorkSchedule(string $employeeProfileId): ?WorkSchedule
    {
        return WorkSchedule::where('employee_profile_id', $employeeProfileId)->first();
    }

    public function saveWorkSchedule(WorkSchedule $schedule): WorkSchedule
    {
        $schedule->save();
        return $schedule;
    }
}
