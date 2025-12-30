<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SchoolRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Determine the central connection name
        // The central DB is typically 'mysql', but fall back to default if needed
        $centralConnection = 'mysql';
        if (!array_key_exists('mysql', config('database.connections', []))) {
            $centralConnection = config('database.default');
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'subdomain' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/',
                // Explicitly use central connection for uniqueness check
                // Format: unique:connection.table,column
                "unique:{$centralConnection}.schools,subdomain",
            ],
            'contact_email' => ['required', 'email', 'max:255'],
            // Nigerian phone numbers: 070, 080, 081, 090, 091, etc. (11 digits starting with 0)
            'contact_phone' => ['required', 'string', 'regex:/^0[789]\\d{9}$/'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('subdomain')) {
            $this->merge([
                'subdomain' => strtolower(trim((string) $this->input('subdomain'))),
            ]);
        }

        if ($this->has('contact_email')) {
            $this->merge([
                'contact_email' => strtolower(trim((string) $this->input('contact_email'))),
            ]);
        }

        if ($this->has('contact_phone')) {
            $digits = preg_replace('/\\D+/', '', (string) $this->input('contact_phone'));
            $this->merge(['contact_phone' => $digits]);
        }
    }
}


