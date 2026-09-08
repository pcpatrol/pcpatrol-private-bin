<?php

namespace App\Http\Requests;

use App\Models\Paste;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePasteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payload' => ['required', 'string', 'max:'.config('paste.max_payload_bytes')],
            'format' => ['required', 'string', Rule::in(Paste::FORMATS)],
            'has_password' => ['required', 'boolean'],
            'burn_after_reading' => ['required', 'boolean'],
            'expires_in' => ['required', 'string', Rule::in(array_keys(config('paste.expiry_options')))],
        ];
    }

    /**
     * Get the number of seconds the paste should be retained for.
     */
    public function retentionSeconds(): ?int
    {
        return config('paste.expiry_options')[$this->string('expires_in')->toString()];
    }
}
