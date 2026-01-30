<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeatherSetting extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'icon_mapping',
        'temperature_ranges',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'icon_mapping' => 'array',
        'temperature_ranges' => 'array',
    ];

    /**
     * The classroom this weather setting belongs to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
