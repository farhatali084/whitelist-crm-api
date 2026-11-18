<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** "new" until the first call; afterwards the result of the latest call. */
    public const STATUSES = ['new', 'no_answer', 'refused', 'meeting_set'];

    protected $fillable = ['name', 'bin', 'phone', 'status'];

    protected $attributes = ['status' => 'new'];

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }
}
