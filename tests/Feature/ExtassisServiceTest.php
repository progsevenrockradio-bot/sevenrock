<?php

namespace Tests\Feature;

use App\Jobs\Concerns\InteractsWithPodcastUploadPipeline;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Services\ExtassisService;
use App\Services\PodcastPipelineAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Tests\TestCase;

class ExtassisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_extassis_enabled_false_hace_nada()
    {
        Config::set('filesystems.disks.extassis.enabled', false);
        
        $mock = $this->mock(ExtassisService::class, function (MockInterface $mock) {
            $mock->shouldReceive('canSync')->andReturn(false);
            $mock->shouldNotReceive('upload');
        });

        $job = new FakePipelineJob(1);
        $job->triggerExtassis('folder', 'remote', 'absolute', 'local');
        
        $this->assertTrue(true);
    }

    public function test_dia_sin_mapeo_no_sube_y_avisa()
    {
        Config::set('filesystems.disks.extassis.enabled', true);
        Config::set('filesystems.disks.extassis.dirs', []); // Sin mapeos

        $mock = $this->mock(ExtassisService::class, function (MockInterface $mock) {
            $mock->shouldReceive('canSync')->andReturn(true);
            $mock->shouldNotReceive('upload');
        });

        $master = MasterProgram::factory()->create(['dia_transmision' => 'LUNES']);
        $radio = RadioProgram::factory()->create(['master_program_id' => $master->id]);

        Log::shouldReceive('warning')->once()->withArgs(function($msg) {
            return str_contains($msg, 'Día de transmisión no mapeado');
        });

        $job = new FakePipelineJob($radio->id);
        $job->triggerExtassis('folder', 'remote', 'absolute', 'local');
    }

    public function test_carpeta_inexistente_lanza_excepcion_clara()
    {
        Config::set('filesystems.disks.extassis.enabled', true);
        Config::set('filesystems.disks.extassis.dirs', ['LUNES' => '006.-LUNES']);

        $master = MasterProgram::factory()->create(['dia_transmision' => 'LUNES']);
        $radio = RadioProgram::factory()->create(['master_program_id' => $master->id]);

        $mock = $this->mock(ExtassisService::class, function (MockInterface $mock) {
            $mock->shouldReceive('canSync')->andReturn(true);
            $mock->shouldReceive('upload')->andThrow(new \RuntimeException('La carpeta destino no existe en EXTASSIS: 006.-LUNES'));
        });

        $auditMock = $this->mock(PodcastPipelineAuditService::class, function (MockInterface $mock) use ($radio) {
            $mock->shouldReceive('record')->once()->withArgs(function($model, $event) use ($radio) {
                return $event === 'EXTASSIS_UPLOAD_FAILED' && $model->id === $radio->id;
            });
        });

        Log::shouldReceive('warning')->once()->withArgs(function($msg) {
            return str_contains($msg, 'EXTASSIS upload failed');
        });

        $job = new FakePipelineJob($radio->id);
        $job->triggerExtassis('folder', 'remote', 'absolute', 'local');
    }

    public function test_carpeta_existente_sube_y_lo_registra()
    {
        Config::set('filesystems.disks.extassis.enabled', true);
        Config::set('filesystems.disks.extassis.dirs', ['LUNES' => '006.-LUNES']);

        $master = MasterProgram::factory()->create(['dia_transmision' => 'LUNES']);
        $radio = RadioProgram::factory()->create(['master_program_id' => $master->id]);

        $mock = $this->mock(ExtassisService::class, function (MockInterface $mock) {
            $mock->shouldReceive('canSync')->andReturn(true);
            $mock->shouldReceive('upload')->once();
        });

        $auditMock = $this->mock(PodcastPipelineAuditService::class, function (MockInterface $mock) use ($radio) {
            $mock->shouldReceive('record')->once()->withArgs(function($model, $event) use ($radio) {
                return $event === 'EXTASSIS_UPLOAD_COMPLETED' && $model->id === $radio->id;
            });
        });

        $job = new FakePipelineJob($radio->id);
        $job->triggerExtassis('folder', 'remote', 'absolute', 'local');
    }
}

class FakePipelineJob
{
    use InteractsWithPodcastUploadPipeline;
    
    public $radioProgramId;

    public function __construct($id) {
        $this->radioProgramId = $id;
    }

    public function triggerExtassis($folder, $remote, $absolute, $local) {
        $this->uploadToExtassis($folder, $remote, $absolute, $local);
    }
}
