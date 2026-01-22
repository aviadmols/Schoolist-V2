<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaFile extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'url',
        'classroom_id',
        'uploaded_by',
    ];

    /**
     * Uploader.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Related classroom (nullable for global scope).
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
