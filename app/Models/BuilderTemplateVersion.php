<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuilderTemplateVersion extends Model
{
    /** @var string */
    public const VERSION_PUBLISHED = 'published';

    /** @var string */
    public const VERSION_DRAFT = 'draft_snapshot';

    /** @var bool */
    public $timestamps = false;

    /** @var array<int, string> */
    protected $fillable = [
        'template_id',
        'version_type',
        'html',
        'classroom_id',
        'created_by',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Parent template.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(BuilderTemplate::class, 'template_id');
    }

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
