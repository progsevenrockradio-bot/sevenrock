<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDailyAudienceReportJob;
use App\Services\AudienceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AudienceDailyReportController extends Controller
{
    protected AudienceReportService $service;

    public function __construct(AudienceReportService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $maxDate = Carbon::yesterday('Europe/Madrid')->format('Y-m-d');
        $minDate = '2026-08-01';

        $fecha = (string) $request->query('fecha', '');
        $reintentar = $request->boolean('reintentar');

        // Si no se proporcionó fecha, por defecto se muestra el formulario o el día de ayer si existe
        if (empty($fecha)) {
            $fecha = $maxDate;
            if ($this->service->hasDailyReport($fecha)) {
                return view('audience.daily', [
                    'fecha' => $fecha,
                    'minDate' => $minDate,
                    'maxDate' => $maxDate,
                    'status' => 'ready',
                    'report' => $this->service->getDailyReport($fecha),
                    'error' => null,
                    'statusData' => null,
                ]);
            }

            return view('audience.daily', [
                'fecha' => $fecha,
                'minDate' => $minDate,
                'maxDate' => $maxDate,
                'status' => 'initial',
                'report' => null,
                'error' => null,
                'statusData' => null,
            ]);
        }

        // 1. Validar formato de fecha (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return view('audience.daily', [
                'fecha' => $maxDate,
                'minDate' => $minDate,
                'maxDate' => $maxDate,
                'status' => 'error',
                'report' => null,
                'error' => 'El formato de fecha no es válido. Utiliza el selector o el formato AAAA-MM-DD.',
                'statusData' => null,
            ]);
        }

        [$y, $m, $d] = explode('-', $fecha);
        if (!checkdate((int) $m, (int) $d, (int) $y)) {
            return view('audience.daily', [
                'fecha' => $maxDate,
                'minDate' => $minDate,
                'maxDate' => $maxDate,
                'status' => 'error',
                'report' => null,
                'error' => 'La fecha introducida no existe en el calendario.',
                'statusData' => null,
            ]);
        }

        // 2. Validar que sea un día cerrado (estrictamente <= ayer)
        if ($fecha > $maxDate) {
            return view('audience.daily', [
                'fecha' => $fecha,
                'minDate' => $minDate,
                'maxDate' => $maxDate,
                'status' => 'error',
                'report' => null,
                'error' => 'Solo es posible consultar días completos ya cerrados. La jornada de hoy sigue en emisión y sus estadísticas no son definitivas.',
                'statusData' => null,
            ]);
        }

        // 3. Validar rango histórico disponible
        if ($fecha < $minDate) {
            return view('audience.daily', [
                'fecha' => $fecha,
                'minDate' => $minDate,
                'maxDate' => $maxDate,
                'status' => 'error',
                'report' => null,
                'error' => "La fecha seleccionada ({$fecha}) es anterior al período histórico disponible (a partir del {$minDate}).",
                'statusData' => null,
            ]);
        }

        // 4. Si el informe ya existe en almacenamiento físico, se sirve de inmediato
        if ($this->service->hasDailyReport($fecha)) {
            $report = $this->service->getDailyReport($fecha);
            return view('audience.daily', [
                'fecha' => $fecha,
                'minDate' => $minDate,
                'maxDate' => $maxDate,
                'status' => 'ready',
                'report' => $report,
                'error' => null,
                'statusData' => ['status' => 'ready'],
            ]);
        }

        // 5. Control de estados y despacho en cola exclusiva 'audience'
        $statusKey = GenerateDailyAudienceReportJob::statusKey($fecha);
        $statusData = Cache::get($statusKey);

        // Si se solicita reintento explícito o el estado previo fue de error/timeout
        $shouldDispatch = $reintentar || !$statusData || in_array($statusData['status'] ?? '', ['failed', 'timeout'], true);

        if ($shouldDispatch) {
            $now = now()->timestamp;
            $statusData = [
                'status' => 'pending',
                'started_at' => $now,
                'updated_at' => $now,
                'error' => null,
            ];
            Cache::put($statusKey, $statusData, 180);

            GenerateDailyAudienceReportJob::dispatch($fecha)->onQueue('audience');
        }

        // Si el driver de cola fue síncrono (ej. pruebas o configuración local), verificar si ya está listo
        if ($this->service->hasDailyReport($fecha)) {
            $report = $this->service->getDailyReport($fecha);
            return view('audience.daily', [
                'fecha' => $fecha,
                'minDate' => $minDate,
                'maxDate' => $maxDate,
                'status' => 'ready',
                'report' => $report,
                'error' => null,
                'statusData' => ['status' => 'ready'],
            ]);
        }

        $currentStatus = $statusData['status'] ?? 'pending';

        return view('audience.daily', [
            'fecha' => $fecha,
            'minDate' => $minDate,
            'maxDate' => $maxDate,
            'status' => $currentStatus,
            'report' => null,
            'error' => $statusData['error'] ?? null,
            'statusData' => $statusData,
        ]);
    }

    public function status(Request $request)
    {
        $fecha = (string) $request->query('fecha', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return response()->json(['status' => 'error', 'message' => 'Fecha no válida.'], 422);
        }

        if ($this->service->hasDailyReport($fecha)) {
            return response()->json([
                'status' => 'ready',
                'date' => $fecha,
                'updated_at' => now()->timestamp,
            ]);
        }

        $statusKey = GenerateDailyAudienceReportJob::statusKey($fecha);
        $statusData = Cache::get($statusKey, [
            'status' => 'pending',
            'started_at' => now()->timestamp,
            'updated_at' => now()->timestamp,
            'error' => null,
        ]);

        $elapsed = now()->timestamp - ($statusData['started_at'] ?? now()->timestamp);

        // Límite de espera de 35 segundos para evitar sondeo infinito
        if ($elapsed > 35 && in_array($statusData['status'], ['pending', 'processing'], true)) {
            $statusData['status'] = 'timeout';
            $statusData['updated_at'] = now()->timestamp;
            $statusData['error'] = 'El tiempo límite de espera para generar el informe ha sido superado.';
            Cache::put($statusKey, $statusData, 60);
        }

        return response()->json([
            'status' => $statusData['status'],
            'date' => $fecha,
            'started_at' => $statusData['started_at'] ?? null,
            'updated_at' => $statusData['updated_at'] ?? null,
            'error' => $statusData['error'] ?? null,
            'elapsed' => $elapsed,
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $fecha = (string) $request->query('fecha', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            abort(404, 'Fecha no válida para descarga.');
        }

        $pdfPath = $this->service->getDailyPdfPath($fecha);
        if (!$pdfPath || !file_exists($pdfPath)) {
            abort(404, "El informe PDF para el día {$fecha} no está disponible.");
        }

        $filename = "informe-audiencia-diaria-sevenrock-{$fecha}.pdf";

        return response()->streamDownload(function () use ($pdfPath) {
            readfile($pdfPath);
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
