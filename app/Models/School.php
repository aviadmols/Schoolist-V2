<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = ['city_id', 'name'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }
}
