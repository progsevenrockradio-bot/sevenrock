<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\AuditLog;
use App\Models\ThemeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigureEditorialAutoPublishCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_status_shows_configuration(): void
    {
        ThemeSetting::clearCache();
        $settings = ThemeSetting::firstOrCreate([], ThemeSetting::defaults());
        $settings->email_auto_publish = false;
        $settings->save();
        ThemeSetting::clearCache();

        $this->artisan('editorial:configure-auto-publish --status')
            ->assertExitCode(0);
    }

    public function test_command_enable_activates_and_records_audit_trail_accurately(): void
    {
        ThemeSetting::clearCache();
        $settings = ThemeSetting::firstOrCreate([], ThemeSetting::defaults());
        $settings->email_auto_publish = false;
        $settings->save();
        ThemeSetting::clearCache();

        $initialAuditCount = AuditLog::count();

        $this->artisan('editorial:configure-auto-publish --enable')
            ->expectsOutputToContain("Ajuste 'email_auto_publish' en ThemeSetting actualizado a: TRUE")
            ->expectsOutputToContain("Evento de auditoría registrado exitosamente: editorial.auto_publish.enabled")
            ->assertExitCode(0);

        ThemeSetting::clearCache();
        $this->assertTrue((bool) ThemeSetting::current()->email_auto_publish);

        // Verificar registro exacto en AuditLog
        $this->assertGreaterThan($initialAuditCount, AuditLog::count());
        $audit = AuditLog::where('event', 'editorial.auto_publish.enabled')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame(ThemeSetting::class, $audit->subject_type);
        $this->assertSame((string) $settings->getKey(), (string) $audit->subject_id);
        $this->assertFalse($audit->before_state['email_auto_publish'] ?? null);
        $this->assertTrue($audit->after_state['email_auto_publish'] ?? null);
        $this->assertSame('editorial.auto_publish.enabled', $audit->event);
    }

    public function test_command_disable_deactivates_and_records_audit_trail_accurately(): void
    {
        ThemeSetting::clearCache();
        $settings = ThemeSetting::firstOrCreate([], ThemeSetting::defaults());
        $settings->email_auto_publish = true;
        $settings->save();
        ThemeSetting::clearCache();

        $this->artisan('editorial:configure-auto-publish --disable')
            ->expectsOutputToContain("Ajuste 'email_auto_publish' en ThemeSetting actualizado a: FALSE")
            ->expectsOutputToContain("Evento de auditoría registrado exitosamente: editorial.auto_publish.disabled")
            ->assertExitCode(0);

        ThemeSetting::clearCache();
        $this->assertFalse((bool) ThemeSetting::current()->email_auto_publish);

        $audit = AuditLog::where('event', 'editorial.auto_publish.disabled')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame(ThemeSetting::class, $audit->subject_type);
        $this->assertTrue($audit->before_state['email_auto_publish'] ?? null);
        $this->assertFalse($audit->after_state['email_auto_publish'] ?? null);
    }
}
