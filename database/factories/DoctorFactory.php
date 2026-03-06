<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Dr. ' . $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'specialty' => $this->faker->randomElement(['Dermatology', 'Neurology', 'Psychiatry', 'General Practice']),
            'password' => Hash::make('password'),
            'bio' => $this->faker->paragraph(),
        ];
    }
}
