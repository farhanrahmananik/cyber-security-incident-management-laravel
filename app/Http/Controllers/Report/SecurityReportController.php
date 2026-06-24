<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportFilterRequest;
use App\Services\Audit\AuditLogService;
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
        AuditLogService $auditLogService,
    ): View {
        $filters = $request->validated();
        $reportData = $securityReportService->build($request->user(), $filters);

        $auditLogService->record(
            event: 'security_report.viewed',
            newValues: [
                'filters' => $this->safeFilters($filters),
                'summary' => $this->safeSummary($reportData['summary']),
            ],
            request: $request,
        );

        return view('reports.security.index', [
            'reportData' => $reportData,
        ]);
    }

    /**
     * Export the filtered security report as CSV.
     */
    public function export(
        ReportFilterRequest $request,
        SecurityReportExportService $securityReportExportService,
        AuditLogService $auditLogService,
    ): StreamedResponse {
        $filters = $request->validated();
        $response = $securityReportExportService->download($request->user(), $filters);

        $auditLogService->record(
            event: 'security_report.exported',
            newValues: [
                'export_type' => 'csv',
                'filters' => $this->safeFilters($filters),
                'exported_at' => now()->format('Y-m-d H:i:s'),
            ],
            request: $request,
        );

        return $response;
    }

    /**
     * Keep report audit filter payloads compact and limited to validated filter keys.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function safeFilters(array $filters): array
    {
        $safeFilters = [];

        foreach ([
            'date_from',
            'date_to',
            'status',
            'severity_id',
            'priority_id',
            'category_id',
            'assigned_to_id',
        ] as $key) {
            if (! array_key_exists($key, $filters) || blank($filters[$key])) {
                continue;
            }

            $safeFilters[$key] = $filters[$key];
        }

        return $safeFilters;
    }

    /**
     * Keep report audit summary payloads at aggregate count level only.
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, int>
     */
    private function safeSummary(array $summary): array
    {
        return [
            'total_incidents' => (int) ($summary['total_incidents'] ?? 0),
            'open_incidents' => (int) ($summary['open_incidents'] ?? 0),
            'closed_incidents' => (int) ($summary['closed_incidents'] ?? 0),
            'critical_incidents' => (int) ($summary['critical_incidents'] ?? 0),
        ];
    }
}
