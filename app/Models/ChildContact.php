<?php

namespace App\Models;

use App\Models\Concerns\CreatesWithUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildContact extends Model
{
    use CreatesWithUser;

    /** @var array<int, string> */
    protected $fillable = [
        'child_id',
        'created_by_user_id',
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
