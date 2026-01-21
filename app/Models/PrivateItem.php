<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateItem extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'classroom_id',
        'title',
        'content',
        'occurs_on_date',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'occurs_on_date' => 'date',
    ];

    /**
     * The user who owns this private item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The classroom this item is scoped to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
