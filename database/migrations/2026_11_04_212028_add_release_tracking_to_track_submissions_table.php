<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo estable maqueta → lanzamiento, fecha de primera publicación
     * efectiva y estado del aviso de publicación (reintentable por separado).
     *
     * Los registros históricos NO se vinculan: new_release_id queda NULL.
     */
    public function up(): void
    {
        Schema::table('track_submissions', function (Blueprint $table) {
            $table->foreignId('new_release_id')
                ->nullable()
                ->constrained('new_releases')
                ->nullOnDelete();

            $table->timestamp('published_at')->nullable();

            // null = sin aviso | pending = en curso/indeterminado | sent | failed (reintentable) | unknown | skipped
            $table->string('publication_notice_status', 20)->nullable()->index();
            $table->unsignedSmallInteger('publication_notice_attempts')->default(0);
            $table->timestamp('publication_notice_sent_at')->nullable();
            $table->text('publication_notice_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('track_submissions', function (Blueprint $table) {
            $table->dropForeign(['new_release_id']);
            $table->dropIndex(['publication_notice_status']);
            $table->dropColumn([
                'new_release_id',
                'published_at',
                'publication_notice_status',
                'publication_notice_attempts',
                'publication_notice_sent_at',
                'publication_notice_error',
            ]);
        });
    }
};
