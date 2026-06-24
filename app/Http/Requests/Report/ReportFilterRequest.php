<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('report.view') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'in:reported,triaged,assigned,investigating,contained,resolved,closed'],
            'severity_id' => ['nullable', 'integer', 'exists:severity_levels,id'],
            'priority_id' => ['nullable', 'integer', 'exists:priority_levels,id'],
            'category_id' => ['nullable', 'integer', 'exists:incident_categories,id'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
