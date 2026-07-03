<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Core Permissions
        $permissions = [
            // Core Platform Admin Permissions
            ['name' => 'Manage Platform settings', 'slug' => 'platform.manage', 'module' => 'platform'],
            
            // Organization Administration Permissions
            ['name' => 'View Organization details', 'slug' => 'organization.view', 'module' => 'organization'],
            ['name' => 'Update Organization details', 'slug' => 'organization.update', 'module' => 'organization'],
            
            // User Administration Permissions
            ['name' => 'View User records', 'slug' => 'users.view', 'module' => 'users'],
            ['name' => 'Create User records', 'slug' => 'users.create', 'module' => 'users'],
            ['name' => 'Update User records', 'slug' => 'users.edit', 'module' => 'users'],
            ['name' => 'Delete User records', 'slug' => 'users.delete', 'module' => 'users'],
            
            // Billing & Invoicing permissions
            ['name' => 'Manage platform billing', 'slug' => 'billing.manage', 'module' => 'billing'],
        ];

        $permissionInstances = [];
        foreach ($permissions as $perm) {
            $permissionInstances[$perm['slug']] = Permission::create($perm);
        }

        // 2. Create Default Global Roles
        $rolesData = [
            [
                'name' => 'Super Administrator',
                'slug' => 'super-administrator',
                'description' => 'Unrestricted root-level administrative access to all systems and tenant data.',
                'is_system' => true
            ],
            [
                'name' => 'Administrator',
                'slug' => 'administrator',
                'description' => 'Standard organization-level administrative operations.',
                'is_system' => true
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Operational manager permissions for standard workflows.',
                'is_system' => false
            ],
            [
                'name' => 'Employee',
                'slug' => 'employee',
                'description' => 'Standard employee execution level permissions.',
                'is_system' => false
            ],
        ];

        $roleInstances = [];
        foreach ($rolesData as $roleInfo) {
            $roleInstances[$roleInfo['slug']] = Role::create($roleInfo);
        }

        // 3. Attach permissions to roles
        // Super Admin gets everything
        $roleInstances['super-administrator']->permissions()->attach(
            array_map(fn($p) => $p->id, $permissionInstances)
        );

        // Admin gets organization and user controls, but not platform settings
        $adminPermissions = array_filter(
            $permissionInstances,
            fn($p) => $p->slug !== 'platform.manage'
        );
        $roleInstances['administrator']->permissions()->attach(
            array_map(fn($p) => $p->id, $adminPermissions)
        );

        // Manager gets view and edit user, view organization
        $managerPermissions = array_filter(
            $permissionInstances,
            fn($p) => in_array($p->slug, ['organization.view', 'users.view', 'users.create', 'users.edit'])
        );
        $roleInstances['manager']->permissions()->attach(
            array_map(fn($p) => $p->id, $managerPermissions)
        );

        // Employee only gets views
        $employeePermissions = array_filter(
            $permissionInstances,
            fn($p) => in_array($p->slug, ['organization.view', 'users.view'])
        );
        $roleInstances['employee']->permissions()->attach(
            array_map(fn($p) => $p->id, $employeePermissions)
        );

        // 4. Create Default Organization (JUANET HQ)
        $organization = Organization::create([
            'name' => 'JUANET Headquarters',
            'slug' => 'juanet-hq',
            'domain' => 'hq.juanet.io',
            'status' => 'active',
        ]);

        // 5. Create first Super Administrator User
        $superAdmin = User::create([
            'name' => 'JUANET Super Administrator',
            'email' => 'juanetsolutions@gmail.com',
            'password' => Hash::make('JuanetSecurePass123!'),
            'status' => 'active',
        ]);

        // 6. Create organization membership
        $organization->members()->create([
            'user_id' => $superAdmin->id,
            'is_owner' => true,
            'status' => 'active',
        ]);

        // 7. Assign Super Administrator role to User for the Organization
        $superAdmin->roles()->attach($roleInstances['super-administrator']->id, [
            'id' => (string) Str::uuid7(),
            'organization_id' => $organization->id
        ]);
    }
}
