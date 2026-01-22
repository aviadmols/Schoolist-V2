<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuilderTemplate extends Model
{
    /** @var string */
    public const SCOPE_GLOBAL = 'global';

    /** @var string */
    public const SCOPE_CLASSROOM = 'classroom';

    /** @var string */
    public const TYPE_SCREEN = 'screen';

    /** @var string */
    public const TYPE_SECTION = 'section';

    /** @var array<int, string> */
    protected $fillable = [
        'scope',
        'type',
        'name',
        'key',
        'draft_html',
        'published_html',
        'is_override_enabled',
        'mock_data_json',
        'classroom_id',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_override_enabled' => 'boolean',
        'mock_data_json' => 'array',
    ];

    /**
     * Template versions.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(BuilderTemplateVersion::class, 'template_id');
    }

    /**
     * Template creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Template updater.
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
