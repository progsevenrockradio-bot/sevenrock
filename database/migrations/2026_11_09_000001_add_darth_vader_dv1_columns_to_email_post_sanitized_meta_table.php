<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('email_post_sanitized_meta')) {
            Schema::table('email_post_sanitized_meta', function (Blueprint $table) {
                if (! Schema::hasColumn('email_post_sanitized_meta', 'batch_id')) {
                    $table->string('batch_id', 100)->nullable()->index()->after('source_message_id');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'element_id')) {
                    $table->string('element_id', 100)->nullable()->index()->after('batch_id');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'payload_hash')) {
                    $table->string('payload_hash', 64)->nullable()->index()->after('element_id');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'event_date')) {
                    $table->string('event_date', 50)->nullable()->after('musical_release_date');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'artist_name')) {
                    $table->string('artist_name', 255)->nullable()->after('event_date');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'sources')) {
                    $table->text('sources')->nullable()->after('artist_name');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'image_credit')) {
                    $table->string('image_credit', 255)->nullable()->after('sources');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'image_alt')) {
                    $table->string('image_alt', 500)->nullable()->after('image_credit');
                }
                if (! Schema::hasColumn('email_post_sanitized_meta', 'corrects_element_id')) {
                    $table->string('corrects_element_id', 100)->nullable()->index()->after('superseded_by_message_id');
                }
            });

            // Añadir restricción única conjunta tras comprobar posibles duplicados
            $this->addUniqueIndexSafely();
        }
    }

    protected function addUniqueIndexSafely(): void
    {
        // Comprobar si ya existe el índice
        $indexExists = false;
        try {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('email_post_sanitized_meta');
            $indexExists = isset($indexes['uniq_email_meta_batch_element']);
        } catch (\Throwable) {
            // Fallback si doctrine no está disponible
            $indexExists = false;
        }

        if (! $indexExists) {
            // Verificar si existen duplicados previos no nulos
            $duplicates = DB::table('email_post_sanitized_meta')
                ->select('batch_id', 'element_id', DB::raw('count(*) as c'))
                ->whereNotNull('batch_id')
                ->whereNotNull('element_id')
                ->groupBy('batch_id', 'element_id')
                ->having('c', '>', 1)
                ->get();

            if ($duplicates->isEmpty()) {
                Schema::table('email_post_sanitized_meta', function (Blueprint $table) {
                    $table->unique(['batch_id', 'element_id'], 'uniq_email_meta_batch_element');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('email_post_sanitized_meta')) {
            Schema::table('email_post_sanitized_meta', function (Blueprint $table) {
                try {
                    $table->dropUnique('uniq_email_meta_batch_element');
                } catch (\Throwable) {
                    // Ignorar si no existe
                }

                $columnsToDrop = [];
                foreach (['batch_id', 'element_id', 'payload_hash', 'event_date', 'artist_name', 'sources', 'image_credit', 'image_alt', 'corrects_element_id'] as $column) {
                    if (Schema::hasColumn('email_post_sanitized_meta', $column)) {
                        $columnsToDrop[] = $column;
                    }
                }
                if (! empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
