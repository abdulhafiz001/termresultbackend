<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'in:school_admin,teacher,student'],
            'username' => ['nullable', 'string', 'max:255'],
            'admission_number' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}


