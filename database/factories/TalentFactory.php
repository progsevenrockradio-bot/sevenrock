<?php

namespace Database\Factories;

use App\Models\Talent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class TalentFactory extends Factory
{
    protected $model = Talent::class;

    public function definition(): array
    {
        return [
            'band_name'           => fake()->unique()->company() . ' ' . fake()->unique()->numberBetween(1000, 999999),
            'email'               => fake()->unique()->safeEmail(),
            'password'            => Hash::make('Secret123!'),
            'plan'                => 'free',
            'subscription_status' => 'pending',
            'country'             => 'Venezuela',
            'is_hidden'           => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => 'active',
            'is_hidden'           => false,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => true,
        ]);
    }

    public function withReferral(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'referred_by_code' => $code,
        ]);
    }
}
