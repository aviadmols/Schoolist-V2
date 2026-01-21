<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableEntry extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'day_of_week',
        'start_time',
        'end_time',
        'subject',
        'teacher',
        'room',
    ];

    /**
     * The classroom this entry belongs to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
