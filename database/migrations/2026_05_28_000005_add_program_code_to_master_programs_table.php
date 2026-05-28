<?php

declare(strict_types=1);

use App\Models\MasterProgram;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
                $table->string('program_code', 12)->nullable()->after('nombre');
            }

            if (! Schema::hasColumn('master_programs', 'code_prefix')) {
                $table->string('code_prefix', 12)->nullable()->after('program_code');
            }
        });

        $seen = [];
        DB::table('master_programs')
            ->orderBy('id')
            ->select(['id', 'nombre', 'program_code', 'code_prefix'])
            ->get()
            ->each(function ($program) use (&$seen): void {
                $base = MasterProgram::normalizeProgramCode((string) ($program->code_prefix ?: $program->nombre ?: 'PROGRAMA'));
                if ($base === '') {
                    $base = 'PROGRAMA';
                }

                $candidate = $base;
                if (in_array($candidate, $seen, true)) {
                    $candidate = $this->uniqueFromSeen($base, $seen);
                }

                $seen[] = $candidate;

                DB::table('master_programs')->where('id', $program->id)->update([
                    'program_code' => $candidate,
                    'code_prefix' => $base,
                ]);
            });

        Schema::table('master_programs', function (Blueprint $table): void {
            $table->unique('program_code', 'master_programs_program_code_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('master_programs')) {
            return;
        }

        Schema::table('master_programs', function (Blueprint $table): void {
            $table->dropUnique('master_programs_program_code_unique');

            foreach (['program_code', 'code_prefix'] as $column) {
                if (Schema::hasColumn('master_programs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * @param array<int, string> $seen
     */
    private function uniqueFromSeen(string $base, array $seen): string
    {
        $base = substr($base, 0, 12);

        for ($i = 1; $i <= 99; $i++) {
            $suffix = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $prefixLength = max(0, 12 - strlen($suffix));
            $candidate = substr($base, 0, $prefixLength) . $suffix;
            if (! in_array($candidate, $seen, true)) {
                return $candidate;
            }
        }

        return substr($base, 0, 10) . '99';
    }
};
