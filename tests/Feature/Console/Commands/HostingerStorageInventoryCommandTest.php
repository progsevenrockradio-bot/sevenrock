<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Services\StorageInventory\R2ObjectInspector;
use App\Services\StorageInventory\StorageInventoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class HostingerStorageInventoryCommandTest extends TestCase
{
    private string $testStoragePath;
    private string $outsidePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = storage_path('framework/testing/inventory_test_' . uniqid());
        $this->outsidePath = storage_path('framework/testing/outside_test_' . uniqid());

        File::makeDirectory($this->testStoragePath . '/public/podcast-inbox/covers', 0755, true);
        File::makeDirectory($this->testStoragePath . '/public/programas_procesados', 0755, true);
        File::makeDirectory($this->testStoragePath . '/tmp/podcast-processing', 0755, true);
        File::makeDirectory($this->testStoragePath . '/tmp/r2', 0755, true);
        File::makeDirectory($this->testStoragePath . '/podcast-chunks/upload_123', 0755, true);
        File::makeDirectory($this->testStoragePath . '/temp', 0755, true);
        File::makeDirectory($this->outsidePath, 0755, true);

        Config::set('queue.default', 'database');
        $this->prepareMinimalSchema();
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->testStoragePath)) {
            File::deleteDirectory($this->testStoragePath);
        }
        if (File::isDirectory($this->outsidePath)) {
            File::deleteDirectory($this->outsidePath);
        }

        parent::tearDown();
    }

    private function prepareMinimalSchema(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('queue');
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at')->default(0);
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->text('connection')->nullable();
                $table->text('queue')->nullable();
                $table->longText('payload');
                $table->longText('exception')->nullable();
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('radio_programs')) {
            Schema::create('radio_programs', function (Blueprint $table): void {
                $table->id();
                $table->string('titulo_programa')->nullable();
                $table->integer('numero_episodio')->nullable();
                $table->string('archivo_mp3')->nullable();
                $table->string('archivo_mp3_disk')->nullable();
                $table->string('delivery_status')->nullable();
                $table->string('radioboss_status')->nullable();
                $table->string('archive_org_status')->nullable();
                $table->timestamp('processing_started_at')->nullable();
                $table->timestamp('processing_finished_at')->nullable();
            });
        }
    }

    public function test_inventory_runs_read_only_and_classifies_temporaries_safely(): void
    {
        $oldMtime = time() - (48 * 3600); // 48h ago

        // 1. Temporal libre válido (con UUID y > 24h, sin jobs) -> Categoría B
        $idleTemp = $this->testStoragePath . '/tmp/podcast-processing/11111111-2222-3333-4444-555555555555-idle_audio.mp3';
        file_put_contents($idleTemp, str_repeat('A', 1024));
        touch($idleTemp, $oldMtime);

        // 2. Temporal con job reservado/en ejecución -> Categoría C (Conservar)
        $busyTemp = $this->testStoragePath . '/tmp/podcast-processing/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee-busy_audio.mp3';
        file_put_contents($busyTemp, str_repeat('B', 1024));
        touch($busyTemp, $oldMtime);
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['file' => 'busy_audio.mp3']),
            'attempts' => 1,
            'reserved_at' => time() - 60,
            'available_at' => time() - 120,
            'created_at' => time() - 120,
        ]);

        // 3. Temporal con job fallido reintentable -> Categoría C (Conservar)
        $failedTemp = $this->testStoragePath . '/tmp/r2/99999999-8888-7777-6666-555555555555-failed_episode.mp3';
        file_put_contents($failedTemp, str_repeat('F', 1024));
        touch($failedTemp, $oldMtime);
        DB::table('failed_jobs')->insert([
            'payload' => json_encode(['path' => 'failed_episode.mp3']),
            'failed_at' => now(),
        ]);

        // 4. Temporal reciente (< 24h) -> Categoría C (Conservar)
        $recentTemp = $this->testStoragePath . '/tmp/podcast-processing/12345678-1234-1234-1234-1234567890ab-recent_audio.mp3';
        file_put_contents($recentTemp, str_repeat('R', 512));
        touch($recentTemp, time() - 600); // 10 min ago

        // 5. Archivo en tmp sin patrón de propiedad UUID -> Categoría D (Incierto)
        $unknownTemp = $this->testStoragePath . '/tmp/podcast-processing/manual_file_without_uuid.mp3';
        file_put_contents($unknownTemp, str_repeat('U', 512));
        touch($unknownTemp, $oldMtime);

        // 6. Archivo fuera de las raíces permitidas (en la raíz de storage/app) -> No debe incluirse
        $rootPatch = $this->testStoragePath . '/phase1_changes.patch';
        file_put_contents($rootPatch, 'diff --git');

        $service = app(StorageInventoryService::class);
        $report = $service->run($this->testStoragePath, false, 24);

        $byRelPath = collect($report['items'])->keyBy('relative_path');

        $this->assertFalse($byRelPath->has('phase1_changes.patch'), 'Los archivos sueltos en la raíz no deben incluirse como candidatos.');

        $this->assertSame(
            StorageInventoryService::CAT_B,
            $byRelPath->get('tmp/podcast-processing/11111111-2222-3333-4444-555555555555-idle_audio.mp3')['category']
        );
        $this->assertSame(
            StorageInventoryService::CAT_C,
            $byRelPath->get('tmp/podcast-processing/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee-busy_audio.mp3')['category']
        );
        $this->assertSame(
            StorageInventoryService::CAT_C,
            $byRelPath->get('tmp/r2/99999999-8888-7777-6666-555555555555-failed_episode.mp3')['category']
        );
        $this->assertSame(
            StorageInventoryService::CAT_C,
            $byRelPath->get('tmp/podcast-processing/12345678-1234-1234-1234-1234567890ab-recent_audio.mp3')['category']
        );
        $this->assertSame(
            StorageInventoryService::CAT_D,
            $byRelPath->get('tmp/podcast-processing/manual_file_without_uuid.mp3')['category']
        );

        // Garantizar que ningún archivo fue borrado ni alterado
        $this->assertFileExists($idleTemp);
        $this->assertFileExists($busyTemp);
        $this->assertFileExists($failedTemp);
        $this->assertFileExists($recentTemp);
        $this->assertFileExists($unknownTemp);
        $this->assertFileExists($rootPatch);
    }

    public function test_r2_verification_requires_sha256_match_and_no_local_references_for_category_a(): void
    {
        // Archivo 1: copia procesada cuyos bytes coinciden con R2 y el episodio apunta a r2 -> Categoría A
        $verifiedRel = 'programas_procesados/001.- SHOW VERIFICADO 28-10-2026.mp3';
        $verifiedPath = $this->testStoragePath . '/public/' . $verifiedRel;
        $verifiedContent = 'MP3_PROCESADO_VERIFICADO_CONTENIDO_EXACTO';
        file_put_contents($verifiedPath, $verifiedContent);
        $verifiedSha = hash('sha256', $verifiedContent);

        DB::table('radio_programs')->insert([
            'id' => 10,
            'titulo_programa' => 'Show Verificado',
            'numero_episodio' => 1,
            'archivo_mp3' => $verifiedRel,
            'archivo_mp3_disk' => 'r2',
            'delivery_status' => 'delivery_verified',
            'radioboss_status' => 'radioboss_verified',
            'archive_org_status' => 'archive_verified',
            'processing_started_at' => now()->subHours(5),
            'processing_finished_at' => now()->subHours(4),
        ]);

        // Archivo 2: mismo tamaño en R2 pero distinto SHA-256 -> Categoría D (no basta tamaño igual)
        $mismatchRel = 'programas_procesados/002.- SHOW DISTINTO 28-10-2026.mp3';
        $mismatchPath = $this->testStoragePath . '/public/' . $mismatchRel;
        $mismatchContent = 'CONTENIDO_LOCAL_DIFERENTE_MISMO_TAMANO__';
        file_put_contents($mismatchPath, $mismatchContent);

        // Archivo 3: episodio sigue usando archivo_mp3_disk=public -> Categoría C (Conservar)
        $localActiveRel = 'programas_procesados/003.- SHOW LOCAL ACTIVO 28-10-2026.mp3';
        $localActivePath = $this->testStoragePath . '/public/' . $localActiveRel;
        file_put_contents($localActivePath, 'CONTENIDO_LOCAL_EN_USO');

        DB::table('radio_programs')->insert([
            'id' => 11,
            'titulo_programa' => 'Show Local Activo',
            'numero_episodio' => 3,
            'archivo_mp3' => $localActiveRel,
            'archivo_mp3_disk' => 'public',
            'delivery_status' => 'delivery_verified',
        ]);

        $r2Mock = Mockery::mock(R2ObjectInspector::class);
        $r2Mock->shouldReceive('isConfigured')->andReturn(true);
        $r2Mock->shouldReceive('bucket')->andReturn('sevenrock-bucket');
        $r2Mock->shouldReceive('publicBaseUrl')->andReturn('https://cdn.sevenrockradio.com');
        $r2Mock->shouldReceive('appUrl')->andReturnUsing(fn (string $key) => 'https://cdn.sevenrockradio.com/' . $key);

        $r2Mock->shouldReceive('head')->with($verifiedRel)->andReturn([
            'status' => 'present',
            'size' => strlen($verifiedContent),
            'etag' => 'etag-1',
            'last_modified' => now()->toIso8601String(),
            'error' => null,
        ]);
        $r2Mock->shouldReceive('hash')->with($verifiedRel)->andReturn([
            'status' => 'ok',
            'sha256' => $verifiedSha,
            'md5' => md5($verifiedContent),
            'bytes' => strlen($verifiedContent),
            'error' => null,
        ]);

        $r2Mock->shouldReceive('head')->with($mismatchRel)->andReturn([
            'status' => 'present',
            'size' => strlen($mismatchContent),
            'etag' => 'etag-2',
            'last_modified' => now()->toIso8601String(),
            'error' => null,
        ]);
        $r2Mock->shouldReceive('hash')->with($mismatchRel)->andReturn([
            'status' => 'ok',
            'sha256' => hash('sha256', 'OTRO_CONTENIDO_REMOTO_DE_IGUAL_LONGITUD!'),
            'md5' => '0000',
            'bytes' => strlen($mismatchContent),
            'error' => null,
        ]);

        $service = new StorageInventoryService($r2Mock);

        // Sin --deep-verify: incluso con tamaño igual en R2 queda en D (pendiente de checksum)
        $reportShallow = $service->run($this->testStoragePath, false, 24);
        $shallowItems = collect($reportShallow['items'])->keyBy('relative_path');
        $this->assertSame(
            StorageInventoryService::CAT_D,
            $shallowItems->get('public/' . $verifiedRel)['category'],
            'Sin deep-verify no debe clasificarse como A aunque exista y tenga el mismo tamaño.'
        );

        // Con --deep-verify:
        $reportDeep = $service->run($this->testStoragePath, true, 24);
        $deepItems = collect($reportDeep['items'])->keyBy('relative_path');

        $this->assertSame(
            StorageInventoryService::CAT_A,
            $deepItems->get('public/' . $verifiedRel)['category'],
            'Con SHA-256 idéntico, URL R2 válida y archivo_mp3_disk=r2 debe clasificarse como A.'
        );
        $this->assertSame(
            StorageInventoryService::CAT_D,
            $deepItems->get('public/' . $mismatchRel)['category'],
            'Si el SHA-256 difiere aunque el tamaño coincida, debe quedar en D.'
        );
        $this->assertSame(
            StorageInventoryService::CAT_C,
            $deepItems->get('public/' . $localActiveRel)['category'],
            'Si el episodio sigue apuntando al disco local public, debe conservarse en C.'
        );
    }
}
