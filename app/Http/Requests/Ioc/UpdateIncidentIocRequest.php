<?php

namespace App\Http\Requests\Ioc;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIncidentIocRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('ioc.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:ip_address,domain,url,file_hash,email_address,malware_filename,process_name,registry_key,other'],
            'value' => ['required', 'string', 'max:2048'],
            'description' => ['nullable', 'string', 'max:5000'],
            'confidence' => ['nullable', 'string', 'in:low,medium,high'],
            'first_seen_at' => ['nullable', 'date'],
            'last_seen_at' => ['nullable', 'date', 'after_or_equal:first_seen_at'],
        ];
    }
}
