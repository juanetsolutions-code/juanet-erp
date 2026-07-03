<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('Secret123!'),
            'remember_token' => Str::random(10),
            'phone' => $this->faker->phoneNumber(),
            'status' => 'active',
            'version' => 1,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
