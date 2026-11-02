<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_no' => 'E'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'job_title' => fake()->jobTitle(),
            'work_email' => fake()->unique()->safeEmail(),
            'hire_date' => fake()->dateTimeBetween('-6 years', '-2 months')->format('Y-m-d'),
            'status' => 'active',
        ];
    }
}
