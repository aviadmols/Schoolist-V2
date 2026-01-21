<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Classroom $classroom) {
            if (!$classroom->join_code) {
                $classroom->join_code = static::generateUniqueJoinCode();
            }
        });
    }

    /**
     * Generate a unique 10-digit join code.
     */
    protected static function generateUniqueJoinCode(): string
    {
        do {
            $code = (string) rand(1000000000, 9999999999);
        } while (static::where('join_code', $code)->exists());

        return $code;
    }

    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'join_code',
        'timezone',
        'media_size_bytes',
        'timetable_file_id',
        'city_id',
        'school_id',
        'grade_level',
        'grade_number',
    ];

    /**
     * The city this classroom belongs to.
     */
    public function city(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * The school this classroom belongs to.
     */
    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(School::class);
    }

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
