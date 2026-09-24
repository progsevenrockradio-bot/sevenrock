<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Talent;
use Illuminate\Support\Facades\Storage;

class AdminCleanupCommand extends Command
{
    protected $signature = 'admin:cleanup {--list-users : Solo listar los usuarios} {--clean-bands : Borrar bandas de prueba y archivos} {--clean-users : Borrar todos los usuarios excepto los especificados}';
    protected $description = 'Lista usuarios o borra bandas de prueba y sus archivos exceptuando a Aetherfrost.';

    public function handle()
    {
        if ($this->option('list-users')) {
            $this->listUsers();
            return 0;
        }

        if ($this->option('clean-bands')) {
            $this->cleanBands();
            return 0;
        }

        if ($this->option('clean-users')) {
            $this->cleanUsers();
            return 0;
        }

        $this->info("Por favor, usa --list-users, --clean-bands o --clean-users.");
        return 0;
    }

    private function listUsers()
    {
        $users = User::all();
        $this->info("================ LISTADO DE USUARIOS ================");
        $this->info(str_pad("ROL", 10) . " | " . str_pad("ID", 4) . " | " . str_pad("NOMBRE", 30) . " | EMAIL");
        $this->info(str_repeat("-", 80));
        
        foreach ($users as $u) {
            $role = method_exists($u, 'hasAdminAccess') && $u->hasAdminAccess() ? 'ADMIN' : 'USER';
            $this->line(str_pad($role, 10) . " | " . str_pad((string)$u->id, 4) . " | " . str_pad(substr($u->name, 0, 30), 30) . " | " . $u->email);
        }
        $this->info("=====================================================");
    }

    private function cleanBands()
    {
        $this->info("Iniciando borrado de bandas de prueba (Muro del Rock)...");
        
        // Conservar Aetherfrost
        $talents = Talent::where('band_name', 'not like', '%aetherfrost%')->get();
        $deletedCount = 0;

        foreach ($talents as $talent) {
            $this->line("Borrando banda: " . $talent->band_name);
            
            if ($talent->logo) {
                Storage::disk('public')->delete($talent->logo);
            }
            
            foreach ($talent->media as $media) {
                Storage::disk('public')->delete($media->path);
                $media->delete();
            }
            
            foreach ($talent->albums as $album) {
                if ($album->cover_image_path) {
                    Storage::disk('public')->delete($album->cover_image_path);
                }
                $album->delete();
            }
            
            $talent->forceDelete();
            $deletedCount++;
        }

        $this->info("Limpieza terminada. Bandas borradas: {$deletedCount}.");
    }

    private function cleanUsers()
    {
        $this->info("Iniciando borrado de usuarios...");

        $keepEmails = [
            'prog.sevenrockradio@gmail.com',
            'press.sevenrockradio@gmail.com',
            'aetherfrost002@gmail.com',
        ];

        $usersToDelete = User::whereNotIn('email', $keepEmails)->get();
        $deletedCount = 0;

        foreach ($usersToDelete as $user) {
            $this->line("Borrando usuario: " . $user->email);
            // También podemos borrar el talento asociado si existe y no queremos que quede huérfano, 
            // pero como ya corriste clean-bands, solo borramos el usuario.
            $user->delete();
            $deletedCount++;
        }

        $this->info("Limpieza terminada. Usuarios borrados: {$deletedCount}.");
    }
}

