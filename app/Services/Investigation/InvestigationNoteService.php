<?php

namespace App\Services\Investigation;

use App\Models\Incident;
use App\Models\InvestigationNote;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class InvestigationNoteService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * Create an investigation note for the given incident.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Incident $incident, User $author, array $data): InvestigationNote
    {
        $note = DB::transaction(function () use ($incident, $author, $data): InvestigationNote {
            return InvestigationNote::query()->create([
                'incident_id' => $incident->getKey(),
                'author_id' => $author->getKey(),
                'note' => $data['note'],
            ]);
        });

        $this->auditLogService->record(
            event: 'investigation_note.created',
            auditable: $note,
            newValues: [
                'incident_id' => $note->incident_id,
                'author_id' => $note->author_id,
                'body_present' => true,
                'body_length' => strlen((string) $note->note),
            ],
            request: request(),
        );

        return $note;
    }

    /**
     * Update the investigation note body.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(InvestigationNote $note, array $data): InvestigationNote
    {
        $oldBody = (string) $note->note;
        $oldLength = strlen((string) $note->note);

        $updatedNote = DB::transaction(function () use ($note, $data): InvestigationNote {
            $note->update([
                'note' => $data['note'],
            ]);

            return $note;
        });

        $newLength = strlen((string) $updatedNote->note);

        if ($oldBody !== (string) $updatedNote->note) {
            $this->auditLogService->record(
                event: 'investigation_note.updated',
                auditable: $updatedNote,
                oldValues: [
                    'body_changed' => false,
                    'body_length' => $oldLength,
                ],
                newValues: [
                    'body_changed' => true,
                    'body_length' => $newLength,
                ],
                request: request(),
            );
        }

        return $updatedNote;
    }

    /**
     * Delete the investigation note.
     */
    public function delete(InvestigationNote $note): void
    {
        DB::transaction(function () use ($note): void {
            $note->delete();
        });

        $this->auditLogService->record(
            event: 'investigation_note.deleted',
            auditable: $note,
            oldValues: ['deleted' => false],
            newValues: ['deleted' => true],
            request: request(),
        );
    }
}
