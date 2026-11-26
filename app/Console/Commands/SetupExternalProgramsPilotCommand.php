<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExternalProgramSetting;
use App\Models\MarketingMailAccount;
use App\Models\MasterProgram;
use App\Models\MasterProgramDeliverySender;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SetupExternalProgramsPilotCommand extends Command
{
    protected $signature = 'external-programs:pilot-setup
                            {--status : Mostrar programas, buzones disponibles y configuración actual sin modificar nada}
                            {--program-id= : ID del programa maestro piloto (si se omite, se muestra la lista)}
                            {--mailbox= : Buzón receptor IMAP a guardar en external_program_settings}
                            {--sender= : Correo alternativo temporal a autorizar en el programa piloto (sin tocar email_notificacion)}
                            {--label=Piloto temporal : Etiqueta para el remitente alternativo}
                            {--since=now : Fecha/hora de inicio (now o Y-m-d H:i:s)}
                            {--deactivate-sender= : Desactivar un remitente alternativo temporal}';

    protected $description = 'Consulta o prepara la autorización temporal del piloto de recepción externa sin modificar los correos habituales de los productores';

    public function handle(): int
    {
        $tz = (string) config('app.timezone', 'Europe/Madrid');

        if ($this->option('deactivate-sender')) {
            $email = strtolower(trim((string) $this->option('deactivate-sender')));
            $count = MasterProgramDeliverySender::query()
                ->where('email', $email)
                ->update(['is_active' => false]);
            $this->info("Remitente temporal {$email} desactivado ({$count} registro(s)).");
            return self::SUCCESS;
        }

        if ($this->option('status') || ! $this->option('program-id')) {
            $this->line('=== CONFIGURACIÓN ACTUAL ===');
            $this->line('APP_URL: ' . (string) config('app.url'));
            $this->line('IMAP_USERNAME (.env): ' . (string) config('services.imap.username', '(vacío)'));
            $this->line('mailbox_email (DB): ' . (ExternalProgramSetting::getValue('mailbox_email') ?? '(no configurado)'));
            $this->line('telegram_chat_id (DB): ' . (ExternalProgramSetting::getValue('telegram_chat_id') ?? '(no configurado)'));
            $this->line('since_date (DB): ' . (ExternalProgramSetting::getValue('since_date') ?? '(no configurado)'));
            $this->line('EXTERNAL_PROGRAMS_TELEGRAM_BOT_TOKEN set: ' . (filled(env('EXTERNAL_PROGRAMS_TELEGRAM_BOT_TOKEN')) ? 'YES' : 'NO'));
            $this->line('TELEGRAM_BOT_TOKEN set: ' . (filled(env('TELEGRAM_BOT_TOKEN')) ? 'YES' : 'NO'));

            $this->line('');
            $this->line('=== CUENTAS IMAP (MarketingMailAccount) ===');
            $accounts = MarketingMailAccount::query()->orderBy('id')->get();
            if ($accounts->isEmpty()) {
                $this->line('  (ninguna)');
            } else {
                foreach ($accounts as $acc) {
                    $this->line(sprintf(
                        '  #%d %s | host=%s | active=%d | pass_set=%d',
                        $acc->id,
                        $acc->email,
                        $acc->imap_host ?: '(default)',
                        $acc->is_active ? 1 : 0,
                        filled($acc->imap_password) ? 1 : 0
                    ));
                }
            }

            $this->line('');
            $this->line('=== PROGRAMAS MAESTROS ACTIVOS (primeros 10) ===');
            foreach (MasterProgram::query()->where('activo', true)->orderBy('id')->limit(10)->get() as $p) {
                $senders = $p->deliverySenders()->get()->map(fn ($s) => $s->email . ($s->is_active ? '[ON]' : '[OFF]'))->implode(', ');
                $this->line(sprintf(
                    '  #%d %s | notif_recog=%d | alt_senders=[%s]',
                    $p->id,
                    $p->nombre,
                    $p->recognize_delivery_from_notification_email ? 1 : 0,
                    $senders
                ));
            }

            return self::SUCCESS;
        }

        $programId = (int) $this->option('program-id');
        $program = MasterProgram::query()->find($programId);
        if (! $program) {
            $this->error("No existe el programa maestro #{$programId}.");
            return self::FAILURE;
        }

        $mailbox = strtolower(trim((string) $this->option('mailbox')));
        if ($mailbox !== '') {
            if (! filter_var($mailbox, FILTER_VALIDATE_EMAIL)) {
                $this->error("Buzón inválido: {$mailbox}");
                return self::FAILURE;
            }
            ExternalProgramSetting::setValue('mailbox_email', $mailbox);
            $this->info("Buzón receptor guardado: {$mailbox}");
        }

        $sinceOpt = trim((string) $this->option('since'));
        if ($sinceOpt !== '') {
            $sinceCarbon = strtolower($sinceOpt) === 'now'
                ? now($tz)
                : Carbon::parse($sinceOpt, $tz);
            ExternalProgramSetting::setValue('since_date', $sinceCarbon->format('Y-m-d H:i:s'));
            $this->info('Fecha de inicio guardada: ' . $sinceCarbon->format('Y-m-d H:i:s') . " ({$tz})");
        }

        $sender = strtolower(trim((string) $this->option('sender')));
        if ($sender !== '') {
            if (! filter_var($sender, FILTER_VALIDATE_EMAIL)) {
                $this->error("Remitente inválido: {$sender}");
                return self::FAILURE;
            }
            $label = trim((string) $this->option('label')) ?: 'Piloto temporal';
            MasterProgramDeliverySender::query()->updateOrCreate(
                [
                    'master_program_id' => $program->id,
                    'email' => $sender,
                ],
                [
                    'label' => $label,
                    'is_active' => true,
                ]
            );
            $this->info("Remitente alternativo temporal autorizado en #{$program->id} ({$program->nombre}): {$sender}");
            $this->line('Correo habitual del productor (email_notificacion) conservado intacto.');
        }

        return self::SUCCESS;
    }
}
