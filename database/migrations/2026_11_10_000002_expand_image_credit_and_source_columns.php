<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Amplía las columnas de crédito de imagen a VARCHAR(500) para albergar atribuciones
     * completas con autor, repositorio y licencia sin truncamientos.
     */
    public function up(): void
    {
        // 1. Ampliar posts.image_source de varchar(30) a varchar(500)
        if (Schema::hasTable('posts') && Schema::hasColumn('posts', 'image_source')) {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `posts` MODIFY `image_source` VARCHAR(500) NULL DEFAULT NULL");
            } else {
                Schema::table('posts', function (Blueprint $table) {
                    $table->string('image_source', 500)->nullable()->change();
                });
            }
        }

        // 2. Ampliar email_post_sanitized_meta.image_credit de varchar(255) a varchar(500)
        if (Schema::hasTable('email_post_sanitized_meta') && Schema::hasColumn('email_post_sanitized_meta', 'image_credit')) {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `email_post_sanitized_meta` MODIFY `image_credit` VARCHAR(500) NULL DEFAULT NULL");
            } else {
                Schema::table('email_post_sanitized_meta', function (Blueprint $table) {
                    $table->string('image_credit', 500)->nullable()->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     * Por seguridad estricta y siguiendo la directriz explícita del proyecto:
     * la reversión nunca debe reducir automáticamente las columnas si eso pudiera
     * truncar o corromper datos de atribución ya existentes.
     */
    public function down(): void
    {
        // Intencionadamente no-op para evitar pérdida de datos por truncado
    }
};
