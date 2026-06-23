<?php

namespace App\Http\Requests\IncidentSetup;

use App\Models\PriorityLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriorityLevelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('priority-level.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $priorityLevel = $this->route('priorityLevel');
        $priorityLevelId = $priorityLevel instanceof PriorityLevel
            ? $priorityLevel->getKey()
            : $priorityLevel;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('priority_levels', 'name')->ignore($priorityLevelId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
