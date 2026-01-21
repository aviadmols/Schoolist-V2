<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildContact extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'child_id',
        'name',
        'phone',
        'relation',
    ];

    /**
     * The child this contact belongs to.
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
