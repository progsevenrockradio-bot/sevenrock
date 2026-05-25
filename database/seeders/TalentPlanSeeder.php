<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TalentPlanSeeder extends Seeder
{
    public function run(): void
    {
        // Los planes son source-of-truth en config/payment.php.
        // Este seeder existe para mantener la estructura del proyecto y
        // facilitar futuras persistencias si se añade una tabla de planes.
    }
}
