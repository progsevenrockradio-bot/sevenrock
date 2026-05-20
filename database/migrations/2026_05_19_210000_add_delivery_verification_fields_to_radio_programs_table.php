<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('radio_programs', function (Blueprint $table): void {
            if (! Schema::hasColumn('radio_programs', 'radioboss_status')) {
                $table->string('radioboss_status', 32)->nullable()->after('enviado_radioboss');
            }

            if (! Schema::hasColumn('radio_programs', 'radioboss_verified_at')) {
                $table->timestamp('radioboss_verified_at')->nullable()->after('radioboss_status');
            }

            if (! Schema::hasColumn('radio_programs', 'radioboss_last_error')) {
                $table->text('radioboss_last_error')->nullable()->after('radioboss_verified_at');
            }

            if (! Schema::hasColumn('radio_programs', 'radioboss_metadata')) {
                $table->json('radioboss_metadata')->nullable()->after('radioboss_last_error');
            }

            if (! Schema::hasColumn('radio_programs', 'delivery_status')) {
                $table->string('delivery_status', 32)->nullable()->after('sync_archive_org');
            }

            if (! Schema::hasColumn('radio_programs', 'delivery_verified_at')) {
                $table->timestamp('delivery_verified_at')->nullable()->after('delivery_status');
            }

            if (! Schema::hasColumn('radio_programs', 'delivery_last_error')) {
                $table->text('delivery_last_error')->nullable()->after('delivery_verified_at');
            }

            if (! Schema::hasColumn('radio_programs', 'delivery_metadata')) {
                $table->json('delivery_metadata')->nullable()->after('delivery_last_error');
            }

            if (! Schema::hasColumn('radio_programs', 'archive_org_verified_at')) {
                $table->timestamp('archive_org_verified_at')->nullable()->after('archive_org_uploaded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('radio_programs', function (Blueprint $table): void {
            foreach ([
                'radioboss_status',
                'radioboss_verified_at',
                'radioboss_last_error',
                'radioboss_metadata',
                'delivery_status',
                'delivery_verified_at',
                'delivery_last_error',
                'delivery_metadata',
                'archive_org_verified_at',
            ] as $column) {
                if (Schema::hasColumn('radio_programs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
