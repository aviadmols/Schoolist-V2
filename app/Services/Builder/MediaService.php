<?php

namespace App\Services\Builder;

use App\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaService
{
    /** @var string */
    private const DISK = 'media';

    /**
     * Store an uploaded file and create metadata record.
     */
    public function storeUploadedFile(UploadedFile $file, string $directory = 'assets'): MediaFile
    {
        $path = $file->store($directory, self::DISK);
        $url = Storage::disk(self::DISK)->url($path);

        return MediaFile::query()->create([
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'url' => $url,
            'uploaded_by' => auth()->id(),
        ]);
    }

    /**
     * Delete a media file and storage object.
     */
    public function deleteMediaFile(MediaFile $mediaFile): void
    {
        Storage::disk($mediaFile->disk)->delete($mediaFile->path);
        $mediaFile->delete();
    }
}
