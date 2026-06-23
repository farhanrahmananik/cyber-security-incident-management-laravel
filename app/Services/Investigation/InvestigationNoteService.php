<?php

namespace App\Services\Investigation;

use App\Models\Incident;
use App\Models\InvestigationNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvestigationNoteService
{
    /**
     * Create an investigation note for the given incident.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Incident $incident, User $author, array $data): InvestigationNote
    {
        return DB::transaction(function () use ($incident, $author, $data): InvestigationNote {
            return InvestigationNote::query()->create([
                'incident_id' => $incident->getKey(),
                'author_id' => $author->getKey(),
                'note' => $data['note'],
            ]);
        });
    }

    /**
     * Update the investigation note body.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(InvestigationNote $note, array $data): InvestigationNote
    {
        return DB::transaction(function () use ($note, $data): InvestigationNote {
            $note->update([
                'note' => $data['note'],
            ]);

            return $note;
        });
    }

    /**
     * Delete the investigation note.
     */
    public function delete(InvestigationNote $note): void
    {
        DB::transaction(function () use ($note): void {
            $note->delete();
        });
    }
}
