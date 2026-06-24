<?php

namespace App\Http\Requests\ResponseAction;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResponseActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('response-action.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'action_type' => ['required', 'string', 'in:containment,eradication,recovery,communication,monitoring,lessons_learned,other'],
            'status' => ['required', 'string', 'in:planned,in_progress,completed,cancelled'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }
}
