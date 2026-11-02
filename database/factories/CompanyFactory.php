<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'country_code' => 'ID',
            'base_currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
        ];
    }

    public function generic(): static
    {
        return $this->state(['country_code' => 'GB', 'base_currency' => 'GBP', 'timezone' => 'Europe/London', 'locale' => 'en']);
    }
}
