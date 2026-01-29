<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherGlobalSetting extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'api_provider',
        'api_key',
    ];

    /**
     * Get the singleton global weather setting instance.
     */
    public static function getInstance(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'api_provider' => 'openweathermap',
                'api_key' => null,
            ]
        );
    }
}
