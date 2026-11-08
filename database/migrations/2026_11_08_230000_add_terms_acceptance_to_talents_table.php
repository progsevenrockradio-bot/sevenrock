<?php

declare(strict_types=1);

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
            if (! Schema::hasColumn('talents', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
            }

            if (! Schema::hasColumn('talents', 'terms_version')) {
                $table->string('terms_version', 50)->nullable()->after('terms_accepted_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('talents')) {
            return;
        }

        Schema::table('talents', function (Blueprint $table): void {
            if (Schema::hasColumn('talents', 'terms_version')) {
                $table->dropColumn('terms_version');
            }

            if (Schema::hasColumn('talents', 'terms_accepted_at')) {
                $table->dropColumn('terms_accepted_at');
            }
        });
    }
};

