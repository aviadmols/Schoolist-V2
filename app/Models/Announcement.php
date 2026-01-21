<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Announcement extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'user_id',
        'type',
        'title',
        'content',
        'occurs_on_date',
        'end_date',
        'day_of_week',
        'always_show',
        'occurs_at_time',
        'location',
        'attachment_path',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'occurs_on_date' => 'date',
        'end_date' => 'date',
        'always_show' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Announcement $announcement) {
            $announcement->updateClassroomMediaSize(null, $announcement->attachment_path);
        });

        static::updating(function (Announcement $announcement) {
            if ($announcement->isDirty('attachment_path')) {
                $announcement->updateClassroomMediaSize($announcement->getOriginal('attachment_path'), $announcement->attachment_path);
            }
        });

        static::deleting(function (Announcement $announcement) {
            $announcement->updateClassroomMediaSize($announcement->attachment_path, null);
        });
    }

    /**
     * The classroom this announcement belongs to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * The creator of the announcement.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * User statuses for this announcement.
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(AnnouncementUserStatus::class);
    }

    /**
     * Current user's status for this announcement.
     */
    public function currentUserStatus(): HasOne
    {
        return $this->hasOne(AnnouncementUserStatus::class)
            ->where('user_id', auth()->id());
    }

    /**
     * Update classroom media size when file path changes.
     *
     * @param string|null $oldPath
     * @param string|null $newPath
     * @return void
     */
    protected function updateClassroomMediaSize(?string $oldPath, ?string $newPath): void
    {
        $oldSize = $this->getMediaFileSize($oldPath);
        $newSize = $this->getMediaFileSize($newPath);
        $delta = $newSize - $oldSize;

        if ($delta > 0) {
            Classroom::where('id', $this->classroom_id)->increment('media_size_bytes', $delta);
        } elseif ($delta < 0) {
            Classroom::where('id', $this->classroom_id)->decrement('media_size_bytes', abs($delta));
        }
    }

    /**
     * Get file size from the public disk.
     *
     * @param string|null $path
     * @return int
     */
    protected function getMediaFileSize(?string $path): int
    {
        if (!$path) {
            return 0;
        }

        if (!Storage::disk('public')->exists($path)) {
            return 0;
        }

        return (int) Storage::disk('public')->size($path);
    }
}
