<?php

namespace App\Models;

use App\Models\Concerns\CreatesWithUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Child extends Model
{
    use CreatesWithUser;

    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'created_by_user_id',
        'name',
        'photo_file_id',
    ];

    /**
     * The classroom this child belongs to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * The photo file of the child.
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(File::class, 'photo_file_id');
    }

    /**
     * The contacts associated with this child.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ChildContact::class);
    }
}
