<?php

namespace App\Services\IncidentSetup;

use App\Models\PriorityLevel;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Str;

class PriorityLevelService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * Create a priority level with a stable unique slug.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PriorityLevel
    {
        $data['slug'] = $this->uniqueSlug((string) $data['name']);

        $priorityLevel = PriorityLevel::query()->create($data);

        $this->auditLogService->record(
            event: 'priority_level.created',
            auditable: $priorityLevel,
            newValues: $this->safeValues($priorityLevel),
            request: request(),
        );

        return $priorityLevel;
    }

    /**
     * Update a priority level and regenerate its slug when the name changes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PriorityLevel $priorityLevel, array $data): PriorityLevel
    {
        $oldValues = $this->safeValues($priorityLevel);

        if (isset($data['name']) && $data['name'] !== $priorityLevel->name) {
            $data['slug'] = $this->uniqueSlug((string) $data['name'], $priorityLevel);
        }

        $priorityLevel->update($data);

        $newValues = $this->safeValues($priorityLevel);
        $changedValues = $this->changedValues($oldValues, $newValues);

        if ($changedValues['old'] !== []) {
            $this->auditLogService->record(
                event: 'priority_level.updated',
                auditable: $priorityLevel,
                oldValues: $changedValues['old'],
                newValues: $changedValues['new'],
                request: request(),
            );
        }

        return $priorityLevel;
    }

    /**
     * Deactivate a priority level without physically deleting it.
     */
    public function deactivate(PriorityLevel $priorityLevel): void
    {
        $wasActive = (bool) $priorityLevel->is_active;

        $priorityLevel->update(['is_active' => false]);

        if ($wasActive === true) {
            $this->auditLogService->record(
                event: 'priority_level.deactivated',
                auditable: $priorityLevel,
                oldValues: ['is_active' => true],
                newValues: ['is_active' => false],
                request: request(),
            );
        }
    }

    /**
     * Generate a unique slug for a priority level.
     */
    private function uniqueSlug(string $name, ?PriorityLevel $ignore = null): string
    {
        $baseSlug = Str::slug($name) ?: 'priority-level';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $ignore)) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Determine if the slug already exists.
     */
    private function slugExists(string $slug, ?PriorityLevel $ignore = null): bool
    {
        return PriorityLevel::query()
            ->where('slug', $slug)
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->getKey()))
            ->exists();
    }

    /**
     * Return safe priority level fields for audit logging.
     *
     * @return array<string, mixed>
     */
    private function safeValues(PriorityLevel $priorityLevel): array
    {
        return [
            'name' => $priorityLevel->name,
            'slug' => $priorityLevel->slug,
            'description' => $priorityLevel->description,
            'color' => $priorityLevel->color,
            'sort_order' => (int) $priorityLevel->sort_order,
            'is_active' => (bool) $priorityLevel->is_active,
        ];
    }

    /**
     * Extract changed audit values from two safe snapshots.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    private function changedValues(array $oldValues, array $newValues): array
    {
        $old = [];
        $new = [];

        foreach ($newValues as $key => $value) {
            if (($oldValues[$key] ?? null) === $value) {
                continue;
            }

            $old[$key] = $oldValues[$key] ?? null;
            $new[$key] = $value;
        }

        return ['old' => $old, 'new' => $new];
    }
}
