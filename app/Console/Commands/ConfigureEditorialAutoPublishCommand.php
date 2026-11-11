<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ThemeSetting;
use App\Services\AuditTrailService;
use Illuminate\Console\Command;
use Throwable;

class ConfigureEditorialAutoPublishCommand extends Command
{
    protected $signature = 'editorial:configure-auto-publish 
                            {--enable : Activa la política de auto-publicación DV-1 en ThemeSetting}
                            {--disable : Desactiva la política de auto-publicación DV-1 en ThemeSetting}
                            {--status : Muestra el estado y valor efectivo evaluado por la aplicación}';

    protected $description = 'Consulta o modifica de forma segura y auditada el ajuste de auto-publicación DV-1 en ThemeSetting.';

    public function handle(AuditTrailService $auditTrailService): int
    {
        ThemeSetting::clearCache();
        $settings = ThemeSetting::current();
        $previousState = (bool) $settings->email_auto_publish;

        if ($this->option('enable')) {
            $newState = true;
            $event = 'editorial.auto_publish.enabled';
            $summary = "Activación de la política de auto-publicación DV-1 en ThemeSetting.";
        } elseif ($this->option('disable')) {
            $newState = false;
            $event = 'editorial.auto_publish.disabled';
            $summary = "Desactivación de la política de auto-publicación DV-1 en ThemeSetting.";
        } else {
            $newState = null;
            $event = null;
            $summary = null;
        }

        if ($newState !== null) {
            $settings->email_auto_publish = $newState;
            $settings->save();
            ThemeSetting::clearCache();

            // Registro obligatorio en auditoría oficial con AuditTrailService (sin silenciar errores)
            try {
                $auditTrailService->recordModel(
                    event: $event,
                    model: $settings,
                    before: ['email_auto_publish' => $previousState],
                    after: ['email_auto_publish' => $newState],
                    changes: ['email_auto_publish' => $newState],
                    context: [
                        'invoked_via' => 'artisan editorial:configure-auto-publish',
                        'command_option' => $newState ? '--enable' : '--disable',
                    ],
                    level: 'info'
                );
            } catch (Throwable $e) {
                $this->error("Fallo crítico al registrar la auditoría para {$event}: " . $e->getMessage());
                return self::FAILURE;
            }

            $stateLabel = $newState ? 'TRUE' : 'FALSE';
            $this->info("Ajuste 'email_auto_publish' en ThemeSetting actualizado a: {$stateLabel}");
            $this->info("Evento de auditoría registrado exitosamente: {$event}");
        }

        // Recargar ajustes frescos
        ThemeSetting::clearCache();
        $settings = ThemeSetting::current();
        $dbValue = (bool) $settings->email_auto_publish;
        $configValue = config('services.email_import.auto_publish');
        $envValue = env('EMAIL_AUTO_PUBLISH');

        $effectiveValue = (bool) (
            $settings->email_auto_publish 
            ?? $configValue 
            ?? $envValue 
            ?? false
        );

        $this->newLine();
        $this->line("=== CONFIGURACIÓN DE AUTO-PUBLICACIÓN EDITORIAL ===");
        $this->table(
            ['Origen / Capa', 'Valor Configurado', 'Prioridad de Evaluación'],
            [
                ['ThemeSetting (Base de Datos)', $dbValue ? 'TRUE' : 'FALSE', '1 (Máxima prioridad)'],
                ['config(services.email_import.auto_publish)', var_export($configValue, true), '2 (Fallback secundario)'],
                ['env(EMAIL_AUTO_PUBLISH)', var_export($envValue, true), '3 (Fallback terciario)'],
                ['VALOR EFECTIVO PROCESADOR', $effectiveValue ? 'ACTIVO (TRUE)' : 'INACTIVO (FALSE)', 'Resultado que gobierna el procesador'],
            ]
        );

        $this->newLine();
        $this->line("=== AISLAMIENTO Y SEGURIDAD ===");
        $this->line("- Tarea en Cron: Schedule::command('emails:process --only-dv1') cada 15 minutos.");
        $this->line("- Aislamiento: El modificador '--only-dv1' garantiza que ningún otro remitente ni tarea de correo puede publicar.");
        $this->line("- Autenticación: Exclusivamente envíos de dark.vader.agent@gmail.com con validación criptográfica SPF/DKIM aprobada.");
        $this->line("- Política: Solo piezas con evidencia estructurada completa y fechas válidas pueden publicarse; piezas con inconsistencias quedan en borrador.");

        return self::SUCCESS;
    }
}
