<?php

namespace Tests\Feature\Audience;

use App\Jobs\GenerateDailyAudienceReportJob;
use App\Services\AudienceReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class AudienceDailyReportTest extends TestCase
{
    protected AudienceReportService $service;
    protected string $isolatedStorage;
    protected string $realStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->realStorage = storage_path('app/audience');
        $this->isolatedStorage = storage_path('framework/testing/audience');

        File::deleteDirectory($this->isolatedStorage);
        File::makeDirectory($this->isolatedStorage . '/daily/reports', 0755, true);
        File::makeDirectory($this->isolatedStorage . '/daily/pdfs', 0755, true);
        File::makeDirectory($this->isolatedStorage . '/daily/locks', 0755, true);

        // Bind isolated service in container so controller resolves the isolated storage during test
        $this->service = new AudienceReportService($this->isolatedStorage);
        $this->app->instance(AudienceReportService::class, $this->service);

        // Ensure gallery_images table exists in testing SQLite for multimedia page checks
        if (!\Illuminate\Support\Facades\Schema::hasTable('gallery_images')) {
            \Illuminate\Support\Facades\Schema::create('gallery_images', function ($table) {
                $table->id();
                $table->string('image_url')->nullable();
                $table->string('caption')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->isolatedStorage);
        parent::tearDown();
    }

    protected function mockDailyPayload(string $date): array
    {
        return [
            'total_sessions' => 950,
            'total_tlh' => 125,
            'total_unique' => 410,
            'days' => [
                [
                    'date' => $date,
                    'cnt' => 950,
                    'cnt_unique' => 410,
                ]
            ],
            'countries' => [
                ['country' => 'Spain', 'country_iso' => 'es', 'cnt' => 500],
                ['country' => 'Germany', 'country_iso' => 'de', 'cnt' => 300],
            ],
            'countries_tlh' => [
                ['country' => 'Spain', 'country_iso' => 'es', 'tlh' => 70],
                ['country' => 'Germany', 'country_iso' => 'de', 'tlh' => 40],
            ],
            'unique' => [
                ['country' => 'Spain', 'country_iso' => 'es', 'uniq' => 250],
                ['country' => 'Germany', 'country_iso' => 'de', 'uniq' => 110],
            ],
        ];
    }

    public function test_public_daily_audience_page_serves_stored_report(): void
    {
        $testDate = '2026-10-25';
        $mock = $this->mockDailyPayload($testDate);
        $this->service->generateDailyReport($testDate, dryRun: false, force: true, mockData: $mock);

        $response = $this->get(route('audience.daily', ['fecha' => $testDate]));

        $response->assertOk();
        $response->assertSee('Consulta Diaria de Audiencia');
        $response->assertSee('Sobre estos datos');
        $response->assertSee('domingo, 25 de octubre de 2026');
        $response->assertSee('950'); // Sesiones
        $response->assertSee('125'); // TLH
        $response->assertSee('410'); // Oyentes únicos
        $response->assertSee('Audiencia global de Seven Rock Radio durante el día seleccionado. No corresponde exclusivamente a un programa.');
        $response->assertSee(route('audience.daily.pdf', ['fecha' => $testDate]));
    }

    public function test_result_reuse_avoids_re_querying_when_cached(): void
    {
        $testDate = '2026-10-24';
        $mock = $this->mockDailyPayload($testDate);
        $this->service->generateDailyReport($testDate, dryRun: false, force: true, mockData: $mock);

        // Fake queue to prove no new job is dispatched when report already exists on disk
        Queue::fake();

        $response = $this->get(route('audience.daily', ['fecha' => $testDate]));

        $response->assertOk();
        $response->assertSee('950');
        Queue::assertNothingPushed();
    }

    public function test_invalid_date_formats_are_rejected_with_clear_error(): void
    {
        $response = $this->get(route('audience.daily', ['fecha' => 'invalid-date']));
        $response->assertOk();
        $response->assertSee('El formato de fecha no es válido');

        $response2 = $this->get(route('audience.daily', ['fecha' => '2026-02-31']));
        $response2->assertOk();
        $response2->assertSee('La fecha introducida no existe en el calendario');
    }

    public function test_unclosed_day_today_or_future_is_rejected(): void
    {
        $today = Carbon::now('Europe/Madrid')->format('Y-m-d');
        $tomorrow = Carbon::now('Europe/Madrid')->addDay()->format('Y-m-d');

        $responseToday = $this->get(route('audience.daily', ['fecha' => $today]));
        $responseToday->assertOk();
        $responseToday->assertSee('Solo es posible consultar días completos ya cerrados');

        $responseTomorrow = $this->get(route('audience.daily', ['fecha' => $tomorrow]));
        $responseTomorrow->assertOk();
        $responseTomorrow->assertSee('Solo es posible consultar días completos ya cerrados');
    }

    public function test_date_prior_to_historical_range_is_rejected(): void
    {
        $oldDate = '2025-12-31';
        $response = $this->get(route('audience.daily', ['fecha' => $oldDate]));
        $response->assertOk();
        $response->assertSee('es anterior al período histórico disponible');
    }

    public function test_missing_report_dispatches_controlled_job_to_audience_queue(): void
    {
        Queue::fake();

        $testDate = '2026-10-23';
        $this->assertFalse($this->service->hasDailyReport($testDate));

        $response = $this->get(route('audience.daily', ['fecha' => $testDate]));

        $response->assertOk();
        $response->assertSee('Sobre estos datos');
        $response->assertSee('Audiencia global de Seven Rock Radio durante el día seleccionado. No corresponde exclusivamente a un programa.');

        // Assert strictly pushed to 'audience' queue
        Queue::assertPushedOn('audience', GenerateDailyAudienceReportJob::class, function ($job) use ($testDate) {
            return $job->date === $testDate;
        });

        // Test status endpoint returns pending
        $statusRes = $this->get(route('audience.daily.status', ['fecha' => $testDate]));
        $statusRes->assertOk();
        $statusRes->assertJson(['status' => 'pending', 'date' => $testDate]);
    }

    public function test_status_endpoint_returns_ready_when_file_exists(): void
    {
        $testDate = '2026-10-22';
        $mock = $this->mockDailyPayload($testDate);
        $this->service->generateDailyReport($testDate, dryRun: false, force: true, mockData: $mock);

        $statusRes = $this->get(route('audience.daily.status', ['fecha' => $testDate]));
        $statusRes->assertOk();
        $statusRes->assertJson(['status' => 'ready', 'date' => $testDate]);
    }

    public function test_job_lifecycle_updates_status_cache_and_handles_failure(): void
    {
        $testDate = '2026-10-19';
        $job = new GenerateDailyAudienceReportJob($testDate);
        $this->assertEquals('audience', $job->queue);

        // Simulate failure handling
        $job->failed(new RuntimeException('Error de conexión con RadioBOSS Cloud'));

        $status = Cache::get(GenerateDailyAudienceReportJob::statusKey($testDate));
        $this->assertEquals('failed', $status['status']);
        $this->assertStringContainsString('RadioBOSS Cloud', $status['error']);
    }

    public function test_failed_status_allows_controlled_retry(): void
    {
        $testDate = '2026-10-18';
        Cache::put(GenerateDailyAudienceReportJob::statusKey($testDate), [
            'status' => 'failed',
            'started_at' => now()->timestamp,
            'updated_at' => now()->timestamp,
            'error' => 'Error de conexión simulado',
        ], 300);

        Queue::fake();

        // Visiting with reintentar=1 must re-dispatch job to audience queue
        $response = $this->get(route('audience.daily', ['fecha' => $testDate, 'reintentar' => 1]));
        $response->assertOk();

        Queue::assertPushedOn('audience', GenerateDailyAudienceReportJob::class, function ($job) use ($testDate) {
            return $job->date === $testDate;
        });
    }

    public function test_incomplete_radioboss_daily_data_throws_exception(): void
    {
        $testDate = '2026-10-21';
        $badData = [
            'total_sessions' => null, // Missing
            'total_tlh' => 10,
            'total_unique' => 5,
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Datos diarios incompletos de RadioBOSS: falta el campo obligatorio 'total_sessions'.");

        $this->service->generateDailyReport($testDate, dryRun: false, force: true, mockData: $badData);
    }

    public function test_pdf_download_serves_exact_selected_date(): void
    {
        $testDate = '2026-10-20';
        $mock = $this->mockDailyPayload($testDate);
        $this->service->generateDailyReport($testDate, dryRun: false, force: true, mockData: $mock);

        $res = $this->get(route('audience.daily.pdf', ['fecha' => $testDate]));
        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');

        // Missing date returns 404
        $res404 = $this->get(route('audience.daily.pdf', ['fecha' => '2026-09-01']));
        $res404->assertNotFound();
    }

    public function test_multimedia_podcasts_tab_displays_link_to_daily_audience(): void
    {
        \Illuminate\Support\Facades\Cache::put('site.gallery.images.24', collect([]), 3600);
        \Illuminate\Support\Facades\Cache::put('site.gallery.featured', collect([]), 3600);

        $response = $this->get(route('multimedia'));
        $response->assertOk();
        $response->assertSee('Consulta la audiencia de la radio');
        $response->assertSee(route('audience.daily'));
    }

    public function test_weekly_report_and_podcasts_remain_intact(): void
    {
        \Illuminate\Support\Facades\Cache::put('site.gallery.images.24', collect([]), 3600);
        \Illuminate\Support\Facades\Cache::put('site.gallery.featured', collect([]), 3600);

        $resWeekly = $this->get(route('audience.report.latest'));
        $resWeekly->assertOk();
        $resWeekly->assertSee('Informe semanal de audiencia');

        $resPodcasts = $this->get(route('multimedia'));
        $resPodcasts->assertOk();
        $resPodcasts->assertSee('Podcasts');
    }
}
