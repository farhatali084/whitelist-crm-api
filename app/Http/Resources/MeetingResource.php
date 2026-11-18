<?php

namespace App\Http\Resources;

use App\Support\Time;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'manager_id' => $this->manager_id,
            'status' => $this->status,
            'starts_at' => Time::iso($this->starts_at),
            'ends_at' => Time::iso($this->ends_at),
            // Rule 6: the first booked slot and the booking moment survive any reschedule.
            'original_starts_at' => Time::iso($this->original_starts_at),
            'booked_at' => Time::iso($this->created_at),
            'cancelled_at' => Time::iso($this->cancelled_at),
            'history' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => [
                'type' => $e->type,
                'at' => Time::iso($e->created_at),
                'old_starts_at' => Time::iso($e->old_starts_at),
                'new_starts_at' => Time::iso($e->new_starts_at),
                'old_manager_id' => $e->old_manager_id,
                'new_manager_id' => $e->new_manager_id,
            ])),
        ];
    }
}
