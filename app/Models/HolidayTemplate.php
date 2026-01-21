<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HolidayTemplate extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'description',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
