<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeCss extends Model
{
    /** @var string */
    protected $table = 'theme_css';

    /** @var array<int, string> */
    protected $fillable = [
        'draft_css',
        'published_css',
        'is_enabled',
        'classroom_id',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    /**
     * Last updater.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Related classroom (nullable for global scope).
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
