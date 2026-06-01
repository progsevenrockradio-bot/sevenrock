<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('master_programs')) {
            return;
        }

        Schema::table('master_programs', function (Blueprint $table): void {
            if (! Schema::hasColumn('master_programs', 'program_code')) {
                $table->string('program_code', 12)->nullable()->unique()->after('nombre');
            }

            if (! Schema::hasColumn('master_programs', 'code_prefix')) {
                $table->string('code_prefix', 12)->nullable()->after('program_code');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('master_programs')) {
            return;
        }

        Schema::table('master_programs', function (Blueprint $table): void {
            if (Schema::hasColumn('master_programs', 'program_code')) {
                $table->dropUnique(['program_code']);
                $table->dropColumn('program_code');
            }

            if (Schema::hasColumn('master_programs', 'code_prefix')) {
                $table->dropColumn('code_prefix');
            }
        });
    }
};
