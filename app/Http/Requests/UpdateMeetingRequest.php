<?php

namespace App\Http\Requests;

use App\Rules\IsoDateTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PATCH /meetings/{id}
 *   reschedule: {"starts_at": "..."} and/or {"manager_id": 2}
 *   cancel:     {"status": "cancelled"}  (cannot be combined with a reschedule)
 */
class UpdateMeetingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'starts_at' => ['sometimes', new IsoDateTime],
            'manager_id' => ['sometimes', 'integer', 'exists:managers,id'],
            'status' => ['sometimes', 'in:cancelled', 'prohibits:starts_at,manager_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'The only status change allowed is "cancelled".',
            'status.prohibits' => 'Cancel and reschedule are separate requests.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->hasAny(['starts_at', 'manager_id', 'status'])) {
                    $validator->errors()->add('body', 'Send starts_at and/or manager_id to reschedule, or status "cancelled" to cancel.');
                }
            },
        ];
    }

    public function isCancellation(): bool
    {
        return $this->input('status') === 'cancelled';
    }
}
