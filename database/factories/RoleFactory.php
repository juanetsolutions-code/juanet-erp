<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = $this->faker->word();
        return [
            'id' => (string) Str::uuid7(),
            'organization_id' => null, // Global by default, can override with Tenant ID
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'is_system' => false,
            'version' => 1,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
