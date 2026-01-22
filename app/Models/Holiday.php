<?php

namespace App\Models;

use App\Models\Concerns\CreatesWithUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    use CreatesWithUser;

    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'created_by_user_id',
        'name',
        'start_date',
        'end_date',
        'description',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * The classroom this holiday belongs to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
