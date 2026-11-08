<?php

namespace App\Http\Controllers;

use App\Services\AudienceReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AudienceReportController extends Controller
{
    protected AudienceReportService $audienceService;

    public function __construct(AudienceReportService $audienceService)
    {
        $this->audienceService = $audienceService;
    }

    public function index()
    {
        $report = $this->audienceService->getLatestReport();

        return view('audience.index', [
            'report' => $report,
            'isHistorical' => false,
        ]);
    }

    public function downloadLatestPdf()
    {
        $report = $this->audienceService->getLatestReport();
        if (!$report) {
            abort(404, 'No hay informes de audiencia disponibles para descargar.');
        }

        $pdfPath = $this->audienceService->getPdfPath((int) $report['year'], (int) $report['week']);
        if (!$pdfPath || !file_exists($pdfPath)) {
            abort(404, 'El archivo PDF del último informe no está disponible.');
        }

        $filename = sprintf('informe-audiencia-sevenrock-%04d-W%02d.pdf', $report['year'], $report['week']);

        return response()->streamDownload(function () use ($pdfPath) {
            readfile($pdfPath);
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function history(Request $request)
    {
        $page = (int) $request->get('page', 1);
        $paginator = $this->audienceService->listReports($page, 10);

        return view('audience.history', [
            'reports' => $paginator,
        ]);
    }

    public function show(int $year, int $week)
    {
        $report = $this->audienceService->getReport($year, $week);
        if (!$report) {
            abort(404, "El informe para el año {$year} y semana {$week} no existe.");
        }

        return view('audience.index', [
            'report' => $report,
            'isHistorical' => true,
        ]);
    }

    public function downloadPdf(int $year, int $week)
    {
        $pdfPath = $this->audienceService->getPdfPath($year, $week);
        if (!$pdfPath || !file_exists($pdfPath)) {
            abort(404, "El archivo PDF para el año {$year} y semana {$week} no está disponible.");
        }

        $filename = sprintf('informe-audiencia-sevenrock-%04d-W%02d.pdf', $year, $week);

        return response()->streamDownload(function () use ($pdfPath) {
            readfile($pdfPath);
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
