<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Support\TextList;

class NormalizeEventContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:normalize-content';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convierte los campos content de texto plano en Eventos a un array de forma segura.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando normalización de contenido de eventos...');

        $events = Event::all();
        $changed = 0;
        $skipped = 0;

        foreach ($events as $event) {
            $rawContent = $event->getRawOriginal('content');
            
            // Revisa si es un JSON (array) válido ya guardado
            $decoded = json_decode((string)$rawContent, true);
            
            if (is_array($decoded)) {
                $skipped++;
            } else {
                // Era un string que no es JSON (o JSON inválido), lo normalizamos
                $event->content = TextList::toArray($rawContent);
                $event->save();
                $changed++;
            }
        }

        $this->info("Proceso completado. Modificados: {$changed}. Ya estaban correctos (saltados): {$skipped}.");
    }
}
