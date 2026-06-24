<?php

namespace App\Services\IncidentSetup;

use App\Models\SeverityLevel;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Str;

class SeverityLevelService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * Create a severity level with a stable unique slug.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SeverityLevel
    {
        $data['slug'] = $this->uniqueSlug((string) $data['name']);

        $severityLevel = SeverityLevel::query()->create($data);

        $this->auditLogService->record(
            event: 'severity_level.created',
            auditable: $severityLevel,
            newValues: $this->safeValues($severityLevel),
            request: request(),
        );

        return $severityLevel;
    }

    /**
     * Update a severity level and regenerate its slug when the name changes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SeverityLevel $severityLevel, array $data): SeverityLevel
    {
        $oldValues = $this->safeValues($severityLevel);

        if (isset($data['name']) && $data['name'] !== $severityLevel->name) {
            $data['slug'] = $this->uniqueSlug((string) $data['name'], $severityLevel);
        }

        $severityLevel->update($data);

        $newValues = $this->safeValues($severityLevel);
        $changedValues = $this->changedValues($oldValues, $newValues);

        if ($changedValues['old'] !== []) {
            $this->auditLogService->record(
                event: 'severity_level.updated',
                auditable: $severityLevel,
                oldValues: $changedValues['old'],
                newValues: $changedValues['new'],
                request: request(),
            );
        }

        return $severityLevel;
    }

    /**
     * Deactivate a severity level without physically deleting it.
     */
    public function deactivate(SeverityLevel $severityLevel): void
    {
        $wasActive = (bool) $severityLevel->is_active;

        $severityLevel->update(['is_active' => false]);

        if ($wasActive === true) {
            $this->auditLogService->record(
                event: 'severity_level.deactivated',
                auditable: $severityLevel,
                oldValues: ['is_active' => true],
                newValues: ['is_active' => false],
                request: request(),
            );
        }
    }

    /**
     * Generate a unique slug for a severity level.
     */
    private function uniqueSlug(string $name, ?SeverityLevel $ignore = null): string
    {
        $baseSlug = Str::slug($name) ?: 'severity-level';
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
    private function slugExists(string $slug, ?SeverityLevel $ignore = null): bool
    {
        return SeverityLevel::query()
            ->where('slug', $slug)
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->getKey()))
            ->exists();
    }

    /**
     * Return safe severity level fields for audit logging.
     *
     * @return array<string, mixed>
     */
    private function safeValues(SeverityLevel $severityLevel): array
    {
        return [
            'name' => $severityLevel->name,
            'slug' => $severityLevel->slug,
            'description' => $severityLevel->description,
            'color' => $severityLevel->color,
            'sort_order' => (int) $severityLevel->sort_order,
            'is_active' => (bool) $severityLevel->is_active,
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
