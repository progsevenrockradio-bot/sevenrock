<?php

namespace Tests\Feature\Audience;

use App\Services\AudienceReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class AudienceReportServiceTest extends TestCase
{
    protected AudienceReportService $service;
    protected string $isolatedStorage;
    protected string $realStorage;
    protected ?string $realReportSnapshot = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->realStorage = storage_path('app/audience');
        $realReportFile = $this->realStorage . '/reports/2026-W44.json';
        if (File::exists($realReportFile)) {
            $this->realReportSnapshot = File::get($realReportFile);
        }

        $this->isolatedStorage = storage_path('framework/testing/audience');
        File::deleteDirectory($this->isolatedStorage);
        File::makeDirectory($this->isolatedStorage . '/reports', 0755, true);
        File::makeDirectory($this->isolatedStorage . '/pdfs', 0755, true);

        // Instantiate service pointing strictly to isolated testing directory
        $this->service = new AudienceReportService($this->isolatedStorage);
    }

    protected function tearDown(): void
    {
        // Clean up test sandbox
        File::deleteDirectory($this->isolatedStorage);

        // Verify that real storage was NEVER modified or contaminated during test
        if ($this->realReportSnapshot !== null) {
            $realReportFile = $this->realStorage . '/reports/2026-W44.json';
            $this->assertFileExists($realReportFile, 'El informe real fue borrado durante las pruebas.');
            $this->assertEquals(
                $this->realReportSnapshot,
                File::get($realReportFile),
                'El informe real en storage/app/audience/reports/2026-W44.json fue modificado por las pruebas.'
            );
        }

        parent::tearDown();
    }

    protected function generateValidMockWeek(string $startDate = '2026-10-26'): array
    {
        $start = Carbon::parse($startDate);
        $rbDays = [];
        $cfDays = [];

        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i)->format('Y-m-d');
            $rbDays[] = [
                'date' => $d,
                'cnt' => 500 + $i * 10,
                'cnt_unique' => 150 + $i * 5,
            ];
            $cfDays[] = [
                'date' => $d,
                'requests' => 30000 + $i * 100,
                'page_views' => 10000 + $i * 50,
                'uniques' => 5000 + $i * 25,
                'threats' => 10,
                'bytes' => 1000000,
                'cached_requests' => 500,
            ];
        }

        return [
            'radioboss' => [
                'source' => 'RadioBOSS Cloud API (Mock)',
                'station_id' => '569',
                'timezone' => 'Europe/Madrid',
                'from' => $startDate,
                'to' => $start->copy()->addDays(6)->format('Y-m-d'),
                'total_sessions' => 3500,
                'total_tlh' => 850,
                'total_unique' => 1100,
                'days' => $rbDays,
                'countries' => [['country' => 'Spain', 'country_iso' => 'es', 'cnt' => 2000]],
                'countries_tlh' => [['country' => 'Spain', 'country_iso' => 'es', 'tlh' => 500]],
                'countries_unique' => [['country' => 'Spain', 'country_iso' => 'es', 'uniq' => 300]],
            ],
            'cloudflare' => [
                'source' => 'Cloudflare GraphQL Analytics (Mock)',
                'zone_name' => 'sevenrockradio.com',
                'timezone' => 'UTC',
                'date_start' => $startDate,
                'date_end' => $start->copy()->addDays(6)->format('Y-m-d'),
                'requests' => 210000,
                'page_views' => 70000,
                'unique_visitors' => 35000,
                'threats' => 70,
                'bytes' => 7000000,
                'cached_requests' => 3500,
                'days' => $cfDays,
            ],
        ];
    }

    public function test_isolated_storage_does_not_touch_real_audience_directory(): void
    {
        $mock = $this->generateValidMockWeek('2026-10-26');
        $report = $this->service->generateReport(2026, 44, dryRun: false, force: true, mockData: $mock);

        $this->assertEquals('2026-W44', $report['id']);
        $this->assertStringStartsWith($this->isolatedStorage, $report['pdf_path']);
        $this->assertFileExists($this->isolatedStorage . '/reports/2026-W44.json');
        $this->assertFileExists($this->isolatedStorage . '/reports/latest.json');

        // Check that real report remains untouched
        $realJson = json_decode(File::get($this->realStorage . '/reports/2026-W44.json'), true);
        $this->assertEquals('RadioBOSS Cloud API (/api/getstats)', $realJson['radioboss']['source']);
        $this->assertNotEquals('RadioBOSS Cloud API (Mock)', $realJson['radioboss']['source']);
    }

    public function test_incomplete_radioboss_data_throws_exception_and_does_not_write(): void
    {
        $mock = $this->generateValidMockWeek('2026-10-26');
        // Missing total_sessions
        unset($mock['radioboss']['total_sessions']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Datos incompletos de RadioBOSS: falta el campo obligatorio 'total_sessions'.");

        $this->service->generateReport(2026, 44, dryRun: false, force: true, mockData: $mock);
    }

    public function test_radioboss_with_less_than_7_days_is_rejected(): void
    {
        $mock = $this->generateValidMockWeek('2026-10-26');
        // Only 5 days
        $mock['radioboss']['days'] = array_slice($mock['radioboss']['days'], 0, 5);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('se esperaban exactamente 7 días para la semana, recibidos 5');

        $this->service->generateReport(2026, 44, dryRun: false, force: true, mockData: $mock);
    }

    public function test_radioboss_discontinuous_days_are_rejected(): void
    {
        $mock = $this->generateValidMockWeek('2026-10-26');
        // Change date of day 2 to create a gap
        $mock['radioboss']['days'][1]['date'] = '2026-10-29';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Discontinuidad en días de RadioBOSS');

        $this->service->generateReport(2026, 44, dryRun: false, force: true, mockData: $mock);
    }

    public function test_incomplete_cloudflare_data_throws_exception(): void
    {
        $mock = $this->generateValidMockWeek('2026-10-26');
        // Missing page_views
        unset($mock['cloudflare']['page_views']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Datos incompletos de Cloudflare');

        $this->service->generateReport(2026, 44, dryRun: false, force: true, mockData: $mock);
    }

    public function test_cloudflare_with_less_than_7_days_is_rejected(): void
    {
        $mock = $this->generateValidMockWeek('2026-10-26');
        // Only 6 days
        $mock['cloudflare']['days'] = array_slice($mock['cloudflare']['days'], 0, 6);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('se esperaban exactamente 7 días en el desglose diario, recibidos 6');

        $this->service->generateReport(2026, 44, dryRun: false, force: true, mockData: $mock);
    }

    public function test_concurrency_lock_prevents_simultaneous_generations(): void
    {
        $lockFile = $this->isolatedStorage . DIRECTORY_SEPARATOR . 'generation.lock';
        $fp = fopen($lockFile, 'c+');
        $this->assertTrue(flock($fp, LOCK_EX));

        try {
            $mock = $this->generateValidMockWeek('2026-10-26');
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Bloqueo activo: otra generación de informe está en curso actualmente.');

            $this->service->generateReport(2026, 44, dryRun: false, force: true, mockData: $mock);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function test_regenerating_older_week_does_not_hijack_latest_pointer(): void
    {
        // 1. Generate week 44 (newer)
        $mock44 = $this->generateValidMockWeek('2026-10-26');
        $this->service->generateReport(2026, 44, dryRun: false, force: true, mockData: $mock44);

        $latestBefore = $this->service->getLatestReport();
        $this->assertEquals('2026-W44', $latestBefore['id']);

        // 2. Generate week 40 (older)
        $mock40 = $this->generateValidMockWeek('2026-09-28');
        $this->service->generateReport(2026, 40, dryRun: false, force: true, mockData: $mock40);

        // 3. Verify that latest.json STILL points to 2026-W44
        $latestAfter = $this->service->getLatestReport();
        $this->assertEquals('2026-W44', $latestAfter['id'], 'latest.json fue erróneamente sustituido por una semana más antigua.');

        // 4. Verify that week 40 exists on disk independently
        $rep40 = $this->service->getReport(2026, 40);
        $this->assertNotNull($rep40);
        $this->assertEquals('2026-W40', $rep40['id']);
    }

    public function test_spanish_period_formatting(): void
    {
        $start = Carbon::parse('2026-10-26');
        $end = Carbon::parse('2026-11-01');

        $label = $this->service->formatPeriodSpanish($start, $end, 44, 2026);
        $this->assertEquals('Semana 44 / 2026 (del 26 de octubre al 1 de noviembre de 2026)', $label);
    }

    public function test_http_routes_serve_real_report_and_pdf(): void
    {
        $res = $this->get(route('audience.report.latest'));
        $res->assertOk();
        $res->assertSee('Informe de audiencia de Seven Rock Radio');
        $res->assertSee('3.535');
        $res->assertSee('86.134');
        $res->assertSee('37.599');
        $res->assertSee('Europe/Madrid');
        $res->assertSee('UTC');

        $pdfRes = $this->get(route('audience.report.latest.pdf'));
        $pdfRes->assertOk();
        $pdfRes->assertHeader('Content-Type', 'application/pdf');

        $histRes = $this->get(route('audience.history'));
        $histRes->assertOk();
        $histRes->assertSee('2026-W44');
    }
}
