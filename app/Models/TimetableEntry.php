<?php

namespace App\Models;

use App\Models\Concerns\CreatesWithUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableEntry extends Model
{
    use CreatesWithUser;

    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'created_by_user_id',
        'day_of_week',
        'start_time',
        'end_time',
        'subject',
        'teacher',
        'room',
        'special_message',
        'sort_order',
    ];

    /**
     * The classroom this entry belongs to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
