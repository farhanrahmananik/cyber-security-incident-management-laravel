<?php

namespace App\Services\Report;

use App\Models\Incident;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityReportExportService
{
    public function __construct(
        private readonly SecurityReportService $securityReportService,
    ) {}

    /**
     * Stream a filtered incident report CSV for the authenticated user.
     *
     * @param  array<string, mixed>  $filters
     */
    public function download(User $user, array $filters = []): StreamedResponse
    {
        $filename = 'security-reports-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($user, $filters): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Incident Number',
                'Title',
                'Status',
                'Category',
                'Severity',
                'Priority',
                'Reporter',
                'Assigned Analyst',
                'Reported At / Created At',
                'Updated At',
            ]);

            $this->securityReportService
                ->incidentQuery($user, $filters)
                ->with(['category', 'severity', 'priority', 'reporter', 'currentAssignee'])
                ->chunkById(500, function ($incidents) use ($handle): void {
                    $incidents->each(function (Incident $incident) use ($handle): void {
                        fputcsv($handle, [
                            $incident->incident_number,
                            $incident->title,
                            $this->humanLabel($incident->status),
                            $incident->category?->name ?? '',
                            $incident->severity?->name ?? '',
                            $incident->priority?->name ?? '',
                            $incident->reporter?->name ?? '',
                            $incident->currentAssignee?->name ?? '',
                            $incident->created_at?->format('Y-m-d H:i:s') ?? '',
                            $incident->updated_at?->format('Y-m-d H:i:s') ?? '',
                        ]);
                    });
                }, 'incidents.id', 'id');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Convert a stored slug/status value to a user-facing label.
     */
    private function humanLabel(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
