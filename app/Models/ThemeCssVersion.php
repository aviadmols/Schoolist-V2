<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeCssVersion extends Model
{
    /** @var bool */
    public $timestamps = false;

    /** @var array<int, string> */
    protected $fillable = [
        'css',
        'classroom_id',
        'created_by',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Version creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Related classroom (nullable for global scope).
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
