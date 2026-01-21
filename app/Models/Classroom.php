<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'join_code',
        'timezone',
        'media_size_bytes',
        'timetable_file_id',
    ];

    /**
     * Users belonging to this classroom.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ClassroomUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The uploaded timetable image for this classroom.
     */
    public function timetableFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'timetable_file_id');
    }

    /**
     * Structured timetable entries for this classroom.
     */
    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    /**
     * Files uploaded to this classroom.
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /**
     * Links for this classroom.
     */
    public function links(): HasMany
    {
        return $this->hasMany(ClassLink::class);
    }
}
