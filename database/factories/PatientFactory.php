<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'file_number' => 'PAT-' . strtoupper(Str::random(6)),
            'full_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'gender' => 'male',
            'dob' => fake()->date(),
            'status' => 'active',
        ];
    }
}
