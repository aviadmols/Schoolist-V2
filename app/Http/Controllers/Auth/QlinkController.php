<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendOtpSmsJob;
use App\Models\AuthToken;
use App\Models\OtpCode;
use App\Models\Qlink;
use App\Models\QlinkVisit;
use App\Models\SmsLog;
use App\Models\SmsSetting;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Builder\TemplateRenderer;
use App\Services\Classroom\ClassroomContextService;
use App\Services\Classroom\ClassroomService;
use App\Services\Sms\SmsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class QlinkController extends Controller
{
    /** @var int */
    private const TOKEN_LENGTH = 12;

    /** @var int */
    private const TOKEN_EXPIRES_DAYS = 180;

    /**
     * Show the qlink login page.
     */
    public function show(string $token, TemplateRenderer $renderer): Response
    {
        $isValid = $this->isValidToken($token);
        $qlink = $this->getOrCreateQlink($token);

        if ($qlink) {
            QlinkVisit::create([
                'qlink_id' => $qlink->id,
                'user_id' => auth()->id(),
                'request_id' => request()->header('X-Request-Id'),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }

        $overrideParts = $renderer->renderPublishedPartsByKey('auth.qlink', [
            'user' => auth()->user(),
            'locale' => app()->getLocale(),
            'page' => [
                'token' => $token,
                'is_valid' => $isValid,
            ],
        ]);

        if ($overrideParts) {
            return response()->view('builder.screen', [
                'html' => $overrideParts['html'],
                'css' => $overrideParts['css'],
                'js' => $overrideParts['js'],
            ]);
        }

        return Inertia::render('Auth/Qlink', [
            'token' => $token,
            'is_valid' => $isValid,
        ]);
    }

    /**
     * Request an OTP code for the given phone.
     */
    public function requestOtp(Request $request, OtpService $otpService, SmsService $smsService): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'qlink_token' => ['required', 'string'],
        ]);

        $this->assertValidToken($request->qlink_token);

        $code = $otpService->generate($request->phone);
        $message = $smsService->buildOtpMessage($code);
        $setting = SmsSetting::where('provider', 'sms019')->first();
        $providerRequest = [
            'sender' => $setting?->sender ?? (string) config('services.sms019.sender'),
            'phone_mask' => substr($request->phone, 0, 3) . '****' . substr($request->phone, -3),
            'message_length' => strlen($message),
        ];
        $providerRequestJson = json_encode($providerRequest, JSON_UNESCAPED_SLASHES) ?: '';

        $log = SmsLog::create([
            'provider' => 'sms019',
            'phone_mask' => substr($request->phone, 0, 3) . '****' . substr($request->phone, -3),
            'status' => 'queued',
            'request_id' => $request->header('X-Request-Id'),
            'user_id' => auth()->id(),
            'classroom_id' => $request->attributes->get('current_classroom')?->id,
            'error_message' => null,
            'provider_request' => $providerRequestJson,
        ]);

        SendOtpSmsJob::dispatchSync($request->phone, $code, $log->id);

        return response()->json(['message' => 'הקוד נשלח בהצלחה.']);
    }

    /**
     * Verify OTP code for a phone and return next step.
     */
    public function verifyOtp(Request $request, OtpService $otpService, ClassroomContextService $contextService): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'code' => ['required', 'string', 'size:4'],
            'qlink_token' => ['required', 'string'],
        ]);

        $this->assertValidToken($request->qlink_token);

        if (!$otpService->verify($request->phone, $request->code)) {
            return response()->json(['message' => 'הקוד שגוי או שפג תוקפו.'], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json([
                'requires_registration' => true,
                'phone' => $request->phone,
                'message' => 'לא נמצא משתמש עם המספר הזה. יש להשלים רישום.',
            ]);
        }

        Auth::login($user);

        $redirectUrl = route('profile.show');
        $classroomId = null;

        if ($user->current_classroom_id) {
            $classroom = $user->classrooms()->where('classroom_id', $user->current_classroom_id)->first();
            if ($classroom) {
                $contextService->setCurrentClassroom($classroom);
                $redirectUrl = route('classroom.show', $classroom);
                $classroomId = $classroom->id;
            }
        }

        $plainToken = Str::random(64);
        AuthToken::create([
            'user_id' => $user->id,
            'classroom_id' => $classroomId,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => Carbon::now()->addDays(self::TOKEN_EXPIRES_DAYS),
        ]);

        return response()->json([
            'auth_token' => $plainToken,
            'redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * Register a new user after OTP verification.
     */
    public function register(Request $request, ClassroomService $classroomService, ClassroomContextService $contextService): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'join_code' => ['nullable', 'string', 'size:10'],
            'qlink_token' => ['required', 'string'],
        ]);

        $this->assertValidToken($request->qlink_token);
        $this->assertRecentOtpVerification($request->phone);

        $name = trim($request->first_name . ' ' . $request->last_name);

        $user = User::create([
            'phone' => $request->phone,
            'name' => $name,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'role' => 'user',
        ]);

        $classroom = null;
        if ($request->join_code) {
            $classroom = $classroomService->joinWithCode($user, $request->join_code);
            if (!$classroom) {
                return response()->json(['message' => 'קוד הכיתה שגוי.'], 422);
            }
        }

        $this->attachQlinkToClassroom($request->qlink_token, $classroom?->id);

        Auth::login($user);

        $plainToken = Str::random(64);
        AuthToken::create([
            'user_id' => $user->id,
            'classroom_id' => $classroom?->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => Carbon::now()->addDays(self::TOKEN_EXPIRES_DAYS),
        ]);

        if ($classroom) {
            $contextService->setCurrentClassroom($classroom);
        }

        return response()->json([
            'auth_token' => $plainToken,
            'redirect_url' => $classroom ? route('classroom.show', $classroom) : route('profile.show'),
        ]);
    }

    /**
     * Join a classroom via code and issue a local auth token.
     */
    public function join(Request $request, ClassroomService $classroomService, ClassroomContextService $contextService): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'join_code' => ['required', 'string', 'size:10'],
            'qlink_token' => ['required', 'string'],
        ]);

        $this->assertValidToken($request->qlink_token);
        $this->assertRecentOtpVerification($request->phone);

        $user = User::where('phone', $request->phone)->firstOrFail();
        $classroom = $classroomService->joinWithCode($user, $request->join_code);

        if (!$classroom) {
            return response()->json(['message' => 'קוד הכיתה שגוי.'], 422);
        }

        $this->attachQlinkToClassroom($request->qlink_token, $classroom->id);

        Auth::login($user);
        $contextService->setCurrentClassroom($classroom);

        $plainToken = Str::random(64);
        AuthToken::create([
            'user_id' => $user->id,
            'classroom_id' => $classroom->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => Carbon::now()->addDays(self::TOKEN_EXPIRES_DAYS),
        ]);

        return response()->json([
            'auth_token' => $plainToken,
            'redirect_url' => route('classroom.show', $classroom),
        ]);
    }

    /**
     * Auto-login using a local storage token.
     */
    public function autoLogin(Request $request, ClassroomContextService $contextService): JsonResponse
    {
        $request->validate([
            'auth_token' => ['required', 'string'],
        ]);

        $tokenHash = hash('sha256', $request->auth_token);
        $authToken = AuthToken::where('token_hash', $tokenHash)->first();

        if (!$authToken || ($authToken->expires_at && $authToken->expires_at->isPast())) {
            return response()->json(['message' => 'החיבור פג תוקף.'], 401);
        }

        $user = User::find($authToken->user_id);

        if (!$user) {
            return response()->json(['message' => 'לא נמצא משתמש.'], 404);
        }

        Auth::login($user);
        $authToken->update(['last_used_at' => Carbon::now()]);

        if ($authToken->classroom_id) {
            $classroom = $user->classrooms()->where('classroom_id', $authToken->classroom_id)->first();
            if ($classroom) {
                $contextService->setCurrentClassroom($classroom);
                return response()->json(['redirect_url' => route('classroom.show', $classroom)]);
            }
        }

        return response()->json(['redirect_url' => route('profile.show')]);
    }

    /**
     * Get an active qlink by token.
     */
    private function isValidToken(string $token): bool
    {
        return (bool) preg_match('/^[0-9]{' . self::TOKEN_LENGTH . '}$/', $token);
    }

    /**
     * Ensure the qlink token is active.
     */
    private function assertValidToken(string $token): void
    {
        if (!$this->isValidToken($token)) {
            abort(404);
        }
    }

    /**
     * Create or fetch a qlink row for a valid token.
     */
    private function getOrCreateQlink(string $token): ?Qlink
    {
        if (!$this->isValidToken($token)) {
            return null;
        }

        return Qlink::firstOrCreate(
            ['token' => $token],
            ['is_active' => false]
        );
    }

    /**
     * Attach the qlink to a classroom if provided.
     */
    private function attachQlinkToClassroom(string $token, ?int $classroomId): void
    {
        if (!$classroomId) {
            return;
        }

        Qlink::where('token', $token)->update([
            'classroom_id' => $classroomId,
            'is_active' => true,
        ]);
    }

    /**
     * Ensure the phone was verified recently.
     */
    private function assertRecentOtpVerification(string $phone): void
    {
        $verified = OtpCode::where('phone', $phone)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>', now()->subMinutes(15))
            ->exists();

        if (!$verified) {
            abort(403);
        }
    }
}
