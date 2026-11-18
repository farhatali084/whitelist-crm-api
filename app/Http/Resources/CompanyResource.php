<?php

namespace App\Http\Resources;

use App\Support\Time;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bin' => $this->bin,
            'phone' => $this->phone,
            'status' => $this->status,
            'created_at' => Time::iso($this->created_at),
        ];
    }
}
