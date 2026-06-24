<?php

namespace App\Services\Incident;

use App\Models\Incident;
use App\Models\IncidentEvidence;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class IncidentEvidenceService
{
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

            return DB::transaction(function () use ($incident, $uploadedBy, $validated, $file, $storedPath, $checksum): IncidentEvidence {
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
        return DB::transaction(function () use ($incidentEvidence, $validated): IncidentEvidence {
            $incidentEvidence->update([
                'title' => $this->trimString($validated['title']),
                'description' => $this->nullableTrimString($validated['description'] ?? null),
            ]);

            return $incidentEvidence;
        });
    }

    /**
     * Soft delete evidence metadata while retaining the stored file.
     */
    public function delete(IncidentEvidence $incidentEvidence, User $deletedBy): void
    {
        DB::transaction(function () use ($incidentEvidence, $deletedBy): void {
            $incidentEvidence->update([
                'deleted_by_id' => $deletedBy->getKey(),
            ]);

            $incidentEvidence->delete();
        });
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
}
