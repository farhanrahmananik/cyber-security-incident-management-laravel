<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportFilterRequest;
use App\Services\Report\SecurityReportExportService;
use App\Services\Report\SecurityReportService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityReportController extends Controller
{
    /**
     * Display the security reports page.
     */
    public function index(
        ReportFilterRequest $request,
        SecurityReportService $securityReportService,
    ): View {
        return view('reports.security.index', [
            'reportData' => $securityReportService->build($request->user(), $request->validated()),
        ]);
    }

    /**
     * Export the filtered security report as CSV.
     */
    public function export(
        ReportFilterRequest $request,
        SecurityReportExportService $securityReportExportService,
    ): StreamedResponse {
        return $securityReportExportService->download($request->user(), $request->validated());
    }
}
