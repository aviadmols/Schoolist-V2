<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\File;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\JsonResponse;

class FileController extends Controller
{
    /** @var FileStorageService */
    private FileStorageService $storageService;

    public function __construct(FileStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Upload a file to the classroom.
     *
     * @param Request $request
     * @param Classroom $classroom
     * @return JsonResponse
     */
    public function upload(Request $request, Classroom $classroom): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB limit
            'entity_type' => ['required', 'string', 'alpha_dash'],
            'entity_id' => ['required', 'integer'],
        ]);

        // Authorization: Check if user belongs to the classroom
        if (!$classroom->users()->where('user_id', auth()->id())->exists()) {
            abort(403);
        }

        $fileRecord = $this->storageService->storeUpload(
            $classroom->id,
            $request->entity_type,
            $request->entity_id,
            $request->file('file')
        );

        return response()->json([
            'message' => 'File uploaded successfully.',
            'file' => $fileRecord,
        ]);
    }

    /**
     * Download a file from the classroom.
     *
     * @param Classroom $classroom
     * @param File $file
     * @return BinaryFileResponse
     */
    public function download(Classroom $classroom, File $file): BinaryFileResponse
    {
        // Scope check
        if ($file->classroom_id !== $classroom->id) {
            abort(404);
        }

        // Authorization
        if (!$classroom->users()->where('user_id', auth()->id())->exists()) {
            abort(403);
        }

        $path = Storage::disk($file->disk)->path($file->path);

        return response()->download($path, $file->name);
    }

    /**
     * Delete a file from the classroom.
     *
     * @param Classroom $classroom
     * @param File $file
     * @return JsonResponse
     */
    public function destroy(Classroom $classroom, File $file): JsonResponse
    {
        // Scope check
        if ($file->classroom_id !== $classroom->id) {
            abort(404);
        }

        // Authorization: Only owner or admin can delete files generally, 
        // or user who uploaded the file.
        $membership = $classroom->users()->where('user_id', auth()->id())->first();
        $isAuthorized = $membership && (
            in_array($membership->pivot->role, ['owner', 'admin']) ||
            $file->user_id === auth()->id()
        );

        if (!$isAuthorized) {
            abort(403);
        }

        $this->storageService->deleteFile($file);

        return response()->json(['message' => 'File deleted successfully.']);
    }
}
