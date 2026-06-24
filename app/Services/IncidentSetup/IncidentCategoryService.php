<?php

namespace App\Services\IncidentSetup;

use App\Models\IncidentCategory;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Str;

class IncidentCategoryService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * Create an incident category with a stable unique slug.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): IncidentCategory
    {
        $data['slug'] = $this->uniqueSlug((string) $data['name']);

        $incidentCategory = IncidentCategory::query()->create($data);

        $this->auditLogService->record(
            event: 'incident_category.created',
            auditable: $incidentCategory,
            newValues: $this->safeValues($incidentCategory),
            request: request(),
        );

        return $incidentCategory;
    }

    /**
     * Update an incident category and regenerate its slug when the name changes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(IncidentCategory $incidentCategory, array $data): IncidentCategory
    {
        $oldValues = $this->safeValues($incidentCategory);

        if (isset($data['name']) && $data['name'] !== $incidentCategory->name) {
            $data['slug'] = $this->uniqueSlug((string) $data['name'], $incidentCategory);
        }

        $incidentCategory->update($data);

        $newValues = $this->safeValues($incidentCategory);
        $changedValues = $this->changedValues($oldValues, $newValues);

        if ($changedValues['old'] !== []) {
            $this->auditLogService->record(
                event: 'incident_category.updated',
                auditable: $incidentCategory,
                oldValues: $changedValues['old'],
                newValues: $changedValues['new'],
                request: request(),
            );
        }

        return $incidentCategory;
    }

    /**
     * Deactivate an incident category without physically deleting it.
     */
    public function deactivate(IncidentCategory $incidentCategory): void
    {
        $wasActive = (bool) $incidentCategory->is_active;

        $incidentCategory->update(['is_active' => false]);

        if ($wasActive === true) {
            $this->auditLogService->record(
                event: 'incident_category.deactivated',
                auditable: $incidentCategory,
                oldValues: ['is_active' => true],
                newValues: ['is_active' => false],
                request: request(),
            );
        }
    }

    /**
     * Generate a unique slug for an incident category.
     */
    private function uniqueSlug(string $name, ?IncidentCategory $ignore = null): string
    {
        $baseSlug = Str::slug($name) ?: 'incident-category';
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
    private function slugExists(string $slug, ?IncidentCategory $ignore = null): bool
    {
        return IncidentCategory::query()
            ->where('slug', $slug)
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->getKey()))
            ->exists();
    }

    /**
     * Return safe incident category fields for audit logging.
     *
     * @return array<string, mixed>
     */
    private function safeValues(IncidentCategory $incidentCategory): array
    {
        return [
            'name' => $incidentCategory->name,
            'slug' => $incidentCategory->slug,
            'description' => $incidentCategory->description,
            'color' => $incidentCategory->color,
            'sort_order' => (int) $incidentCategory->sort_order,
            'is_active' => (bool) $incidentCategory->is_active,
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
