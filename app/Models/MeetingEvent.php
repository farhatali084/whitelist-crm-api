<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of a meeting's history. Never updated or deleted. */
class MeetingEvent extends Model
{
    public const UPDATED_AT = null; // append-only: only created_at

    protected $fillable = [
        'meeting_id', 'type', 'old_starts_at', 'new_starts_at', 'old_manager_id', 'new_manager_id',
    ];

    protected function casts(): array
    {
        return [
            'old_starts_at' => 'immutable_datetime',
            'new_starts_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
