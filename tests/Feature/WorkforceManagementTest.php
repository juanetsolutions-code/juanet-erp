<?php

use App\Contracts\EventBus;
use App\Models\User;
use App\Models\Organization;
use App\Domain\Workforce\Models\Team;
use App\Domain\Workforce\Models\Department;
use App\Domain\Workforce\Models\Position;
use App\Domain\Workforce\Models\EmployeeProfile;
use App\Domain\Workforce\Models\Skill;
use App\Domain\Workforce\Models\EmployeeSkill;
use App\Domain\Workforce\Models\Assignment;
use App\Domain\Workforce\Models\TimeEntry;
use App\Domain\Workforce\Models\LeaveRequest;
use App\Domain\Workforce\Models\Availability;
use App\Domain\Workforce\Services\WorkforceService;
use App\Domain\Workforce\Events\EmployeeAssigned;
use App\Domain\Workforce\Events\AssignmentUpdated;
use App\Domain\Workforce\Events\TimeLogged;
use App\Domain\Workforce\Events\LeaveRequested;
use App\Domain\Workforce\Events\LeaveApproved;
use App\Domain\Workforce\Events\SkillUpdated;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('workforce structure can be created with departments, positions, and teams', function () {
    $org = Organization::create(['name' => 'JUANET HQ', 'slug' => 'juanet-hq']);
    $user = User::create(['name' => 'Staff Developer', 'email' => 'dev@juanet.co.ke', 'password' => 'pass123']);

    $service = app(WorkforceService::class);

    $dept = $service->createDepartment($org->id, 'Product Engineering', $user->id);
    expect($dept->name)->toBe('Product Engineering');
    expect($dept->slug)->toBe('product-engineering');
    expect($dept->manager_id)->toBe($user->id);

    $position = $service->createPosition($org->id, 'Senior Engineer', $dept->id);
    expect($position->name)->toBe('Senior Engineer');
    expect($position->slug)->toBe('senior-engineer');

    $team = $service->createTeam($org->id, 'Kernel Core Team', $user->id);
    expect($team->name)->toBe('Kernel Core Team');
    expect($team->slug)->toBe('kernel-core-team');

    $profile = $service->createEmployeeProfile($org->id, $user->id, $dept->id, $position->id);
    expect($profile->user_id)->toBe($user->id);
    expect($profile->skills_expert_score)->toBe(0);
    expect($profile->status)->toBe('active');

    $service->assignEmployeeToTeam($profile->id, $team->id);
    expect($profile->teams()->first()->id)->toBe($team->id);
});

test('skills matrix assigns skills and recalculates expert scores correctly', function () {
    $eventBus = Mockery::mock(EventBus::class);
    $eventBus->shouldReceive('dispatch')->with(Mockery::type(SkillUpdated::class))->once();
    app()->instance(EventBus::class, $eventBus);

    $org = Organization::create(['name' => 'JUANET HQ', 'slug' => 'juanet-hq']);
    $user = User::create(['name' => 'Jane Dev', 'email' => 'jane@juanet.co.ke', 'password' => 'pass123']);

    $service = app(WorkforceService::class);
    $profile = $service->createEmployeeProfile($org->id, $user->id);
    $skill = $service->createSkill($org->id, 'Laravel PHP 8.4 Framework', 'Backend');

    // Add expert primary skill: Base Expert=10, Primary Multiplier=1.5. Total score should be 15.
    $service->addEmployeeSkill($profile->id, $skill->id, 'primary', 'expert');

    $profile->refresh();
    expect($profile->skills_expert_score)->toBe(15);

    // Add intermediate secondary skill: Base Intermediate=5, Secondary Multiplier=1.0. Total: 15 + 5 = 20.
    $skill2 = $service->createSkill($org->id, 'Vue.js', 'Frontend');
    $service->addEmployeeSkill($profile->id, $skill2->id, 'secondary', 'intermediate');

    $profile->refresh();
    expect($profile->skills_expert_score)->toBe(20);
});

test('assignments can be created, workload updated, and events dispatched', function () {
    $eventBus = Mockery::mock(EventBus::class);
    $eventBus->shouldReceive('dispatch')->with(Mockery::type(EmployeeAssigned::class))->once();
    $eventBus->shouldReceive('dispatch')->with(Mockery::type(AssignmentUpdated::class))->twice(); // one for workload, one for completion
    app()->instance(EventBus::class, $eventBus);

    $org = Organization::create(['name' => 'JUANET HQ', 'slug' => 'juanet-hq']);
    $user = User::create(['name' => 'John Fullstack', 'email' => 'john@juanet.co.ke', 'password' => 'pass123']);

    $service = app(WorkforceService::class);
    $profile = $service->createEmployeeProfile($org->id, $user->id);

    // Create assignment
    $asg = $service->assignEmployee(
        $org->id,
        $profile->id,
        'Lead Architect',
        '2026-07-06',
        '2026-12-31',
        null,
        null,
        30.00
    );

    expect($asg->role)->toBe('Lead Architect');
    expect($asg->estimated_workload)->toBe(30.00);

    // Update workload
    $service->updateAssignmentWorkload($asg->id, 40.00);
    $asg->refresh();
    expect($asg->estimated_workload)->toBe(40.00);

    // Remove employee (complete assignment)
    $service->removeEmployeeFromAssignment($asg->id);
    $asg->refresh();
    expect($asg->status)->toBe('completed');
});

test('time tracker records live sessions, manual entries, and calculates summaries', function () {
    $eventBus = Mockery::mock(EventBus::class);
    $eventBus->shouldReceive('dispatch')->with(Mockery::type(TimeLogged::class))->twice();
    app()->instance(EventBus::class, $eventBus);

    $org = Organization::create(['name' => 'JUANET HQ', 'slug' => 'juanet-hq']);
    $user = User::create(['name' => 'SaaS QA', 'email' => 'qa@juanet.co.ke', 'password' => 'pass123']);

    $service = app(WorkforceService::class);
    $profile = $service->createEmployeeProfile($org->id, $user->id);

    // 1. Live session
    $timer = $service->startTimer($org->id, $profile->id, null, null);
    expect($timer->start_time)->not->toBeNull();
    expect($timer->end_time)->toBeNull();

    // Stop timer (simulating duration entry)
    $timer = $service->stopTimer($timer->id, 'Ran automated testing suites');
    expect($timer->end_time)->not->toBeNull();

    // 2. Manual entry
    $manual = $service->logTimeManual(
        $org->id,
        $profile->id,
        180, // 3 hours
        '2026-07-06',
        null,
        null,
        true,
        'Drafting QA test strategy'
    );

    expect($manual->duration_minutes)->toBe(180);
    expect($manual->is_manual)->toBeTrue();

    // 3. Summaries
    $summary = $service->getTimeSummary($org->id, $profile->id);
    expect($summary['total_hours'])->toBeGreaterThan(2.9);
    expect($summary['billable_hours'])->toBeGreaterThan(2.9);
});

test('leave management processes vacation request and updates availability', function () {
    $eventBus = Mockery::mock(EventBus::class);
    $eventBus->shouldReceive('dispatch')->with(Mockery::type(LeaveRequested::class))->once();
    $eventBus->shouldReceive('dispatch')->with(Mockery::type(LeaveApproved::class))->once();
    app()->instance(EventBus::class, $eventBus);

    $org = Organization::create(['name' => 'JUANET HQ', 'slug' => 'juanet-hq']);
    $user = User::create(['name' => 'Mombasa Vacationer', 'email' => 'beach@juanet.co.ke', 'password' => 'pass123']);
    $manager = User::create(['name' => 'SaaS PM', 'email' => 'pm@juanet.co.ke', 'password' => 'pass123']);

    $service = app(WorkforceService::class);
    $profile = $service->createEmployeeProfile($org->id, $user->id);

    // Request Leave
    $leave = $service->requestLeave(
        $org->id,
        $profile->id,
        'vacation',
        '2026-07-10',
        '2026-07-12',
        'Traveling to Diani beach'
    );

    expect($leave->status)->toBe('pending');
    expect($leave->reason)->toBe('Traveling to Diani beach');

    // Approve Leave
    $service->approveLeave($leave->id, $manager->id);
    $leave->refresh();
    expect($leave->status)->toBe('approved');
    expect($leave->approved_by_id)->toBe($manager->id);

    // Availability should be updated to 'unavailable' for the dates: 10, 11, 12.
    $availability = Availability::where('employee_profile_id', $profile->id)->get();
    expect($availability->count())->toBe(3);
    expect($availability->first()->status)->toBe('unavailable');
    expect($availability->first()->capacity_percentage)->toBe(0);
});

test('tenant isolation ensures workforce data remains isolated between organizations', function () {
    // Org A
    $orgA = Organization::create(['name' => 'JUANET Software', 'slug' => 'juanet-sw']);
    $userA = User::create(['name' => 'Dev Alpha', 'email' => 'alpha@juanet.co.ke', 'password' => 'pass123']);

    // Org B
    $orgB = Organization::create(['name' => 'JUANET Infrastructure', 'slug' => 'juanet-infra']);
    $userB = User::create(['name' => 'Dev Beta', 'email' => 'beta@juanet.co.ke', 'password' => 'pass123']);

    $service = app(WorkforceService::class);

    $profileA = $service->createEmployeeProfile($orgA->id, $userA->id);
    $profileB = $service->createEmployeeProfile($orgB->id, $userB->id);

    // Retrieve planner data for Org A
    $plannerA = $service->getResourcePlannerData($orgA->id);
    expect($plannerA->count())->toBe(1);
    expect($plannerA->first()['employee']['id'])->toBe($profileA->id);

    // Retrieve planner data for Org B
    $plannerB = $service->getResourcePlannerData($orgB->id);
    expect($plannerB->count())->toBe(1);
    expect($plannerB->first()['employee']['id'])->toBe($profileB->id);
});
