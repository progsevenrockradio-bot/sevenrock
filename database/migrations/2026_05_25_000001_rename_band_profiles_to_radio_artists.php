<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('band_profiles') && ! Schema::hasTable('radio_artists')) {
            Schema::rename('band_profiles', 'radio_artists');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('radio_artists') && ! Schema::hasTable('band_profiles')) {
            Schema::rename('radio_artists', 'band_profiles');
        }
    }
};
