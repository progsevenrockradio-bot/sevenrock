<?php

namespace App\Services;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use RuntimeException;

class AudienceReportService
{
    protected string $storageBase;
    protected string $reportsDir;
    protected string $pdfsDir;
    protected string $dailyReportsDir;
    protected string $dailyPdfsDir;
    protected string $dailyLocksDir;

    public function __construct(?string $storageBase = null)
    {
        $this->setStorageBase($storageBase ?: storage_path('app/audience'));
    }

    public function setStorageBase(string $path): void
    {
        $this->storageBase = rtrim($path, '/\\');
        $this->reportsDir = $this->storageBase . DIRECTORY_SEPARATOR . 'reports';
        $this->pdfsDir = $this->storageBase . DIRECTORY_SEPARATOR . 'pdfs';
        $this->dailyReportsDir = $this->storageBase . DIRECTORY_SEPARATOR . 'daily' . DIRECTORY_SEPARATOR . 'reports';
        $this->dailyPdfsDir = $this->storageBase . DIRECTORY_SEPARATOR . 'daily' . DIRECTORY_SEPARATOR . 'pdfs';
        $this->dailyLocksDir = $this->storageBase . DIRECTORY_SEPARATOR . 'daily' . DIRECTORY_SEPARATOR . 'locks';
    }

    public function getStorageBase(): string
    {
        return $this->storageBase;
    }

    public function ensureDirectories(): void
    {
        $dirs = [
            $this->reportsDir,
            $this->pdfsDir,
            $this->dailyReportsDir,
            $this->dailyPdfsDir,
            $this->dailyLocksDir,
        ];

        foreach ($dirs as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }
    }

    public function reportId(int $year, int $week): string
    {
        return sprintf('%04d-W%02d', $year, $week);
    }

    public function hasReport(int $year, int $week): bool
    {
        $file = $this->reportsDir . DIRECTORY_SEPARATOR . $this->reportId($year, $week) . '.json';
        return File::exists($file);
    }

    public function getLatestReport(): ?array
    {
        $latestPointer = $this->reportsDir . DIRECTORY_SEPARATOR . 'latest.json';
        if (File::exists($latestPointer)) {
            $data = json_decode(File::get($latestPointer), true);
            if (is_array($data) && !empty($data['id'])) {
                $report = $this->getReportById($data['id']);
                if ($report !== null) {
                    return $report;
                }
            }
        }

        // Fallback: search the most recent report on disk
        $reports = $this->allReportsSummary();
        if (!empty($reports)) {
            return $this->getReportById($reports[0]['id']);
        }

        return null;
    }

    public function getReport(int $year, int $week): ?array
    {
        return $this->getReportById($this->reportId($year, $week));
    }

    public function getReportById(string $id): ?array
    {
        $path = $this->reportsDir . DIRECTORY_SEPARATOR . $id . '.json';
        if (!File::exists($path)) {
            return null;
        }
        $data = json_decode(File::get($path), true);
        return is_array($data) ? $data : null;
    }

    public function getPdfPath(int $year, int $week): ?string
    {
        $path = $this->pdfsDir . DIRECTORY_SEPARATOR . $this->reportId($year, $week) . '.pdf';
        return File::exists($path) ? realpath($path) : null;
    }

    public function allReportsSummary(): array
    {
        $this->ensureDirectories();
        $files = File::glob($this->reportsDir . DIRECTORY_SEPARATOR . '*-W*.json');
        $reports = [];

        foreach ($files as $file) {
            $content = File::get($file);
            $data = json_decode($content, true);
            if (!is_array($data) || empty($data['id']) || !isset($data['year'], $data['week'])) {
                continue;
            }
            $reports[] = [
                'id' => $data['id'],
                'year' => (int) $data['year'],
                'week' => (int) $data['week'],
                'title' => $data['title'] ?? 'Informe de audiencia',
                'period_label' => $data['period']['label'] ?? '',
                'generated_at' => $data['generated_at'] ?? '',
                'radio_sessions' => $data['radioboss']['total_sessions'] ?? 0,
                'radio_tlh' => $data['radioboss']['total_tlh'] ?? 0,
                'web_page_views' => $data['cloudflare']['page_views'] ?? 0,
                'web_uniques' => $data['cloudflare']['unique_visitors'] ?? 0,
                'has_pdf' => File::exists($this->pdfsDir . DIRECTORY_SEPARATOR . $data['id'] . '.pdf'),
            ];
        }

        usort($reports, function ($a, $b) {
            if ($a['year'] === $b['year']) {
                return $b['week'] <=> $a['week'];
            }
            return $b['year'] <=> $a['year'];
        });

        return $reports;
    }

    public function listReports(int $page = 1, int $perPage = 10): LengthAwarePaginator
    {
        $all = $this->allReportsSummary();
        $total = count($all);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($all, $offset, $perPage);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => route('audience.history')]
        );
    }

    public function formatPeriodSpanish(Carbon $startOfWeek, Carbon $endOfWeek, int $week, int $year): string
    {
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
        ];

        $startDay = (int) $startOfWeek->format('j');
        $startMonth = $meses[(int) $startOfWeek->format('n')];
        $endDay = (int) $endOfWeek->format('j');
        $endMonth = $meses[(int) $endOfWeek->format('n')];
        $endYear = (int) $endOfWeek->format('Y');

        if ($startMonth === $endMonth) {
            return sprintf('Semana %02d / %d (del %d al %d de %s de %d)', $week, $year, $startDay, $endDay, $startMonth, $endYear);
        }

        return sprintf('Semana %02d / %d (del %d de %s al %d de %s de %d)', $week, $year, $startDay, $startMonth, $endDay, $endMonth, $endYear);
    }

    public function fetchRadioBossStats(string $from, string $to): array
    {
        $apiUrl = rtrim((string) config('player.radioboss.api_url', env('RADIOBOSS_API_URL', 'https://c30.radioboss.fm')), '/');
        $stationId = (string) config('player.radioboss.station_id', env('RADIOBOSS_STATION_ID', '569'));
        $apiKey = (string) config('player.radioboss.api_key', env('RADIOBOSS_API_KEY', ''));

        if (empty($apiKey)) {
            throw new RuntimeException('RadioBOSS API Key no configurada en el sistema.');
        }

        $url = "{$apiUrl}/api/getstats/{$stationId}?key={$apiKey}&range=custom&from={$from}&to={$to}";

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\nUser-Agent: SevenRockAuditor/1.0\r\n",
                'timeout' => 20,
                'ignore_errors' => true
            ]
        ]);

        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) {
            throw new RuntimeException('Error de conexión con la API de RadioBOSS.');
        }

        $data = json_decode($res, true);
        if (!is_array($data)) {
            throw new RuntimeException('Respuesta no válida o no JSON de RadioBOSS.');
        }

        return $this->validateAndNormalizeRadioBossData($data, $stationId, $from, $to);
    }

    public function validateAndNormalizeRadioBossData(array $data, string $stationId, string $from, string $to): array
    {
        // 1. Mandatory top-level fields
        $requiredKeys = ['total_sessions', 'total_tlh', 'total_unique', 'days'];
        foreach ($requiredKeys as $rk) {
            if (!array_key_exists($rk, $data) || $data[$rk] === null) {
                throw new RuntimeException("Datos incompletos de RadioBOSS: falta el campo obligatorio '{$rk}'.");
            }
        }

        // 2. Exact 7 days expected
        $days = $data['days'];
        if (!is_array($days) || count($days) !== 7) {
            $count = is_array($days) ? count($days) : 0;
            throw new RuntimeException("Datos incompletos de RadioBOSS: se esperaban exactamente 7 días para la semana, recibidos {$count}.");
        }

        // 3. Validate days continuity and day fields
        $expectedCurrent = Carbon::parse($from);
        $expectedEnd = Carbon::parse($to);
        $normalizedDays = [];

        foreach ($days as $day) {
            if (!isset($day['date'], $day['cnt'], $day['cnt_unique'])) {
                throw new RuntimeException('Datos incompletos de RadioBOSS: un registro diario carece de fecha, sesiones o únicos.');
            }
            if ($day['date'] !== $expectedCurrent->format('Y-m-d')) {
                throw new RuntimeException("Discontinuidad en días de RadioBOSS: se esperaba la fecha {$expectedCurrent->format('Y-m-d')}, recibido {$day['date']}.");
            }
            $normalizedDays[] = [
                'date' => $day['date'],
                'cnt' => (int) $day['cnt'],
                'cnt_unique' => (int) $day['cnt_unique'],
            ];
            $expectedCurrent->addDay();
        }

        return [
            'source' => 'RadioBOSS Cloud API (/api/getstats)',
            'station_id' => $stationId,
            'timezone' => 'Europe/Madrid',
            'from' => $from,
            'to' => $to,
            'total_sessions' => (int) $data['total_sessions'],
            'total_tlh' => (int) $data['total_tlh'],
            'total_unique' => (int) $data['total_unique'],
            'methodology_note' => 'total_unique corresponde a la suma directa de oyentes únicos registrados en cada día del período según la API de RadioBOSS Cloud. El proveedor no indica deduplicación entre días distintos.',
            'days' => $normalizedDays,
            'countries' => array_slice($data['countries'] ?? [], 0, 10),
            'countries_tlh' => array_slice($data['countries_tlh'] ?? [], 0, 10),
            'countries_unique' => array_slice($data['unique'] ?? [], 0, 10),
        ];
    }

    public function fetchCloudflareStats(string $dateStart, string $dateEnd): array
    {
        $zoneId = (string) config('services.cloudflare.zone_id', env('CLOUDFLARE_ZONE_ID', ''));
        $token = (string) config('services.cloudflare.analytics_token', env('CLOUDFLARE_ANALYTICS_TOKEN', ''));

        if (empty($zoneId) || empty($token)) {
            throw new RuntimeException('Credenciales de Cloudflare Analytics no configuradas en .env.');
        }

        // 1. Query aggregated period
        $qAgg = <<<'GRAPHQL'
query GetPeriodAggregatedStats($zoneTag: string!, $dateStart: string!, $dateEnd: string!) {
  viewer {
    zones(filter: { zoneTag: $zoneTag }) {
      httpRequests1dGroups(
        limit: 1
        filter: { date_geq: $dateStart, date_leq: $dateEnd }
      ) {
        sum {
          requests
          bytes
          pageViews
          threats
          cachedRequests
        }
        uniq {
          uniques
        }
      }
    }
  }
}
GRAPHQL;

        $aggRes = $this->runCloudflareGraphQL($qAgg, [
            'zoneTag' => $zoneId,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd
        ], $token);

        if (!empty($aggRes['errors'])) {
            throw new RuntimeException('Error GraphQL Cloudflare (Agregado): ' . ($aggRes['errors'][0]['message'] ?? 'Error desconocido'));
        }

        $aggGroup = $aggRes['data']['viewer']['zones'][0]['httpRequests1dGroups'][0] ?? null;
        if (!$aggGroup) {
            throw new RuntimeException('Cloudflare no devolvió registros agregados para el período solicitado.');
        }

        // 2. Query daily breakdown
        $qDaily = <<<'GRAPHQL'
query GetWeekDailyStats($zoneTag: string!, $dateStart: string!, $dateEnd: string!) {
  viewer {
    zones(filter: { zoneTag: $zoneTag }) {
      httpRequests1dGroups(
        limit: 10
        filter: { date_geq: $dateStart, date_leq: $dateEnd }
        orderBy: [date_ASC]
      ) {
        dimensions {
          date
        }
        sum {
          requests
          bytes
          pageViews
          threats
          cachedRequests
        }
        uniq {
          uniques
        }
      }
    }
  }
}
GRAPHQL;

        $dailyRes = $this->runCloudflareGraphQL($qDaily, [
            'zoneTag' => $zoneId,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd
        ], $token);

        if (!empty($dailyRes['errors'])) {
            throw new RuntimeException('Error GraphQL Cloudflare (Diario): ' . ($dailyRes['errors'][0]['message'] ?? 'Error desconocido'));
        }

        $rawDaily = $dailyRes['data']['viewer']['zones'][0]['httpRequests1dGroups'] ?? [];

        return $this->validateAndNormalizeCloudflareData($aggGroup, $rawDaily, $dateStart, $dateEnd);
    }

    public function validateAndNormalizeCloudflareData(array $aggGroup, array $rawDaily, string $dateStart, string $dateEnd): array
    {
        // 1. Validate mandatory aggregated fields
        if (!isset($aggGroup['sum']['requests'], $aggGroup['sum']['pageViews'], $aggGroup['uniq']['uniques'], $aggGroup['sum']['bytes'])) {
            throw new RuntimeException('Datos incompletos de Cloudflare: falta requests, pageViews, uniques o bytes en el grupo consolidado.');
        }

        // 2. Validate exact 7 days
        if (count($rawDaily) !== 7) {
            $count = count($rawDaily);
            throw new RuntimeException("Datos incompletos de Cloudflare: se esperaban exactamente 7 días en el desglose diario, recibidos {$count}.");
        }

        // 3. Validate continuity
        $expectedCurrent = Carbon::parse($dateStart);
        $dailyRows = [];

        foreach ($rawDaily as $row) {
            $date = $row['dimensions']['date'] ?? null;
            if (!$date || !isset($row['sum']['requests'], $row['sum']['pageViews'], $row['uniq']['uniques'])) {
                throw new RuntimeException('Datos incompletos de Cloudflare: un registro diario carece de fecha o métricas requeridas.');
            }
            if ($date !== $expectedCurrent->format('Y-m-d')) {
                throw new RuntimeException("Discontinuidad en días de Cloudflare: se esperaba la fecha {$expectedCurrent->format('Y-m-d')}, recibido {$date}.");
            }

            $dailyRows[] = [
                'date' => $date,
                'requests' => (int) $row['sum']['requests'],
                'page_views' => (int) $row['sum']['pageViews'],
                'uniques' => (int) $row['uniq']['uniques'],
                'threats' => (int) ($row['sum']['threats'] ?? 0),
                'bytes' => (int) ($row['sum']['bytes'] ?? 0),
                'cached_requests' => (int) ($row['sum']['cachedRequests'] ?? 0),
            ];
            $expectedCurrent->addDay();
        }

        return [
            'source' => 'Cloudflare GraphQL Analytics (httpRequests1dGroups)',
            'zone_name' => 'sevenrockradio.com',
            'timezone' => 'UTC',
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'requests' => (int) $aggGroup['sum']['requests'],
            'page_views' => (int) $aggGroup['sum']['pageViews'],
            'unique_visitors' => (int) $aggGroup['uniq']['uniques'],
            'threats' => (int) ($aggGroup['sum']['threats'] ?? 0),
            'bytes' => (int) ($aggGroup['sum']['bytes'] ?? 0),
            'cached_requests' => (int) ($aggGroup['sum']['cachedRequests'] ?? 0),
            'methodology_note' => 'unique_visitors representa la estimación agregada del período reportada por Cloudflare en httpRequests1dGroups. pageViews mide solicitudes con respuesta HTTP 200 de tipo text/html. requests mide la totalidad de solicitudes gestionadas en el edge.',
            'days' => $dailyRows,
        ];
    }

    protected function runCloudflareGraphQL(string $query, array $variables, string $token): array
    {
        $ch = curl_init('https://api.cloudflare.com/client/v4/graphql');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'variables' => $variables]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'User-Agent: SevenRockAuditor/1.0'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($res === false) {
            throw new RuntimeException('Fallo al conectar con Cloudflare GraphQL: ' . $err);
        }

        $decoded = json_decode($res, true);
        return is_array($decoded) ? $decoded : ['errors' => [['message' => 'Respuesta no parseable']]];
    }

    public function generateReport(int $year, int $week, bool $dryRun = false, bool $force = false, ?array $mockData = null): array
    {
        $this->ensureDirectories();
        $id = $this->reportId($year, $week);

        // Mutex lock to prevent concurrent executions
        $lockPath = $this->storageBase . DIRECTORY_SEPARATOR . 'generation.lock';
        $lockFp = fopen($lockPath, 'c+');
        if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
            fclose($lockFp);
            throw new RuntimeException('Bloqueo activo: otra generación de informe está en curso actualmente.');
        }

        try {
            if (!$force && $this->hasReport($year, $week)) {
                throw new RuntimeException("El informe para la semana {$id} ya existe. Usa force para sobrescribir.");
            }

            // Calculate ISO week boundaries
            $startOfWeek = Carbon::now('Europe/Madrid')->setISODate($year, $week, 1)->startOfDay();
            $endOfWeek = Carbon::now('Europe/Madrid')->setISODate($year, $week, 7)->endOfDay();
            $from = $startOfWeek->format('Y-m-d');
            $to = $endOfWeek->format('Y-m-d');

            $periodLabel = $this->formatPeriodSpanish($startOfWeek, $endOfWeek, $week, $year);

            // Fetch sources
            if ($mockData !== null) {
                if (!isset($mockData['radioboss'], $mockData['cloudflare'])) {
                    throw new RuntimeException('Muestra incompleta: se requiere estructura completa de RadioBOSS y Cloudflare.');
                }
                $stationId = (string) config('player.radioboss.station_id', env('RADIOBOSS_STATION_ID', '569'));
                $radioData = $this->validateAndNormalizeRadioBossData($mockData['radioboss'], $stationId, $from, $to);
                $agg = $mockData['cloudflare']['agg'] ?? [
                    'sum' => [
                        'requests' => $mockData['cloudflare']['requests'] ?? null,
                        'pageViews' => $mockData['cloudflare']['page_views'] ?? null,
                        'bytes' => $mockData['cloudflare']['bytes'] ?? null,
                        'threats' => $mockData['cloudflare']['threats'] ?? 0,
                        'cachedRequests' => $mockData['cloudflare']['cached_requests'] ?? 0,
                    ],
                    'uniq' => [
                        'uniques' => $mockData['cloudflare']['unique_visitors'] ?? null,
                    ]
                ];
                $rawDaily = $mockData['cloudflare']['days_raw'] ?? array_map(function ($d) {
                    return [
                        'dimensions' => ['date' => $d['date'] ?? null],
                        'sum' => [
                            'requests' => $d['requests'] ?? null,
                            'pageViews' => $d['page_views'] ?? null,
                            'threats' => $d['threats'] ?? 0,
                            'bytes' => $d['bytes'] ?? 0,
                            'cachedRequests' => $d['cached_requests'] ?? 0,
                        ],
                        'uniq' => [
                            'uniques' => $d['uniques'] ?? null,
                        ]
                    ];
                }, $mockData['cloudflare']['days'] ?? []);

                $cfData = $this->validateAndNormalizeCloudflareData($agg, $rawDaily, $from, $to);
            } else {
                $radioData = $this->fetchRadioBossStats($from, $to);
                $cfData = $this->fetchCloudflareStats($from, $to);
            }

            $report = [
                'id' => $id,
                'year' => $year,
                'week' => $week,
                'title' => 'Informe de audiencia de Seven Rock Radio, elaborado con datos de RadioBOSS y Cloudflare',
                'generated_at' => Carbon::now('Europe/Madrid')->toIso8601String(),
                'period' => [
                    'label' => $periodLabel,
                    'start_date' => $from,
                    'end_date' => $to,
                ],
                'radioboss' => $radioData,
                'cloudflare' => $cfData,
                'metadata' => [
                    'disclaimer' => 'Informe de audiencia de Seven Rock Radio, elaborado con datos de RadioBOSS y Cloudflare. Datos globales del servicio; no atribuibles a bandas individuales ni certificados por auditoría independiente.',
                    'timezone_note' => 'RadioBOSS consolida la emisión de audio en horario de Madrid (Europe/Madrid), mientras que Cloudflare agrega las métricas perimetrales en tiempo universal coordinado (UTC).',
                    'generator_version' => '1.1.0',
                ],
            ];

            // Compile and validate PDF before saving anything
            $pdfContent = $this->compilePdf($report);
            if (empty($pdfContent) || !str_starts_with($pdfContent, '%PDF-') || strlen($pdfContent) < 1500) {
                throw new RuntimeException('La compilación del PDF falló o produjo un documento inválido o truncado. Abortando sin alterar el histórico.');
            }

            if ($dryRun) {
                $report['dry_run'] = true;
                $report['pdf_size_bytes'] = strlen($pdfContent);
                return $report;
            }

            // Safe Atomic writing via temp files + rename
            $tmpJsonPath = $this->reportsDir . DIRECTORY_SEPARATOR . $id . '.tmp.' . uniqid('', true) . '.json';
            $tmpPdfPath = $this->pdfsDir . DIRECTORY_SEPARATOR . $id . '.tmp.' . uniqid('', true) . '.pdf';
            $finalJsonPath = $this->reportsDir . DIRECTORY_SEPARATOR . $id . '.json';
            $finalPdfPath = $this->pdfsDir . DIRECTORY_SEPARATOR . $id . '.pdf';

            $jsonString = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (file_put_contents($tmpJsonPath, $jsonString) === false) {
                throw new RuntimeException('Error de escritura en archivo temporal JSON.');
            }

            if (file_put_contents($tmpPdfPath, $pdfContent) === false) {
                @unlink($tmpJsonPath);
                throw new RuntimeException('Error de escritura en archivo temporal PDF.');
            }

            // Verify temporary files are valid and readable before publishing
            if (!File::exists($tmpJsonPath) || File::size($tmpJsonPath) === 0) {
                @unlink($tmpJsonPath);
                @unlink($tmpPdfPath);
                throw new RuntimeException('El archivo temporal JSON quedó vacío o corrupto.');
            }

            if (!File::exists($tmpPdfPath) || File::size($tmpPdfPath) < 1500) {
                @unlink($tmpJsonPath);
                @unlink($tmpPdfPath);
                throw new RuntimeException('El archivo temporal PDF quedó vacío o truncado.');
            }

            // Publish atomically via rename
            if (!rename($tmpJsonPath, $finalJsonPath)) {
                @unlink($tmpJsonPath);
                @unlink($tmpPdfPath);
                throw new RuntimeException('Fallo al renombrar archivo JSON final.');
            }

            if (!rename($tmpPdfPath, $finalPdfPath)) {
                @unlink($tmpPdfPath);
                throw new RuntimeException('Fallo al renombrar archivo PDF final.');
            }

            // Update latest.json only if this report is chronologically the latest
            $this->updateLatestPointerIfChronologicallyNewer($id, $year, $week);

            $report['pdf_path'] = $finalPdfPath;
            return $report;
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    protected function updateLatestPointerIfChronologicallyNewer(string $id, int $year, int $week): void
    {
        $latestPointer = $this->reportsDir . DIRECTORY_SEPARATOR . 'latest.json';
        $shouldUpdate = true;

        if (File::exists($latestPointer)) {
            $currentLatest = json_decode(File::get($latestPointer), true);
            if (is_array($currentLatest) && isset($currentLatest['year'], $currentLatest['week'])) {
                $curYear = (int) $currentLatest['year'];
                $curWeek = (int) $currentLatest['week'];

                // If the generated report is older than the current latest pointer, do NOT replace latest.json
                if ($year < $curYear || ($year === $curYear && $week < $curWeek)) {
                    $shouldUpdate = false;
                }
            }
        }

        if ($shouldUpdate) {
            $tmpPointer = $this->reportsDir . DIRECTORY_SEPARATOR . 'latest.tmp.' . uniqid('', true) . '.json';
            $payload = json_encode([
                'id' => $id,
                'year' => $year,
                'week' => $week,
                'updated_at' => Carbon::now('Europe/Madrid')->toIso8601String()
            ], JSON_PRETTY_PRINT);

            file_put_contents($tmpPointer, $payload);
            rename($tmpPointer, $latestPointer);
        }
    }

    public function compilePdf(array $report): string
    {
        $html = view('audience.pdf', compact('report'))->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function dailyReportId(string $date): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException("Formato de fecha inválido para informe diario: {$date}");
        }
        return $date;
    }

    public function hasDailyReport(string $date): bool
    {
        $this->ensureDirectories();
        $file = $this->dailyReportsDir . DIRECTORY_SEPARATOR . $this->dailyReportId($date) . '.json';
        return File::exists($file);
    }

    public function getDailyReport(string $date): ?array
    {
        $this->ensureDirectories();
        $path = $this->dailyReportsDir . DIRECTORY_SEPARATOR . $this->dailyReportId($date) . '.json';
        if (!File::exists($path)) {
            return null;
        }
        $data = json_decode(File::get($path), true);
        return is_array($data) ? $data : null;
    }

    public function getDailyPdfPath(string $date): ?string
    {
        $this->ensureDirectories();
        $path = $this->dailyPdfsDir . DIRECTORY_SEPARATOR . $this->dailyReportId($date) . '.pdf';
        return File::exists($path) ? realpath($path) : null;
    }

    public function isClosedDay(string $date): bool
    {
        $yesterday = Carbon::yesterday('Europe/Madrid')->format('Y-m-d');
        return $date <= $yesterday;
    }

    public function formatDateSpanish(string $date): string
    {
        $d = Carbon::parse($date);
        $dias = [
            1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves',
            5 => 'viernes', 6 => 'sábado', 0 => 'domingo'
        ];
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
        ];

        $diaSemana = $dias[(int) $d->dayOfWeek];
        $dia = (int) $d->format('j');
        $mes = $meses[(int) $d->format('n')];
        $ano = (int) $d->format('Y');

        return sprintf('%s, %d de %s de %d', $diaSemana, $dia, $mes, $ano);
    }

    public function fetchRadioBossDailyStats(string $date): array
    {
        $apiUrl = rtrim((string) config('player.radioboss.api_url', env('RADIOBOSS_API_URL', 'https://c30.radioboss.fm')), '/');
        $stationId = (string) config('player.radioboss.station_id', env('RADIOBOSS_STATION_ID', '569'));
        $apiKey = (string) config('player.radioboss.api_key', env('RADIOBOSS_API_KEY', ''));

        if (empty($apiKey)) {
            throw new RuntimeException('RadioBOSS API Key no configurada en el sistema.');
        }

        $url = "{$apiUrl}/api/getstats/{$stationId}?key={$apiKey}&range=custom&from={$date}&to={$date}";

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\nUser-Agent: SevenRockAuditor/1.0\r\n",
                'timeout' => 20,
                'ignore_errors' => true
            ]
        ]);

        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) {
            throw new RuntimeException('Error de conexión con la API de RadioBOSS para el día solicitado.');
        }

        $data = json_decode($res, true);
        if (!is_array($data)) {
            throw new RuntimeException('Respuesta no válida o no JSON de RadioBOSS.');
        }

        return $this->validateAndNormalizeRadioBossDailyData($data, $stationId, $date);
    }

    public function validateAndNormalizeRadioBossDailyData(array $data, string $stationId, string $date): array
    {
        $requiredKeys = ['total_sessions', 'total_tlh', 'total_unique'];
        foreach ($requiredKeys as $rk) {
            if (!array_key_exists($rk, $data) || $data[$rk] === null) {
                throw new RuntimeException("Datos diarios incompletos de RadioBOSS: falta el campo obligatorio '{$rk}'.");
            }
        }

        if (isset($data['days']) && is_array($data['days']) && !empty($data['days'])) {
            $matchingDay = false;
            foreach ($data['days'] as $day) {
                if (isset($day['date']) && $day['date'] === $date) {
                    $matchingDay = true;
                    break;
                }
            }
            if (!$matchingDay) {
                throw new RuntimeException("Discrepancia en la fecha devuelta por RadioBOSS: no contiene datos para {$date}.");
            }
        }

        return [
            'source' => 'RadioBOSS Cloud API (/api/getstats)',
            'station_id' => $stationId,
            'timezone' => 'Europe/Madrid',
            'date' => $date,
            'formatted_date' => $this->formatDateSpanish($date),
            'total_sessions' => (int) $data['total_sessions'],
            'total_tlh' => (int) $data['total_tlh'],
            'total_unique' => (int) $data['total_unique'],
            'disclaimer' => 'Audiencia global de Seven Rock Radio durante el día seleccionado. No corresponde exclusivamente a un programa.',
            'countries' => array_slice($data['countries'] ?? [], 0, 10),
            'countries_tlh' => array_slice($data['countries_tlh'] ?? [], 0, 10),
            'countries_unique' => array_slice($data['unique'] ?? [], 0, 10),
        ];
    }

    public function generateDailyReport(string $date, bool $dryRun = false, bool $force = false, ?array $mockData = null): array
    {
        $this->ensureDirectories();
        $id = $this->dailyReportId($date);

        if (!$this->isClosedDay($date)) {
            throw new RuntimeException("Solo se pueden generar informes para días completos cerrados. La fecha {$date} no está cerrada.");
        }

        $lockPath = $this->dailyLocksDir . DIRECTORY_SEPARATOR . "{$id}.lock";
        $lockFp = fopen($lockPath, 'c+');
        if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
            fclose($lockFp);
            throw new RuntimeException("Bloqueo activo: la obtención del informe para el día {$id} ya está en curso.");
        }

        try {
            if (!$force && $this->hasDailyReport($date)) {
                throw new RuntimeException("El informe para el día {$id} ya existe en disco. Usa force para sobrescribir.");
            }

            $stationId = (string) config('player.radioboss.station_id', env('RADIOBOSS_STATION_ID', '569'));

            if ($mockData !== null) {
                $radioData = $this->validateAndNormalizeRadioBossDailyData($mockData, $stationId, $date);
            } else {
                $radioData = $this->fetchRadioBossDailyStats($date);
            }

            $formattedDate = $this->formatDateSpanish($date);

            $report = [
                'id' => $id,
                'date' => $date,
                'formatted_date' => $formattedDate,
                'title' => "Audiencia diaria de Seven Rock Radio ({$formattedDate})",
                'generated_at' => Carbon::now('Europe/Madrid')->toIso8601String(),
                'radioboss' => $radioData,
                'metadata' => [
                    'disclaimer' => 'Audiencia global de Seven Rock Radio durante el día seleccionado. No corresponde exclusivamente a un programa.',
                    'timezone' => 'Europe/Madrid',
                    'station_id' => $stationId,
                    'generator_version' => '1.1.0',
                ],
            ];

            $pdfContent = $this->compileDailyPdf($report);
            if (empty($pdfContent) || !str_starts_with($pdfContent, '%PDF-') || strlen($pdfContent) < 1500) {
                throw new RuntimeException('La compilación del PDF diario produjo un documento inválido o truncado.');
            }

            if ($dryRun) {
                $report['dry_run'] = true;
                $report['pdf_size_bytes'] = strlen($pdfContent);
                return $report;
            }

            $tmpJsonPath = $this->dailyReportsDir . DIRECTORY_SEPARATOR . $id . '.tmp.' . uniqid('', true) . '.json';
            $tmpPdfPath = $this->dailyPdfsDir . DIRECTORY_SEPARATOR . $id . '.tmp.' . uniqid('', true) . '.pdf';
            $finalJsonPath = $this->dailyReportsDir . DIRECTORY_SEPARATOR . $id . '.json';
            $finalPdfPath = $this->dailyPdfsDir . DIRECTORY_SEPARATOR . $id . '.pdf';

            $jsonString = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (file_put_contents($tmpJsonPath, $jsonString) === false) {
                throw new RuntimeException('Error de escritura en archivo temporal JSON diario.');
            }

            if (file_put_contents($tmpPdfPath, $pdfContent) === false) {
                @unlink($tmpJsonPath);
                throw new RuntimeException('Error de escritura en archivo temporal PDF diario.');
            }

            if (!File::exists($tmpJsonPath) || File::size($tmpJsonPath) === 0) {
                @unlink($tmpJsonPath);
                @unlink($tmpPdfPath);
                throw new RuntimeException('El archivo temporal JSON diario quedó corrupto.');
            }

            if (!File::exists($tmpPdfPath) || File::size($tmpPdfPath) < 1500) {
                @unlink($tmpJsonPath);
                @unlink($tmpPdfPath);
                throw new RuntimeException('El archivo temporal PDF diario quedó truncado.');
            }

            if (!rename($tmpJsonPath, $finalJsonPath)) {
                @unlink($tmpJsonPath);
                @unlink($tmpPdfPath);
                throw new RuntimeException('Fallo al renombrar archivo JSON diario final.');
            }

            if (!rename($tmpPdfPath, $finalPdfPath)) {
                @unlink($tmpPdfPath);
                throw new RuntimeException('Fallo al renombrar archivo PDF diario final.');
            }

            $report['pdf_path'] = $finalPdfPath;
            return $report;
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    public function compileDailyPdf(array $report): string
    {
        $html = view('audience.daily_pdf', compact('report'))->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
