<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Services\Classroom\ClassroomService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class JoinClassroomController extends Controller
{
    private const MAX_ATTEMPTS = 3;
    private const LOCKOUT_MINUTES = 15;

    public function __construct(
        private ClassroomService $classroomService
    ) {}

    /**
     * Join a classroom using a join code.
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'join_code' => ['required', 'string', 'size:10'],
        ]);

        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['message' => 'יש להתחבר תחילה.'], 401);
        }

        $lockoutKey = "classroom.join.attempts.{$user->id}";
        $attempts = Cache::get($lockoutKey, 0);

        // Check if user is locked out
        if ($attempts >= self::MAX_ATTEMPTS) {
            $lockoutUntil = Cache::get("classroom.join.lockout.{$user->id}");
            
            if ($lockoutUntil && now()->lt($lockoutUntil)) {
                $minutesRemaining = now()->diffInMinutes($lockoutUntil, false);
                return response()->json([
                    'message' => "נחסמת עקב ניסיונות רבים מדי. נסה שוב בעוד {$minutesRemaining} דקות.",
                    'locked' => true,
                    'minutes_remaining' => $minutesRemaining,
                ], 429);
            } else {
                // Lockout expired, reset attempts
                Cache::forget($lockoutKey);
                Cache::forget("classroom.join.lockout.{$user->id}");
            }
        }

        $classroom = $this->classroomService->joinWithCode($user, $request->join_code);

        if (!$classroom) {
            $attempts++;
            Cache::put($lockoutKey, $attempts, now()->addMinutes(self::LOCKOUT_MINUTES));

            if ($attempts >= self::MAX_ATTEMPTS) {
                $lockoutUntil = now()->addMinutes(self::LOCKOUT_MINUTES);
                Cache::put("classroom.join.lockout.{$user->id}", $lockoutUntil, $lockoutUntil);
                
                return response()->json([
                    'message' => 'קוד הכיתה שגוי. נחסמת ל-' . self::LOCKOUT_MINUTES . ' דקות עקב ניסיונות רבים מדי.',
                    'locked' => true,
                    'minutes_remaining' => self::LOCKOUT_MINUTES,
                ], 422);
            }

            $remainingAttempts = self::MAX_ATTEMPTS - $attempts;
            return response()->json([
                'message' => 'קוד הכיתה שגוי. נותרו ' . $remainingAttempts . ' ניסיונות.',
                'attempts_remaining' => $remainingAttempts,
            ], 422);
        }

        // Success - reset attempts
        Cache::forget($lockoutKey);
        Cache::forget("classroom.join.lockout.{$user->id}");

        Log::info('User joined classroom', [
            'user_id' => $user->id,
            'classroom_id' => $classroom->id,
            'request_id' => $request->header('X-Request-Id'),
        ]);

        return response()->json([
            'message' => 'הצטרפת לכיתה בהצלחה!',
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'grade_level' => $classroom->grade_level,
                'grade_number' => $classroom->grade_number,
            ],
            'redirect' => route('classroom.show', $classroom),
        ]);
    }
}
