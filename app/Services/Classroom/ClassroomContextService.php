<?php

namespace App\Services\Classroom;

use App\Models\Classroom;
use Illuminate\Support\Facades\Session;

class ClassroomContextService
{
    /** @var string */
    private const SESSION_KEY = 'current_classroom_id';

    /**
     * Get the current classroom from context.
     */
    public function getCurrentClassroom(): ?Classroom
    {
        $id = Session::get(self::SESSION_KEY);

        if (!$id && auth()->check()) {
            $id = auth()->user()->current_classroom_id;
        }

        if (!$id) {
            return null;
        }

        return Classroom::find($id);
    }

    /**
     * Set the current classroom in context.
     */
    public function setCurrentClassroom(Classroom $classroom): void
    {
        Session::put(self::SESSION_KEY, $classroom->id);

        if (auth()->check()) {
            auth()->user()->update(['current_classroom_id' => $classroom->id]);
        }
    }

    /**
     * Clear the current classroom context.
     */
    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
