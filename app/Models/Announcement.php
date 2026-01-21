<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Announcement extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'user_id',
        'type',
        'title',
        'content',
        'occurs_on_date',
        'day_of_week',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'occurs_on_date' => 'date',
    ];

    /**
     * The classroom this announcement belongs to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * The creator of the announcement.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * User statuses for this announcement.
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(AnnouncementUserStatus::class);
    }

    /**
     * Current user's status for this announcement.
     */
    public function currentUserStatus(): HasOne
    {
        return $this->hasOne(AnnouncementUserStatus::class)
            ->where('user_id', auth()->id());
    }
}
