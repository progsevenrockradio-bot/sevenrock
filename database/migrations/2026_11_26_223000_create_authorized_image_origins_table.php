<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('authorized_image_origins')) {
            Schema::create('authorized_image_origins', function (Blueprint $table) {
                $table->id();
                $table->string('label', 150);
                $table->string('host', 255);
                $table->string('path_prefix', 255)->nullable();
                $table->boolean('require_https')->default(true);
                $table->boolean('is_active')->default(true);
                $table->string('scope', 40)->default('manual_editorial');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index(['host', 'is_active'], 'aio_host_active_idx');
                $table->index(['scope', 'is_active'], 'aio_scope_active_idx');
            });
        }

        // Sembrar el espacio propio de Cloudinary de Seven Rock Radio (cuenta dutx97jzy)
        // restringido estrictamente a rutas de imágenes de esa cuenta.
        $exists = DB::table('authorized_image_origins')
            ->where('host', 'res.cloudinary.com')
            ->where('path_prefix', '/dutx97jzy/image/upload/')
            ->exists();

        if (! $exists) {
            DB::table('authorized_image_origins')->insert([
                'label'         => 'Cloudinary Seven Rock Radio (dutx97jzy)',
                'host'          => 'res.cloudinary.com',
                'path_prefix'   => '/dutx97jzy/image/upload/',
                'require_https' => true,
                'is_active'     => true,
                'scope'         => 'manual_editorial',
                'notes'         => 'Espacio propio de Cloudinary autorizado exclusivamente para imágenes de la cuenta dutx97jzy en edición manual de noticias y efemérides.',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('authorized_image_origins');
    }
};

