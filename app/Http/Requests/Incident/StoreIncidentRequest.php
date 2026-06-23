<?php

namespace App\Http\Requests\Incident;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('incident.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'incident_category_id' => ['required', 'exists:incident_categories,id'],
            'severity_level_id' => ['required', 'exists:severity_levels,id'],
            'priority_level_id' => ['required', 'exists:priority_levels,id'],
            'impact_summary' => ['nullable', 'string'],
            'affected_system' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
            'detected_at' => $this->detectedAtRules(),
        ];
    }

    /**
     * Build detected_at validation rules.
     *
     * @return list<string>
     */
    private function detectedAtRules(): array
    {
        $rules = ['nullable', 'date'];

        if ($this->filled('occurred_at')) {
            $rules[] = 'after_or_equal:occurred_at';
        }

        return $rules;
    }
}
