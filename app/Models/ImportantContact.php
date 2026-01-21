<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportantContact extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'first_name',
        'last_name',
        'role',
        'phone',
        'email',
    ];

    /**
     * The classroom this contact belongs to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
