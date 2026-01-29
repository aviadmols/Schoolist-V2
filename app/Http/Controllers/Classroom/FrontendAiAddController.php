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
        $request->validate([
            'content_text' => 'nullable|string',
            'content_file' => 'nullable|image|max:10240', // 10MB
        ]);

        $text = trim((string) $request->input('content_text'));
        $file = $request->file('content_file');

        if ($text === '' && !$file) {
            return response()->json(['ok' => false, 'error' => 'נא להזין טקסט או לצרף תמונה'], 422);
        }

        $setting = AiSetting::where('provider', self::AI_PROVIDER)
            ->whereNull('classroom_id')
            ->first();

        if (!$setting || !$setting->token || !$setting->content_analyzer_model || !$setting->content_analyzer_prompt) {
            return response()->json(['ok' => false, 'error' => 'הגדרות AI חסרות במערכת'], 500);
        }

        $prompt = $this->buildPrompt($setting->content_analyzer_prompt, $text, $classroom);
        
        $imageMime = null;
        $imageBase64 = null;
        if ($file) {
            $imageMime = $file->getMimeType();
            $imageBase64 = base64_encode(file_get_contents($file->getRealPath()));
        }

        $response = $service->requestContentAnalysis(
            (string) $setting->token,
            (string) $setting->content_analyzer_model,
            $prompt,
            $imageMime,
            $imageBase64,
            $classroom->id
        );

        if (!$response) {
            return response()->json(['ok' => false, 'error' => 'תקשורת עם ה-AI נכשלה'], 500);
        }

        $suggestion = $this->parseResponse($response);
        if (!$suggestion) {
            return response()->json(['ok' => false, 'error' => 'לא ניתן היה לנתח את התוכן'], 422);
        }

        return response()->json([
            'ok' => true,
            'suggestion' => $suggestion
        ]);
    }

    public function store(Request $request, Classroom $classroom)
    {
        $request->validate([
            'suggestion' => 'required|array',
            'is_public' => 'required|boolean'
        ]);

        $suggestion = $request->input('suggestion');
        $isPublic = $request->input('is_public');
        $user = auth()->user();

        // Permission check
        $canPostPublicly = $user && ($user->role === 'site_admin' || 
            $classroom->users()->where('users.id', $user->id)->wherePivotIn('role', ['owner', 'admin'])->exists() ||
            ($classroom->allow_member_posting && $classroom->users()->where('users.id', $user->id)->exists()));

        if ($isPublic && !$canPostPublicly) {
            $isPublic = false; // Force private if not allowed
        }

        try {
            $type = (string) ($suggestion['type'] ?? 'unknown');
            $data = $suggestion['extracted_data'] ?? [];
            
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
                return response()->json(['ok' => false, 'error' => 'סוג תוכן לא מזוהה'], 422);
            }

            return response()->json([
                'ok' => true,
                'message' => $summary,
                'is_public' => $isPublic
            ]);

        } catch (\Exception $e) {
            Log::error('Frontend AI Add failed', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'שגיאה בשמירת הנתונים'], 500);
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
