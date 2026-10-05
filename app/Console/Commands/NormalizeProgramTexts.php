<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MasterProgram;
use App\Models\MasterProgramEmision;
use App\Support\ProgramTextNormalizer;

class NormalizeProgramTexts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'programs:normalize-texts {--apply : Apply the changes to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Normalize host and description texts for programs and emissions to prevent duplication in UI.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apply = $this->option('apply');
        
        $this->info("Starting normalization" . ($apply ? " (APPLY MODE)" : " (SIMULATION MODE)"));
        
        $programsRevisados = 0;
        $programsCambiados = 0;
        
        $emisionesRevisadas = 0;
        $emisionesCambiadas = 0;

        // 1. Programs
        $programs = MasterProgram::all();
        foreach ($programs as $program) {
            $programsRevisados++;
            $originalHost = $program->host;
            $originalDesc = $program->description;
            
            $newHost = ProgramTextNormalizer::limpiarConductor($originalHost);
            $newDesc = ProgramTextNormalizer::limpiarDescripcion($originalDesc);
            
            $changed = false;
            
            if ($originalHost !== $newHost) {
                $changed = true;
                $this->line("<comment>Program ID {$program->id} ({$program->nombre}) - HOST CHANGED:</comment>");
                $this->line("  OLD: {$originalHost}");
                $this->line("  NEW: {$newHost}");
                $program->host = $newHost;
            }
            
            if ($originalDesc !== $newDesc) {
                $changed = true;
                $this->line("<comment>Program ID {$program->id} ({$program->nombre}) - DESC CHANGED:</comment>");
                $this->line("  OLD: " . str_replace("\n", '\n', $originalDesc));
                $this->line("  NEW: " . str_replace("\n", '\n', $newDesc));
                $program->description = $newDesc;
            }
            
            if ($changed) {
                $programsCambiados++;
                if ($apply) {
                    $program->save();
                }
            }
        }
        
        // 2. Emisiones (only description, no host field in emision usually but if there is, we will check. Wait, MasterProgramEmision doesn't have a host field natively exposed in the prompt, prompt says "Recorre las emisiones/episodios con descripción y aplica también limpiarDescripcion.")
        $emisiones = MasterProgramEmision::whereNotNull('etiqueta')->orWhereNotNull('enlace')->get(); // we will fetch all just in case, but description is the field? Wait, MasterProgramEmision doesn't have a description field? Let's check. Ah! The prompt says "Recorre las emisiones/episodios con descripción y aplica también limpiarDescripcion." Wait, the episodes are fetched from Archive.org. Do emissions have a description? Let's check `MasterProgramEmision` model columns. I will just check if `description` column exists or maybe I should use `Schema::hasColumn()`. Or maybe the user meant "emisiones/episodios" as another table? Wait! Emisiones might not have descriptions, but maybe `MasterProgramEmision` has `descripcion` or `description`. Let me read `Schema`. No wait, I don't know the exact column. The prompt says "Recorre las emisiones/episodios con descripción y aplica también limpiarDescripcion.". Let's assume it's `descripcion` or `description`.
        
        $hasDescriptionCol = \Illuminate\Support\Facades\Schema::hasColumn('master_program_emisiones', 'description');
        $hasDescripcionCol = \Illuminate\Support\Facades\Schema::hasColumn('master_program_emisiones', 'descripcion');
        $descCol = $hasDescriptionCol ? 'description' : ($hasDescripcionCol ? 'descripcion' : null);

        if ($descCol) {
            $allEmisiones = MasterProgramEmision::all();
            foreach ($allEmisiones as $emision) {
                $emisionesRevisadas++;
                $originalDesc = $emision->{$descCol};
                $newDesc = ProgramTextNormalizer::limpiarDescripcion($originalDesc);
                
                if ($originalDesc !== $newDesc) {
                    $emisionesCambiadas++;
                    $this->line("<comment>Emision ID {$emision->id} (Program ID {$emision->master_program_id}) - DESC CHANGED:</comment>");
                    $this->line("  OLD: " . str_replace("\n", '\n', $originalDesc));
                    $this->line("  NEW: " . str_replace("\n", '\n', $newDesc));
                    $emision->{$descCol} = $newDesc;
                    if ($apply) {
                        $emision->save();
                    }
                }
            }
        } else {
            // Check if there is an Episode model? The prompt says "emisiones/episodios". Archive.org episodes are not in DB. Maybe they meant MasterProgramEmision?
            $this->info("No description column found on master_program_emisiones.");
        }

        $this->info("--------------------------------------------------");
        $this->info("RESUMEN:");
        $this->info("Programas revisados: {$programsRevisados}");
        $this->info("Programas cambiados: {$programsCambiados}");
        $this->info("Emisiones revisadas: {$emisionesRevisadas}");
        $this->info("Emisiones cambiadas: {$emisionesCambiadas}");
        
        if (!$apply) {
            $this->info("Run with --apply to save these changes.");
        }
    }
}
