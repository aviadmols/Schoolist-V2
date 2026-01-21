<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class File extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'user_id',
        'name',
        'path',
        'mime_type',
        'size_bytes',
        'disk',
    ];

    /**
     * Classroom this file belongs to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * User who uploaded the file.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
