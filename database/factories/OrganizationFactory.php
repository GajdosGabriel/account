<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'legal_name' => $name.', s. r. o.',
            'legal_form' => 'sro',
            'ico' => (string) fake()->unique()->numberBetween(10000000, 99999999),
            'dic' => (string) fake()->numberBetween(1000000000, 9999999999),
            'vat_mode' => 'non_payer',
            'street' => fake()->streetName(),
            'street_no' => (string) fake()->numberBetween(1, 99),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'country' => 'SK',
            'email' => fake()->companyEmail(),
            'billing_email' => fake()->companyEmail(),
            'currency' => 'EUR',
            'payment_terms_days' => 14,
            'status' => 'active',
        ];
    }
}
