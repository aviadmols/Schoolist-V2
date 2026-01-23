<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'provider',
        'phone_mask',
        'status',
        'request_id',
        'user_id',
        'classroom_id',
        'error_message',
        'provider_request',
        'provider_response',
    ];

    /**
     * The user related to the SMS log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The classroom related to the SMS log.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
