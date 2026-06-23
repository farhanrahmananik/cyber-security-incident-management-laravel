<?php

namespace App\Services\IncidentSetup;

use App\Models\PriorityLevel;
use Illuminate\Support\Str;

class PriorityLevelService
{
    /**
     * Create a priority level with a stable unique slug.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PriorityLevel
    {
        $data['slug'] = $this->uniqueSlug((string) $data['name']);

        return PriorityLevel::query()->create($data);
    }

    /**
     * Update a priority level and regenerate its slug when the name changes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PriorityLevel $priorityLevel, array $data): PriorityLevel
    {
        if (isset($data['name']) && $data['name'] !== $priorityLevel->name) {
            $data['slug'] = $this->uniqueSlug((string) $data['name'], $priorityLevel);
        }

        $priorityLevel->update($data);

        return $priorityLevel;
    }

    /**
     * Deactivate a priority level without physically deleting it.
     */
    public function deactivate(PriorityLevel $priorityLevel): void
    {
        $priorityLevel->update(['is_active' => false]);
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
}
