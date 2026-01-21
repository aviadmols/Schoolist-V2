<?php

namespace App\Services\Classroom;

use App\Models\Classroom;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ClassroomService
{
    /** @var AuditService */
    private AuditService $auditService;

    /** @var HolidayService */
    private HolidayService $holidayService;

    public function __construct(AuditService $auditService, HolidayService $holidayService)
    {
        $this->auditService = $auditService;
        $this->holidayService = $holidayService;
    }

    /**
     * Create a new classroom and assign the user as owner.
     *
     * @param User $user
     * @param array $data
     * @return Classroom
     */
    public function createForUser(User $user, array $data): Classroom
    {
        return DB::transaction(function () use ($user, $data) {
            $classroom = Classroom::create([
                'name' => $data['name'],
                'join_code' => $this->generateUniqueJoinCode(),
                'timezone' => $data['timezone'] ?? 'Asia/Jerusalem',
            ]);

            $classroom->users()->attach($user->id, ['role' => 'owner']);

            // Seed holidays from templates
            $this->holidayService->seedFromTemplates($classroom);

            $this->auditService->log('classroom.created', $classroom, null, $classroom->toArray(), $classroom->id);

            return $classroom;
        });
    }

    /**
     * Join a classroom using a join code.
     *
     * @param User $user
     * @param string $joinCode
     * @return Classroom|null
     */
    public function joinWithCode(User $user, string $joinCode): ?Classroom
    {
        $classroom = Classroom::where('join_code', $joinCode)->first();

        if (!$classroom) {
            return null;
        }

        // Check if already a member
        if ($classroom->users()->where('user_id', $user->id)->exists()) {
            return $classroom;
        }

        $classroom->users()->attach($user->id, ['role' => 'member']);

        $this->auditService->log('classroom.joined', $classroom, null, ['user_id' => $user->id], $classroom->id);

        return $classroom;
    }

    /**
     * Generate a unique 10-digit join code.
     *
     * @return string
     */
    private function generateUniqueJoinCode(): string
    {
        do {
            $code = (string) rand(1000000000, 9999999999);
        } while (Classroom::where('join_code', $code)->exists());

        return $code;
    }
}
