<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_settings', function (Blueprint $table): void {
            $table->json('hero_slides')->nullable()->after('hero_slide_secondary_path');
        });

        $settings = DB::table('theme_settings')->first();
        if ($settings) {
            $slides = [];

            if (! empty($settings->hero_slide_primary_path)) {
                $slides[] = ['image' => $settings->hero_slide_primary_path];
            }

            if (! empty($settings->hero_slide_secondary_path)) {
                $slides[] = ['image' => $settings->hero_slide_secondary_path];
            }

            if (! empty($slides)) {
                DB::table('theme_settings')->update([
                    'hero_slides' => json_encode($slides),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table): void {
            $table->dropColumn('hero_slides');
        });
    }
};
