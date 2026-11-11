<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ThemeSetting;
use App\Services\EditorialBatchProcessor;
use Illuminate\Console\Command;

class ConfigureEditorialAutoPublishCommand extends Command
{
    protected $signature = 'editorial:configure-auto-publish 
                            {--enable : Activa la política de auto-publicación DV-1 en ThemeSetting}
                            {--disable : Desactiva la política de auto-publicación DV-1 en ThemeSetting}
                            {--status : Muestra el estado y valor efectivo evaluado por la aplicación}';

    protected $description = 'Consulta o modifica de forma segura y auditada el ajuste de auto-publicación DV-1 en ThemeSetting.';

    public function handle(): int
    {
        ThemeSetting::clearCache();
        $settings = ThemeSetting::current();

        if ($this->option('enable')) {
            $settings->email_auto_publish = true;
            $settings->save();
            ThemeSetting::clearCache();
            $this->info("Ajuste 'email_auto_publish' en ThemeSetting actualizado a: TRUE");

            // Registro explícito en auditoría
            try {
                AuditLog::create([
                    'auditable_type' => ThemeSetting::class,
                    'auditable_id'   => $settings->id ?: 1,
                    'action'         => 'editorial_auto_publish_enabled',
                    'user_id'        => null,
                    'old_values'     => ['email_auto_publish' => false],
                    'new_values'     => ['email_auto_publish' => true],
                    'ip_address'     => '127.0.0.1',
                    'user_agent'     => 'CLI / Artisan Command',
                ]);
            } catch (\Throwable) {
                // Si la tabla no está disponible o falla, continuar
            }
        } elseif ($this->option('disable')) {
            $settings->email_auto_publish = false;
            $settings->save();
            ThemeSetting::clearCache();
            $this->info("Ajuste 'email_auto_publish' en ThemeSetting actualizado a: FALSE");

            try {
                AuditLog::create([
                    'auditable_type' => ThemeSetting::class,
                    'auditable_id'   => $settings->id ?: 1,
                    'action'         => 'editorial_auto_publish_disabled',
                    'user_id'        => null,
                    'old_values'     => ['email_auto_publish' => true],
                    'new_values'     => ['email_auto_publish' => false],
                    'ip_address'     => '127.0.0.1',
                    'user_agent'     => 'CLI / Artisan Command',
                ]);
            } catch (\Throwable) {
                // continuar
            }
        }

        // Recargar ajustes frescos
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
