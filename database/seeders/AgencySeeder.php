<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Agency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AgencySeeder extends Seeder
{
    public function run(): void
    {
        Agency::query()->updateOrCreate(
            ['email' => 'via-agency@sevenrockradio.com'],
            [
                'name' => 'Via Agency',
                'slug' => 'via-agency',
                'password' => Hash::make('password'),
                'logo_path' => 'assets/lucille/logo_share.png', // Logo de prueba existente
                'website_url' => 'https://via-agency.example.com',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }
}
