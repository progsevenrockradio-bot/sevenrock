<?php

declare(strict_types=1);

namespace Tests\Feature\Talent;

use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TalentAuditMediaStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_media_storage_command_detects_affected_objects_in_read_only_mode(): void
    {
        $talent = Talent::factory()->create([
            'band_name' => 'Banda Auditada',
            'is_hidden' => false,
            'subscription_status' => 'active',
        ]);

        // 1. Objeto público en disco r2_private (seguro)
        TalentMedia::create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'seguro.mp3',
            'disk' => 'r2_private',
            'backblaze_key' => 'talents/seguro.mp3',
            'url' => route('talents.media.stream', ['id' => 1]),
            'status' => 'approved',
            'is_exclusive' => false,
            'mime_type' => 'audio/mpeg',
            'size' => 1024,
        ]);

        // 2. Objeto exclusivo en disco público r2 (afectado)
        TalentMedia::create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'exclusivo_en_r2.mp3',
            'disk' => 'r2',
            'backblaze_key' => 'talents/exclusivo_en_r2.mp3',
            'url' => 'https://pub-sevenrock.r2.dev/talents/exclusivo_en_r2.mp3',
            'status' => 'approved',
            'is_exclusive' => true,
            'mime_type' => 'audio/mpeg',
            'size' => 1024,
        ]);

        // 3. Documento en disco público r2 (afectado)
        TalentMedia::create([
            'talent_id' => $talent->id,
            'type' => 'document',
            'filename' => 'dossier.pdf',
            'disk' => 'r2',
            'backblaze_key' => 'talents/dossier.pdf',
            'url' => 'https://pub-sevenrock.r2.dev/talents/dossier.pdf',
            'status' => 'approved',
            'is_exclusive' => false,
            'mime_type' => 'application/pdf',
            'size' => 2048,
        ]);

        config([
            'filesystems.disks.r2.bucket' => 'sevenrock-public-bucket',
            'filesystems.disks.r2.url' => 'https://pub-sevenrock.r2.dev',
            'filesystems.disks.r2_private.bucket' => 'sevenrock-private-bucket',
            'filesystems.disks.r2_private.url' => '',
            'filesystems.disks.r2_private.visibility' => 'private',
        ]);

        $this->artisan('talent:audit-media-storage')
            ->expectsOutputToContain('CONFIGURACIÓN EFECTIVA Y CACHÉ DEL ENTORNO')
            ->expectsOutputToContain('AISLAMIENTO Y PRIVACIDAD DE BUCKETS R2')
            ->expectsOutputToContain('Aislamiento físico verificado: [sevenrock-private-bucket] es estrictamente distinto de [sevenrock-public-bucket].')
            ->expectsOutputToContain('Total de registros en talent_media: 3')
            ->expectsOutputToContain('Objetos que requieren regularización segura: 2')
            ->assertSuccessful();

        $this->artisan('talent:audit-media-storage', ['--format' => 'json'])
            ->assertSuccessful();
    }
}

