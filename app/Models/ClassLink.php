<?php

namespace App\Models;

use App\Models\Concerns\CreatesWithUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ClassLink extends Model
{
    use CreatesWithUser;

    /** @var array<int, string> */
    protected $fillable = [
        'classroom_id',
        'created_by_user_id',
        'title',
        'category',
        'url',
        'file_path',
        'icon',
        'sort_order',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (ClassLink $link) {
            $link->updateClassroomMediaSize(null, $link->file_path);
        });

        static::updating(function (ClassLink $link) {
            if ($link->isDirty('file_path')) {
                $link->updateClassroomMediaSize($link->getOriginal('file_path'), $link->file_path);
            }
        });

        static::deleting(function (ClassLink $link) {
            $link->updateClassroomMediaSize($link->file_path, null);
        });
    }

    /**
     * Classroom this link belongs to.
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
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
