<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Qlink extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'token',
        'is_active',
        'created_by_user_id',
        'classroom_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The user who created the qlink.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * The classroom linked to this qlink.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Visits for this qlink.
     */
    public function visits(): HasMany
    {
        return $this->hasMany(QlinkVisit::class);
    }
}
