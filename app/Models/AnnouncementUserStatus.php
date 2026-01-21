<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementUserStatus extends Model
{
    /** @var string */
    protected $table = 'announcement_user_status';

    /** @var array<int, string> */
    protected $fillable = [
        'announcement_id',
        'user_id',
        'done_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'done_at' => 'datetime',
    ];

    /**
     * The announcement this status belongs to.
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * The user this status belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
