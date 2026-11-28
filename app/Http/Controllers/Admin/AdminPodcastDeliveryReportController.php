<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PodcastDeliveryReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AdminPodcastDeliveryReportController extends Controller
{
    public function __construct(
        private readonly PodcastDeliveryReportService $reportService,
    ) {
    }

    public function index(Request $request): View
    {
        /** @var User $viewer */
        $viewer = Auth::user();
        $filters = $this->reportService->extractFilters($request, $viewer);
        $report = $this->reportService->getPaginatedReport($request, $viewer, $filters, 25);

        return view('admin.podcast-uploads.deliveries', [
            'paginator' => $report['paginator'],
            'rows' => $report['rows'],
            'filters' => $report['filters'],
            'filterOptions' => $report['filterOptions'],
            'activeFilterLabels' => $report['activeFilterLabels'],
            'appTimezone' => $report['appTimezone'],
            'programTimezones' => $report['programTimezones'],
            'printQuery' => $report['printQuery'],
            'distributionStatusOptions' => PodcastDeliveryReportService::DISTRIBUTION_STATUS_OPTIONS,
            'sortOptions' => PodcastDeliveryReportService::SORT_OPTIONS,
        ]);
    }

    public function print(Request $request): View
    {
        /** @var User $viewer */
        $viewer = Auth::user();
        $filters = $this->reportService->extractFilters($request, $viewer);
        $report = $this->reportService->getPrintReport($request, $viewer, $filters);

        return view('admin.podcast-uploads.deliveries-print', $report);
    }
}

