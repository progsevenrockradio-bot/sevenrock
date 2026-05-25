<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('talents')) {
            return;
        }

        Schema::table('talents', function (Blueprint $table): void {
            if (! Schema::hasColumn('talents', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }

            foreach ([
                'instagram_url',
                'youtube_url',
                'tiktok_url',
                'spotify_url',
                'website_url',
            ] as $column) {
                if (! Schema::hasColumn('talents', $column)) {
                    $table->string($column)->nullable()->after('bio');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('talents')) {
            return;
        }

        Schema::table('talents', function (Blueprint $table): void {
            foreach ([
                'email_verified_at',
                'instagram_url',
                'youtube_url',
                'tiktok_url',
                'spotify_url',
                'website_url',
            ] as $column) {
                if (Schema::hasColumn('talents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
