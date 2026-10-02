<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\AirplayNoticeMail;
use App\Models\AirplayNotice;
use App\Models\AirplaySchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAirplayNoticesCommand extends Command
{
    protected $signature = 'airplay:send-notices {--semana= : Filtrar por semana especifica (ej: 2026-W40)} {--force : Reenviar aunque ya se hayan enviado}';

    protected $description = 'Envia notificaciones por email a sellos/productoras y artistas de la programacion de la semana';

    public function handle(): int
    {
        $semana = $this->option('semana');
        $force  = (bool) $this->option('force');

        $query = AirplaySchedule::query()
            ->where('es_novedad', true)
            ->where(function ($q) {
                $q->whereNotNull('email_sello')
                  ->orWhereNotNull('email_artista')
                  ->orWhereNotNull('contacto_email');
            });

        if (!empty($semana)) {
            $query->where('semana', strtoupper((string)$semana));
        }

        if (!$force) {
            $query->whereNull('notificado_at');
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            $this->info('No hay avisos pendientes de envio.');
            return Command::SUCCESS;
        }

        $this->info(sprintf('Procesando %d registros de programacion...', $schedules->count()));
        $enviadosCount = 0;
        $erroresCount  = 0;

        foreach ($schedules as $schedule) {
            $destinatarios = [];

            $emailSello = $schedule->email_sello ?: $schedule->contacto_email;
            if (!empty($emailSello)) {
                $destinatarios[$emailSello] = 'productora';
            }

            if (!empty($schedule->email_artista) && !isset($destinatarios[$schedule->email_artista])) {
                $destinatarios[$schedule->email_artista] = 'artista';
            }

            if (empty($destinatarios)) {
                continue;
            }

            $scheduleSuccess = true;

            foreach ($destinatarios as $email => $tipo) {
                try {
                    $notice = AirplayNotice::firstOrCreate(
                        [
                            'airplay_schedule_id' => $schedule->id,
                            'email'               => $email,
                        ],
                        [
                            'tipo'   => $tipo,
                            'estado' => 'pendiente',
                        ]
                    );

                    if (!$force && $notice->estado === 'enviado') {
                        continue;
                    }

                    Mail::to($email)->send(new AirplayNoticeMail($schedule, $tipo));

                    $notice->update([
                        'estado'     => 'enviado',
                        'enviado_en' => now(),
                        'error'      => null,
                    ]);

                    $enviadosCount++;
                    $this->line(sprintf('  [OK] Email a %s (%s): %s - %s', $email, $tipo, $schedule->artista, $schedule->titulo));
                } catch (Throwable $e) {
                    $scheduleSuccess = false;
                    $erroresCount++;
                    Log::error('Error enviando aviso airplay: ' . $e->getMessage(), [
                        'schedule_id' => $schedule->id,
                        'email'       => $email,
                    ]);

                    AirplayNotice::updateOrCreate(
                        [
                            'airplay_schedule_id' => $schedule->id,
                            'email'               => $email,
                        ],
                        [
                            'tipo'   => $tipo,
                            'estado' => 'error',
                            'error'  => $e->getMessage(),
                        ]
                    );

                    $this->error(sprintf('  [FAIL] Error enviando a %s: %s', $email, $e->getMessage()));
                }
            }

            if ($scheduleSuccess) {
                $schedule->update([
                    'notificado_at'   => now(),
                    'avisos_enviados' => $schedule->avisos_enviados + 1,
                ]);
            }
        }

        $this->info(sprintf('Proceso finalizado. Enviados: %d, Errores: %d', $enviadosCount, $erroresCount));

        return Command::SUCCESS;
    }
}
