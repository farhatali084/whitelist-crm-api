<?php

namespace App\Http\Requests;

use App\Models\Call;
use App\Rules\IsoDateTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'manager_id' => ['required', 'integer', 'exists:managers,id'],
            'result' => ['required', Rule::in(Call::RESULTS)],
            // Rule 5: "meeting_set" must point to a meeting that was actually created;
            // other results must not carry a meeting.
            'meeting_id' => [
                'required_if:result,meeting_set',
                'prohibited_unless:result,meeting_set',
                'nullable', 'integer', 'exists:meetings,id',
            ],
            'comment' => ['nullable', 'string', 'max:1000'],
            'called_at' => ['nullable', new IsoDateTime],
        ];
    }

    public function messages(): array
    {
        return [
            'meeting_id.required_if' => 'A call with result "meeting_set" must reference the created meeting (meeting_id). Book the meeting first with POST /meetings.',
            'meeting_id.prohibited_unless' => 'meeting_id is only allowed when result is "meeting_set".',
        ];
    }
}
