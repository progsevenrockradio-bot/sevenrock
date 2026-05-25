<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('radio_artists')) {
            return;
        }

        Schema::table('radio_artists', function (Blueprint $table): void {
            if (! Schema::hasColumn('radio_artists', 'founded_date')) {
                $table->date('founded_date')->nullable()->after('image_path');
            }

            if (! Schema::hasColumn('radio_artists', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('founded_date');
            }

            if (! Schema::hasColumn('radio_artists', 'country')) {
                $table->string('country')->nullable()->after('logo_path');
            }

            if (! Schema::hasColumn('radio_artists', 'genre')) {
                $table->string('genre')->nullable()->after('country');
            }

            if (! Schema::hasColumn('radio_artists', 'members_count')) {
                $table->unsignedInteger('members_count')->nullable()->after('genre');
            }

            if (! Schema::hasColumn('radio_artists', 'status')) {
                $table->string('status')->nullable()->after('members_count');
            }

            if (! Schema::hasColumn('radio_artists', 'labels')) {
                $table->text('labels')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('radio_artists')) {
            return;
        }

        Schema::table('radio_artists', function (Blueprint $table): void {
            foreach (['founded_date', 'logo_path', 'country', 'genre', 'members_count', 'status', 'labels'] as $column) {
                if (Schema::hasColumn('radio_artists', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
