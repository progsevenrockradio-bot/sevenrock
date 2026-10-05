<?php

namespace Database\Factories;

use App\Models\PromoterCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromoterCodeFactory extends Factory
{
    protected $model = PromoterCode::class;

    public function definition(): array
    {
        return [
            'code'        => PromoterCode::generateUniqueCode(),
            'owner_name'  => fake()->name(),
            'owner_email' => fake()->unique()->safeEmail(),
            'owner_type'  => 'conductor',
            'max_bands'   => PromoterCode::DEFAULT_MAX_BANDS,
            'is_archived' => false,
        ];
    }
}
