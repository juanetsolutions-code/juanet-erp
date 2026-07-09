<?php

namespace App\Domain\Workforce\Repositories;

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
use Illuminate\Support\Collection;

interface WorkforceRepositoryInterface
{
    // Employee Profiles
    public function findEmployeeProfile(string $id): ?EmployeeProfile;
    public function findEmployeeProfileByUserId(string $userId, string $orgId): ?EmployeeProfile;
    public function getEmployeeProfiles(string $orgId, array $filters = []): Collection;
    public function saveEmployeeProfile(EmployeeProfile $profile): EmployeeProfile;

    // Teams
    public function findTeam(string $id): ?Team;
    public function getTeams(string $orgId): Collection;
    public function saveTeam(Team $team): Team;

    // Departments
    public function findDepartment(string $id): ?Department;
    public function getDepartments(string $orgId): Collection;
    public function saveDepartment(Department $department): Department;

    // Positions
    public function findPosition(string $id): ?Position;
    public function getPositions(string $orgId): Collection;
    public function savePosition(Position $position): Position;

    // Skills
    public function findSkill(string $id): ?Skill;
    public function getSkills(string $orgId): Collection;
    public function saveSkill(Skill $skill): Skill;
    public function saveEmployeeSkill(EmployeeSkill $employeeSkill): EmployeeSkill;

    // Leave Requests
    public function findLeaveRequest(string $id): ?LeaveRequest;
    public function getLeaveRequests(string $orgId, ?string $employeeId = null): Collection;
    public function saveLeaveRequest(LeaveRequest $request): LeaveRequest;

    // Assignments
    public function findAssignment(string $id): ?Assignment;
    public function getAssignments(string $orgId, array $filters = []): Collection;
    public function saveAssignment(Assignment $assignment): Assignment;

    // Time Entries
    public function findTimeEntry(string $id): ?TimeEntry;
    public function getTimeEntries(string $orgId, ?string $employeeId = null): Collection;
    public function saveTimeEntry(TimeEntry $entry): TimeEntry;

    // Availabilities & Schedules
    public function getAvailabilities(string $employeeProfileId, string $startDate, string $endDate): Collection;
    public function saveAvailability(Availability $availability): Availability;
    public function getWorkSchedule(string $employeeProfileId): ?WorkSchedule;
    public function saveWorkSchedule(WorkSchedule $schedule): WorkSchedule;
}
