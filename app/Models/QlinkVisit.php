<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QlinkVisit extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'qlink_id',
        'user_id',
        'request_id',
        'ip_address',
        'user_agent',
    ];

    /**
     * The qlink this visit belongs to.
     */
    public function qlink(): BelongsTo
    {
        return $this->belongsTo(Qlink::class);
    }

    /**
     * The user who visited the link.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
