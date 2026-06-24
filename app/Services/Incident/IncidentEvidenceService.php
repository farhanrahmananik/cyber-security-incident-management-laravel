<?php

namespace App\Services\Incident;

use App\Models\Incident;
use App\Models\IncidentEvidence;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class IncidentEvidenceService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * Create evidence metadata and store the uploaded file.
     *
     * @param  array<string, mixed>  $validated
     */
    public function create(Incident $incident, array $validated, User $uploadedBy): IncidentEvidence
    {
        $file = $validated['evidence_file'] ?? null;

        if (! $file instanceof UploadedFile) {
            throw new InvalidArgumentException('Evidence file is required.');
        }

        $storedPath = null;
        $directory = 'incidents/'.$incident->getKey().'/evidences';
        $checksum = $this->checksum($file);
        $filename = $this->filename($file);

        try {
            $storedPath = $file->storeAs($directory, $filename, 'local');

            if ($storedPath === false) {
                throw new RuntimeException('Unable to store evidence file.');
            }

            $evidence = DB::transaction(function () use ($incident, $uploadedBy, $validated, $file, $storedPath, $checksum): IncidentEvidence {
                return IncidentEvidence::query()->create([
                    'incident_id' => $incident->getKey(),
                    'uploaded_by_id' => $uploadedBy->getKey(),
                    'title' => $this->trimString($validated['title']),
                    'description' => $this->nullableTrimString($validated['description'] ?? null),
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'disk' => 'local',
                    'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                    'file_size' => $file->getSize() ?? 0,
                    'checksum_sha256' => $checksum,
                ]);
            });

            $this->auditLogService->record(
                event: 'incident_evidence.uploaded',
                auditable: $evidence,
                newValues: $this->safeValues($evidence),
                request: request(),
            );

            return $evidence;
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    /**
     * Update evidence metadata only.
     *
     * @param  array<string, mixed>  $validated
     */
    public function update(IncidentEvidence $incidentEvidence, array $validated): IncidentEvidence
    {
        $oldTitle = $incidentEvidence->title;
        $oldDescription = $incidentEvidence->description;

        $updatedEvidence = DB::transaction(function () use ($incidentEvidence, $validated): IncidentEvidence {
            $incidentEvidence->update([
                'title' => $this->trimString($validated['title']),
                'description' => $this->nullableTrimString($validated['description'] ?? null),
            ]);

            return $incidentEvidence;
        });

        $oldValues = [];
        $newValues = [];

        if ($oldTitle !== $updatedEvidence->title) {
            $oldValues['title'] = $oldTitle;
            $newValues['title'] = $updatedEvidence->title;
        }

        if ($oldDescription !== $updatedEvidence->description) {
            $oldValues['description_changed'] = false;
            $newValues['description_changed'] = true;
        }

        if ($oldValues !== []) {
            $this->auditLogService->record(
                event: 'incident_evidence.updated',
                auditable: $updatedEvidence,
                oldValues: $oldValues,
                newValues: $newValues,
                request: request(),
            );
        }

        return $updatedEvidence;
    }

    /**
     * Soft delete evidence metadata while retaining the stored file.
     */
    public function delete(IncidentEvidence $incidentEvidence, User $deletedBy): void
    {
        $oldDeletedAt = $incidentEvidence->deleted_at?->format('Y-m-d H:i:s');

        DB::transaction(function () use ($incidentEvidence, $deletedBy): void {
            $incidentEvidence->update([
                'deleted_by_id' => $deletedBy->getKey(),
            ]);

            $incidentEvidence->delete();
        });

        $this->auditLogService->record(
            event: 'incident_evidence.deleted',
            auditable: $incidentEvidence,
            oldValues: [
                'deleted_at' => $oldDeletedAt,
                'deleted_by_id' => null,
            ],
            newValues: [
                'deleted_at' => $incidentEvidence->deleted_at?->format('Y-m-d H:i:s'),
                'deleted_by_id' => $deletedBy->getKey(),
            ],
            request: request(),
        );
    }

    /**
     * Record a successful evidence download.
     */
    public function recordDownload(IncidentEvidence $incidentEvidence, User $downloadedBy): void
    {
        $this->auditLogService->record(
            event: 'incident_evidence.downloaded',
            auditable: $incidentEvidence,
            newValues: [
                'incident_id' => $incidentEvidence->incident_id,
                'downloaded_by_id' => $downloadedBy->getKey(),
            ],
            request: request(),
        );
    }

    /**
     * Generate a SHA-256 checksum for the uploaded temporary file.
     */
    private function checksum(UploadedFile $file): string
    {
        $path = $file->getRealPath() ?: $file->getPathname();
        $checksum = hash_file('sha256', $path);

        if ($checksum === false) {
            throw new RuntimeException('Unable to calculate evidence checksum.');
        }

        return $checksum;
    }

    /**
     * Generate a safe storage filename.
     */
    private function filename(UploadedFile $file): string
    {
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();

        return Str::uuid()->toString().($extension ? '.'.$extension : '');
    }

    /**
     * Trim a required string value.
     */
    private function trimString(mixed $value): string
    {
        return trim((string) $value);
    }

    /**
     * Trim nullable string values.
     */
    private function nullableTrimString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Return safe evidence metadata for audit logging.
     *
     * @return array<string, mixed>
     */
    private function safeValues(IncidentEvidence $incidentEvidence): array
    {
        return [
            'incident_id' => $incidentEvidence->incident_id,
            'title' => $incidentEvidence->title,
            'mime_type' => $incidentEvidence->mime_type,
            'file_size' => $incidentEvidence->file_size,
            'checksum_sha256' => $incidentEvidence->checksum_sha256,
            'uploaded_by_id' => $incidentEvidence->uploaded_by_id,
        ];
    }
}
