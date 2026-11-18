<?php

namespace App\Http\Requests;

use App\Rules\IsoDateTime;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'manager_id' => ['required', 'integer', 'exists:managers,id'],
            'starts_at' => ['required', new IsoDateTime], // duration is fixed (60 min), so no ends_at
        ];
    }
}
