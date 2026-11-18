<?php

namespace App\Http\Resources;

use App\Support\Time;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'manager_id' => $this->manager_id,
            'result' => $this->result,
            'meeting_id' => $this->meeting_id,
            'comment' => $this->comment,
            'called_at' => Time::iso($this->called_at),
        ];
    }
}
