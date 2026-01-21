<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassLink extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'title',
        'url',
        'file_path',
        'icon',
        'sort_order',
    ];

    /**
     * Classroom this link belongs to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
