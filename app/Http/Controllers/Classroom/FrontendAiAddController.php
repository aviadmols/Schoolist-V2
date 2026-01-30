<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\ImportantContact;
use App\Models\Child;
use App\Models\ChildContact;
use App\Services\Ai\OpenRouterService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FrontendAiAddController extends Controller
{
    private const AI_PROVIDER = 'openrouter';
    private const ANNOUNCEMENT_TITLE_MAX_LENGTH = 80;

    public function analyze(Request $request, Classroom $classroom, OpenRouterService $service)
    {
        $startTime = microtime(true);
        $requestId = uniqid('ai_analyze_', true);
        
        Log::info("[AI Analyze] Request started", [
            'request_id' => $requestId,
            'classroom_id' => $classroom->id,
            'user_id' => auth()->id(),
            'has_text' => $request->has('content_text'),
            'has_file' => $request->hasFile('content_file'),
            'all_inputs' => array_keys($request->all()),
        ]);

        try {
            $request->validate([
                'content_text' => 'nullable|string|max:10000',
                'content_file' => 'nullable|image|max:10240', // 10MB
            ], [
                'content_file.image' => 'הקובץ חייב להיות תמונה',
                'content_file.max' => 'גודל הקובץ לא יכול לעלות על 10MB',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error("[AI Analyze] Validation failed", [
                'request_id' => $requestId,
                'errors' => $e->errors(),
                'input' => $request->all(),
                'has_content_text' => $request->has('content_text'),
                'has_content_file' => $request->hasFile('content_file'),
                'all_keys' => array_keys($request->all()),
            ]);
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                $errorMessages[] = $field . ': ' . implode(', ', $messages);
            }
            return response()->json([
                'ok' => false, 
                'error' => 'שגיאת אימות: ' . implode(' | ', $errorMessages)
            ], 422);
        }

        $text = trim((string) $request->input('content_text'));
        $file = $request->file('content_file');

        Log::info("[AI Analyze] Input processed", [
            'request_id' => $requestId,
            'text_length' => strlen($text),
            'has_file' => $file !== null,
            'file_size' => $file ? $file->getSize() : null,
            'file_mime' => $file ? $file->getMimeType() : null,
        ]);

        if ($text === '' && !$file) {
            Log::warning("[AI Analyze] Empty input", ['request_id' => $requestId]);
            return response()->json(['ok' => false, 'error' => 'נא להזין טקסט או לצרף תמונה'], 422);
        }

        $setting = AiSetting::where('provider', self::AI_PROVIDER)
            ->whereNull('classroom_id')
            ->first();

        if (!$setting || !$setting->token || !$setting->content_analyzer_model || !$setting->content_analyzer_prompt) {
            Log::error("[AI Analyze] Missing AI settings", [
                'request_id' => $requestId,
                'has_setting' => $setting !== null,
                'has_token' => $setting && $setting->token ? 'yes' : 'no',
                'has_model' => $setting && $setting->content_analyzer_model ? 'yes' : 'no',
                'has_prompt' => $setting && $setting->content_analyzer_prompt ? 'yes' : 'no',
            ]);
            return response()->json(['ok' => false, 'error' => 'הגדרות AI חסרות במערכת'], 500);
        }

        $prompt = $this->buildPrompt($setting->content_analyzer_prompt, $text, $classroom);
        
        Log::info("[AI Analyze] Prompt built", [
            'request_id' => $requestId,
            'prompt_length' => strlen($prompt),
            'prompt_preview' => substr($prompt, 0, 200) . '...',
        ]);
        
        $imageMime = null;
        $imageBase64 = null;
        if ($file) {
            $imageMime = $file->getMimeType();
            $imageBase64 = base64_encode(file_get_contents($file->getRealPath()));
            Log::info("[AI Analyze] Image processed", [
                'request_id' => $requestId,
                'mime' => $imageMime,
                'base64_length' => strlen($imageBase64),
            ]);
        }

        $apiStartTime = microtime(true);
        $response = $service->requestContentAnalysis(
            (string) $setting->token,
            (string) $setting->content_analyzer_model,
            $prompt,
            $imageMime,
            $imageBase64,
            $classroom->id
        );
        $apiDuration = microtime(true) - $apiStartTime;

        Log::info("[AI Analyze] API response received", [
            'request_id' => $requestId,
            'api_duration_seconds' => round($apiDuration, 2),
            'has_response' => $response !== null,
            'response_length' => $response ? strlen($response) : 0,
            'response_preview' => $response ? substr($response, 0, 500) : null,
        ]);

        if (!$response) {
            $error = $service->getLastError();
            Log::error("[AI Analyze] API failed", [
                'request_id' => $requestId,
                'error' => $error,
            ]);
            return response()->json(['ok' => false, 'error' => 'תקשורת עם ה-AI נכשלה: ' . ($error ?: 'תגובה ריקה')], 500);
        }

        $suggestion = $this->parseResponse($response);
        
        Log::info("[AI Analyze] Response parsed", [
            'request_id' => $requestId,
            'has_suggestion' => $suggestion !== null,
            'suggestion_type' => $suggestion['type'] ?? null,
            'suggestion_data' => $suggestion,
        ]);

        if (!$suggestion) {
            Log::error("[AI Analyze] Parse failed", [
                'request_id' => $requestId,
                'raw_response' => $response,
            ]);
            return response()->json(['ok' => false, 'error' => 'לא ניתן היה לנתח את התוכן'], 422);
        }

        $totalDuration = microtime(true) - $startTime;
        Log::info("[AI Analyze] Request completed", [
            'request_id' => $requestId,
            'total_duration_seconds' => round($totalDuration, 2),
            'suggestion_type' => $suggestion['type'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'suggestion' => $suggestion
        ]);
    }

    public function store(Request $request, Classroom $classroom)
    {
        $startTime = microtime(true);
        $requestId = uniqid('ai_store_', true);
        
        Log::info("[AI Store] Request started", [
            'request_id' => $requestId,
            'classroom_id' => $classroom->id,
            'user_id' => auth()->id(),
            'has_suggestion' => $request->has('suggestion'),
            'has_is_public' => $request->has('is_public'),
            'all_inputs' => array_keys($request->all()),
        ]);

        try {
            $request->validate([
                'suggestion' => 'required|array',
                'is_public' => 'required|boolean'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error("[AI Store] Validation failed", [
                'request_id' => $requestId,
                'errors' => $e->errors(),
                'input' => $request->all(),
            ]);
            return response()->json([
                'ok' => false, 
                'error' => 'שגיאת אימות: ' . implode(', ', array_map(fn($arr) => implode(', ', $arr), $e->errors()))
            ], 422);
        }

        $suggestion = $request->input('suggestion');
        $isPublic = $request->input('is_public');
        $user = auth()->user();

        Log::info("[AI Store] Input processed", [
            'request_id' => $requestId,
            'suggestion_type' => $suggestion['type'] ?? null,
            'suggestion_keys' => array_keys($suggestion),
            'is_public' => $isPublic,
            'user_role' => $user->role ?? null,
        ]);

        // Permission check
        $canPostPublicly = $user && ($user->role === 'site_admin' || 
            $classroom->users()->where('users.id', $user->id)->wherePivotIn('role', ['owner', 'admin'])->exists() ||
            ($classroom->allow_member_posting && $classroom->users()->where('users.id', $user->id)->exists()));

        Log::info("[AI Store] Permission check", [
            'request_id' => $requestId,
            'can_post_publicly' => $canPostPublicly,
            'requested_is_public' => $isPublic,
        ]);

        if ($isPublic && !$canPostPublicly) {
            $isPublic = false; // Force private if not allowed
            Log::warning("[AI Store] Forced private", ['request_id' => $requestId]);
        }

        try {
            $type = (string) ($suggestion['type'] ?? 'unknown');
            $data = $suggestion['extracted_data'] ?? [];
            
            Log::info("[AI Store] Creating content", [
                'request_id' => $requestId,
                'type' => $type,
                'data_keys' => array_keys($data),
                'data_preview' => $data,
            ]);
            
            if ($type === 'contact') {
                $this->createContacts($classroom, $data);
                $summary = 'אנשי קשר נוספו בהצלחה';
            } elseif ($type === 'contact_page') {
                $this->createChildContactPage($classroom, $data);
                $summary = 'דף קשר לילד נוצר בהצלחה';
            } elseif (in_array($type, ['announcement', 'event', 'homework'])) {
                $this->createAnnouncements($classroom, $type, $data, $isPublic);
                $summary = 'התוכן נוסף בהצלחה';
            } else {
                Log::error("[AI Store] Unknown type", [
                    'request_id' => $requestId,
                    'type' => $type,
                    'suggestion' => $suggestion,
                ]);
                return response()->json(['ok' => false, 'error' => 'סוג תוכן לא מזוהה: ' . $type], 422);
            }

            $totalDuration = microtime(true) - $startTime;
            Log::info("[AI Store] Request completed", [
                'request_id' => $requestId,
                'total_duration_seconds' => round($totalDuration, 2),
                'summary' => $summary,
            ]);

            return response()->json([
                'ok' => true,
                'message' => $summary,
                'is_public' => $isPublic
            ]);

        } catch (\Exception $e) {
            Log::error("[AI Store] Failed", [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'suggestion' => $suggestion,
            ]);
            return response()->json(['ok' => false, 'error' => 'שגיאה בשמירת הנתונים: ' . $e->getMessage()], 500);
        }
    }

    private function buildPrompt($basePrompt, $text, $classroom)
    {
        $now = Carbon::now($classroom->timezone);
        $prompt = trim($basePrompt);
        $prompt .= "\n\nCURRENT_DATE_AND_TIME:\n";
        $prompt .= "Date: ".$now->format('d.m.Y')."\n";
        $prompt .= "Time: ".$now->format('H:i')."\n";
        $prompt .= "Day of week: ".$now->format('l')." (".['ראשון','שני','שלישי','רביעי','חמישי','שישי','שבת'][$now->dayOfWeek].")\n";
        
        if ($text !== '') {
            $prompt .= "\n\nINPUT_TEXT:\n".$text;
        }

        $prompt .= "\n\nSCHEMA FIELDS:\n- announcements: title, content, occurs_on_date, occurs_at_time, location\n- contacts: first_name, last_name, role, phone, email\n- children: name, birth_date\n- child_contacts: name, relation, phone\n\nReturn JSON only.";

        return $prompt;
    }

    private function parseResponse($response)
    {
        preg_match('/\{.*\}/s', $response, $matches);
        $json = $matches[0] ?? $response;
        $payload = json_decode($json, true);
        return $payload['suggestions'][0] ?? null;
    }

    private function createAnnouncements($classroom, $type, $data, $isPublic)
    {
        $announcementType = $type === 'announcement' ? 'message' : $type;
        $items = isset($data['items']) ? $data['items'] : [$data];

        foreach ($items as $item) {
            $title = $item['title'] ?? $item['name'] ?? 'ללא כותרת';
            $content = $item['content'] ?? $item['description'] ?? '';
            
            Announcement::create([
                'classroom_id' => $classroom->id,
                'user_id' => auth()->id(),
                'type' => $announcementType,
                'title' => mb_substr($title, 0, self::ANNOUNCEMENT_TITLE_MAX_LENGTH),
                'content' => $content,
                'occurs_on_date' => $this->parseDate($item['date'] ?? $item['due_date'] ?? null),
                'occurs_at_time' => $item['time'] ?? null,
                'location' => $item['location'] ?? null,
                'is_public' => $isPublic, // We might need to add this column to announcements table if it doesn't exist
            ]);
        }
    }

    private function createContacts($classroom, $data)
    {
        $contacts = isset($data['contacts']) ? $data['contacts'] : [$data];
        foreach ($contacts as $c) {
            $name = $c['name'] ?? '';
            $parts = explode(' ', trim($name));
            $firstName = array_shift($parts);
            $lastName = implode(' ', $parts);

            ImportantContact::create([
                'classroom_id' => $classroom->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => $c['role'] ?? '',
                'phone' => $c['phone'] ?? '',
                'email' => $c['email'] ?? '',
            ]);
        }
    }

    private function createChildContactPage($classroom, $data)
    {
        $child = Child::create([
            'classroom_id' => $classroom->id,
            'name' => $data['child_name'] ?? '',
            'birth_date' => $this->parseDate($data['child_birth_date'] ?? null),
        ]);

        if (!empty($data['parent1_name'])) {
            ChildContact::create([
                'child_id' => $child->id,
                'name' => $data['parent1_name'],
                'relation' => $data['parent1_role'] ?? 'other',
                'phone' => $data['parent1_phone'] ?? '',
            ]);
        }
    }

    private function parseDate($value)
    {
        if (!$value) return null;
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
