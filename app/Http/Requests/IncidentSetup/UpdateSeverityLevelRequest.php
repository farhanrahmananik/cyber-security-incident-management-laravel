<?php

namespace App\Http\Requests\IncidentSetup;

use App\Models\SeverityLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeverityLevelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('severity-level.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $severityLevel = $this->route('severityLevel');
        $severityLevelId = $severityLevel instanceof SeverityLevel
            ? $severityLevel->getKey()
            : $severityLevel;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('severity_levels', 'name')->ignore($severityLevelId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
