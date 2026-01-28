<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeatherSetting extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'api_provider',
        'api_key',
        'city_name',
        'icon_mapping',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'icon_mapping' => 'array',
    ];

    /**
     * The classroom this weather setting belongs to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
