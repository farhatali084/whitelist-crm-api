<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    public const SCHEDULED = 'scheduled';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id', 'manager_id', 'starts_at', 'ends_at', 'status', 'original_starts_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'original_starts_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MeetingEvent::class)->orderBy('id');
    }

    public function isActive(): bool
    {
        return $this->status === self::SCHEDULED;
    }

    /** Active meetings whose [starts_at, ends_at) interval intersects [$start, $end). */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        // Strict comparisons: 10:00-11:00 and 11:00-12:00 do NOT overlap (rule 3).
        return $query->where('status', self::SCHEDULED)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);
    }
}
