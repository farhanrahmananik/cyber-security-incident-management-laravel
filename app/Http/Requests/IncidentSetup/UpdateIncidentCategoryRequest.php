<?php

namespace App\Http\Requests\IncidentSetup;

use App\Models\IncidentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('incident-category.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $incidentCategory = $this->route('incidentCategory');
        $incidentCategoryId = $incidentCategory instanceof IncidentCategory
            ? $incidentCategory->getKey()
            : $incidentCategory;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('incident_categories', 'name')->ignore($incidentCategoryId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
