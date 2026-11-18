<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends Model
{
    public const RESULTS = ['no_answer', 'refused', 'meeting_set'];

    protected $fillable = ['company_id', 'manager_id', 'result', 'meeting_id', 'comment', 'called_at'];

    protected function casts(): array
    {
        return ['called_at' => 'immutable_datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
