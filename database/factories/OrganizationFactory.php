<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        return [
            'id' => (string) Str::uuid7(),
            'name' => $name,
            'slug' => Str::slug($name),
            'domain' => $this->faker->unique()->domainName(),
            'status' => 'active',
            'version' => 1,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
