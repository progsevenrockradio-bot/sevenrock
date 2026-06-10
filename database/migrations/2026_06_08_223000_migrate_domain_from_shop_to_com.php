<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update master_programs table
        if (Schema::hasTable('master_programs')) {
            foreach (['caratula_url', 'live_image_url'] as $column) {
                if (Schema::hasColumn('master_programs', $column)) {
                    DB::table('master_programs')
                        ->where($column, 'like', '%sevenrockradio.shop%')
                        ->update([
                            $column => DB::raw("REPLACE({$column}, 'sevenrockradio.shop', 'sevenrockradio.com')"),
                        ]);
                }
            }
        }

        // 2. Update radio_programs table
        if (Schema::hasTable('radio_programs')) {
            foreach (['caratula_programa', 'imagen_episodio', 'imagen_invitado', 'live_image_url'] as $column) {
                if (Schema::hasColumn('radio_programs', $column)) {
                    DB::table('radio_programs')
                        ->where($column, 'like', '%sevenrockradio.shop%')
                        ->update([
                            $column => DB::raw("REPLACE({$column}, 'sevenrockradio.shop', 'sevenrockradio.com')"),
                        ]);
                }
            }
        }

        // 3. Update outreach_templates table
        if (Schema::hasTable('outreach_templates')) {
            if (Schema::hasColumn('outreach_templates', 'body')) {
                DB::table('outreach_templates')
                    ->where('body', 'like', '%sevenrockradio.shop%')
                    ->update([
                        'body' => DB::raw("REPLACE(body, 'sevenrockradio.shop', 'sevenrockradio.com')"),
                    ]);
            }
        }

        // 4. Update theme_settings table
        if (Schema::hasTable('theme_settings')) {
            if (Schema::hasColumn('theme_settings', 'notification_copy_email')) {
                DB::table('theme_settings')
                    ->where('notification_copy_email', 'like', '%sevenrockradio.shop%')
                    ->update([
                        'notification_copy_email' => DB::raw("REPLACE(notification_copy_email, 'sevenrockradio.shop', 'sevenrockradio.com')"),
                    ]);
            }
        }

        // 5. Update radio_artists (band_profiles) table
        if (Schema::hasTable('radio_artists')) {
            foreach (['image_path', 'logo_path', 'biography', 'editorial_summary'] as $column) {
                if (Schema::hasColumn('radio_artists', $column)) {
                    DB::table('radio_artists')
                        ->where($column, 'like', '%sevenrockradio.shop%')
                        ->update([
                            $column => DB::raw("REPLACE({$column}, 'sevenrockradio.shop', 'sevenrockradio.com')"),
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        // 1. Revert master_programs table
        if (Schema::hasTable('master_programs')) {
            foreach (['caratula_url', 'live_image_url'] as $column) {
                if (Schema::hasColumn('master_programs', $column)) {
                    DB::table('master_programs')
                        ->where($column, 'like', '%sevenrockradio.com%')
                        ->update([
                            $column => DB::raw("REPLACE({$column}, 'sevenrockradio.com', 'sevenrockradio.shop')"),
                        ]);
                }
            }
        }

        // 2. Revert radio_programs table
        if (Schema::hasTable('radio_programs')) {
            foreach (['caratula_programa', 'imagen_episodio', 'imagen_invitado', 'live_image_url'] as $column) {
                if (Schema::hasColumn('radio_programs', $column)) {
                    DB::table('radio_programs')
                        ->where($column, 'like', '%sevenrockradio.com%')
                        ->update([
                            $column => DB::raw("REPLACE({$column}, 'sevenrockradio.com', 'sevenrockradio.shop')"),
                        ]);
                }
            }
        }

        // 3. Revert outreach_templates table
        if (Schema::hasTable('outreach_templates')) {
            if (Schema::hasColumn('outreach_templates', 'body')) {
                DB::table('outreach_templates')
                    ->where('body', 'like', '%sevenrockradio.com%')
                    ->update([
                        'body' => DB::raw("REPLACE(body, 'sevenrockradio.com', 'sevenrockradio.shop')"),
                    ]);
            }
        }

        // 4. Revert theme_settings table
        if (Schema::hasTable('theme_settings')) {
            if (Schema::hasColumn('theme_settings', 'notification_copy_email')) {
                DB::table('theme_settings')
                    ->where('notification_copy_email', 'like', '%sevenrockradio.com%')
                    ->update([
                        'notification_copy_email' => DB::raw("REPLACE(notification_copy_email, 'sevenrockradio.com', 'sevenrockradio.shop')"),
                    ]);
            }
        }

        // 5. Revert radio_artists table
        if (Schema::hasTable('radio_artists')) {
            foreach (['image_path', 'logo_path', 'biography', 'editorial_summary'] as $column) {
                if (Schema::hasColumn('radio_artists', $column)) {
                    DB::table('radio_artists')
                        ->where($column, 'like', '%sevenrockradio.com%')
                        ->update([
                            $column => DB::raw("REPLACE({$column}, 'sevenrockradio.com', 'sevenrockradio.shop')"),
                        ]);
                }
            }
        }
    }
};
