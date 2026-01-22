<?php

namespace App\Models;

use App\Models\Concerns\CreatesWithUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportantContact extends Model
{
    use CreatesWithUser;

    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'created_by_user_id',
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
