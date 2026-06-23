<?php

namespace App\Services\IncidentSetup;

use App\Models\IncidentCategory;
use Illuminate\Support\Str;

class IncidentCategoryService
{
    /**
     * Create an incident category with a stable unique slug.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): IncidentCategory
    {
        $data['slug'] = $this->uniqueSlug((string) $data['name']);

        return IncidentCategory::query()->create($data);
    }

    /**
     * Update an incident category and regenerate its slug when the name changes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(IncidentCategory $incidentCategory, array $data): IncidentCategory
    {
        if (isset($data['name']) && $data['name'] !== $incidentCategory->name) {
            $data['slug'] = $this->uniqueSlug((string) $data['name'], $incidentCategory);
        }

        $incidentCategory->update($data);

        return $incidentCategory;
    }

    /**
     * Deactivate an incident category without physically deleting it.
     */
    public function deactivate(IncidentCategory $incidentCategory): void
    {
        $incidentCategory->update(['is_active' => false]);
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
}
