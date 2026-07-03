<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationMemberFactory extends Factory
{
    protected $model = OrganizationMember::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'is_owner' => false,
            'status' => 'active',
            'version' => 1,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
