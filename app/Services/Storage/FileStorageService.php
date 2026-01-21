<?php

namespace App\Services\Storage;

use App\Models\Classroom;
use App\Models\File;
use App\Services\Audit\AuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class FileStorageService
{
    /** @var AuditService */
    private AuditService $auditService;

    /** @var string */
    private const DISK = 'public';

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Store a file upload and track size.
     *
     * @param int $classroomId
     * @param string $entityType
     * @param int $entityId
     * @param UploadedFile $uploadedFile
     * @return File
     */
    public function storeUpload(int $classroomId, string $entityType, int $entityId, UploadedFile $uploadedFile): File
    {
        return DB::transaction(function () use ($classroomId, $entityType, $entityId, $uploadedFile) {
            $classroom = Classroom::findOrFail($classroomId);
            $uuid = (string) Str::uuid();
            $originalName = $uploadedFile->getClientOriginalName();
            $fileName = "{$uuid}_{$originalName}";
            
            $path = "classrooms/{$classroomId}/{$entityType}/{$entityId}";
            $storedPath = $uploadedFile->storeAs($path, $fileName, self::DISK);
            $size = $uploadedFile->getSize();

            $fileRecord = File::create([
                'classroom_id' => $classroomId,
                'user_id' => auth()->id(),
                'name' => $originalName,
                'path' => $storedPath,
                'mime_type' => $uploadedFile->getMimeType(),
                'size_bytes' => $size,
                'disk' => self::DISK,
            ]);

            // Increment classroom media size
            $classroom->increment('media_size_bytes', $size);

            $this->auditService->log(
                'file.uploaded',
                $fileRecord,
                null,
                ['name' => $originalName, 'size' => $size],
                $classroomId
            );

            return $fileRecord;
        });
    }

    /**
     * Delete a file and update size.
     *
     * @param File $file
     * @return bool
     */
    public function deleteFile(File $file): bool
    {
        return DB::transaction(function () use ($file) {
            $size = $file->size_bytes;
            $classroomId = $file->classroom_id;

            if (Storage::disk($file->disk)->exists($file->path)) {
                Storage::disk($file->disk)->delete($file->path);
            }

            $file->delete();

            // Decrement classroom media size
            Classroom::where('id', $classroomId)->decrement('media_size_bytes', $size);

            $this->auditService->log(
                'file.deleted',
                null,
                ['id' => $file->id, 'name' => $file->name, 'size' => $size],
                null,
                $classroomId
            );

            return true;
        });
    }

    /**
     * Purge all files for a classroom.
     *
     * @param int $classroomId
     * @return void
     */
    public function purgeClassroomFolder(int $classroomId): void
    {
        $path = "classrooms/{$classroomId}";
        
        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->deleteDirectory($path);
        }

        File::where('classroom_id', $classroomId)->delete();
        Classroom::where('id', $classroomId)->update(['media_size_bytes' => 0]);

        $this->auditService->log('classroom.files_purged', null, null, null, $classroomId);
    }
}
