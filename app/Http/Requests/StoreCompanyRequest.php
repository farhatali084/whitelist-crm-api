<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bin' => ['required', 'string', 'regex:/^\d{12}$/', 'unique:companies,bin'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9 ()\-]{7,31}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'bin.regex' => 'The BIN must be exactly 12 digits.',
            'bin.unique' => 'A company with this BIN already exists.',
            'phone.regex' => 'The phone may contain digits, spaces, brackets, dashes and a leading +.',
        ];
    }
}
