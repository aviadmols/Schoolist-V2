<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsSetting extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'provider',
        'username',
        'password',
        'sender',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'password' => 'encrypted',
    ];
}
