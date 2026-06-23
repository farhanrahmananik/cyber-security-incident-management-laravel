<?php

namespace App\Services\IncidentSetup;

use App\Models\SeverityLevel;
use Illuminate\Support\Str;

class SeverityLevelService
{
    /**
     * Create a severity level with a stable unique slug.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SeverityLevel
    {
        $data['slug'] = $this->uniqueSlug((string) $data['name']);

        return SeverityLevel::query()->create($data);
    }

    /**
     * Update a severity level and regenerate its slug when the name changes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SeverityLevel $severityLevel, array $data): SeverityLevel
    {
        if (isset($data['name']) && $data['name'] !== $severityLevel->name) {
            $data['slug'] = $this->uniqueSlug((string) $data['name'], $severityLevel);
        }

        $severityLevel->update($data);

        return $severityLevel;
    }

    /**
     * Deactivate a severity level without physically deleting it.
     */
    public function deactivate(SeverityLevel $severityLevel): void
    {
        $severityLevel->update(['is_active' => false]);
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
}
