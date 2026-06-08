<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanTestDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:clean-test-data {--force : Force the operation to run without prompting for confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Borrador de datos de prueba para limpiar el sitio (conserva los Posts, Programas de Radio y usuarios Administradores)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('¿Estás seguro de que deseas eliminar TODOS los datos de prueba? Esta acción borrará eventos, álbumes, canciones, vídeos, imágenes de galería, productos, talentos y campañas de outreach. Los Posts, Programas de Radio y usuarios Administradores se conservarán.')) {
            $this->info('Operación cancelada.');
            return 0;
        }

        $this->info('Iniciando la limpieza de datos de prueba...');

        // Desactivar temporalmente las restricciones de clave foránea para permitir truncados limpios
        Schema::disableForeignKeyConstraints();

        // 1. Limpieza de Talentos y datos asociados
        $this->cleanTable('talent_interactions');
        $this->cleanTable('talent_media');
        $this->cleanTable('talent_albums');
        $this->cleanTable('talent_subscriptions');
        $this->cleanTable('talents');

        // 2. Limpieza de discografía, eventos, vídeos, galería y productos
        $this->cleanTable('songs');
        $this->cleanTable('albums');
        $this->cleanTable('events');
        $this->cleanTable('videos');
        $this->cleanTable('gallery_images');
        $this->cleanTable('products');

        // 3. Limpieza de interacciones generales e historial
        $this->cleanTable('comments');
        $this->cleanTable('player_favorites');
        $this->cleanTable('post_reactions');
        $this->cleanTable('audit_logs');
        $this->cleanTable('play_history');

        // 4. Limpieza de Outreach (contactos y campañas de prueba)
        $this->cleanTable('outreach_logs');
        $this->cleanTable('outreach_campaigns');
        $this->cleanTable('band_contacts');

        // 5. Limpieza de radio_artists (perfiles de radio antiguos)
        $this->cleanTable('radio_artists');

        // 6. Eliminar usuarios que no sean administradores
        if (Schema::hasTable('users')) {
            $count = DB::table('users')->where('is_admin', false)->delete();
            $this->line("- users (no administradores): Eliminados {$count} registros.");
        }

        Schema::enableForeignKeyConstraints();

        $this->info('¡Limpieza completada con éxito! La base de datos está limpia de datos de prueba.');

        return 0;
    }

    /**
     * Clean a specific table by truncating it.
     */
    private function cleanTable(string $table): void
    {
        if (Schema::hasTable($table)) {
            $count = DB::table($table)->count();
            DB::table($table)->truncate();
            $this->line("- {$table}: Truncada con éxito (se eliminaron {$count} registros).");
        } else {
            $this->warn("- {$table}: La tabla no existe.");
        }
    }
}
