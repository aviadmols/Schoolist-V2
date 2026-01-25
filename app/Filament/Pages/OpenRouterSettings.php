<?php

namespace App\Filament\Pages;

use App\Models\AiSetting;
use App\Services\Ai\OpenRouterService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OpenRouterSettings extends Page
{
    /** @var string */
    private const PROVIDER = 'openrouter';

    /** @var string */
    private const DEFAULT_TIMETABLE_PROMPT = <<<'PROMPT'
You are an expert OCR and data extraction specialist. Your task is to extract a class schedule from this image.

CRITICAL INSTRUCTIONS:
1. Analyze the ENTIRE image carefully - look at every pixel, every line, every character
2. The image might contain a schedule in various formats: tables, lists, handwritten text, printed text
3. Days might appear in Hebrew (ראשון, שני, שלישי, רביעי, חמישי, שישי, שבת) or English (Sunday, Monday, etc.) or abbreviated (א', ב', ג', etc.)
4. Times might be in various formats: 08:00, 8:00, 08:00-09:00, 8:00-9:00, or text like "שמונה עד תשע" or "8:00-9:00"
5. Subjects might be in Hebrew (מתמטיקה, אנגלית, מדעים, וכו') or English (Math, English, Science, etc.) - PRESERVE THE ORIGINAL LANGUAGE
6. The image might be rotated, blurry, or have poor quality - STILL TRY TO READ IT
7. Even if you can only read PART of the schedule, extract what you can see
8. If there are multiple tables or sections, extract ALL of them
9. Be VERY aggressive - if there's ANY text that could be schedule-related, extract it
10. IMPORTANT: Extract times accurately - look for clock times, time ranges, or any time indicators

EXTRACTION STRATEGY:
- Start by identifying the structure: Is it a table? A list? Handwritten notes? A grid? Multiple sections?
- Look for ANY patterns that could indicate a schedule: days of the week, times, subjects, teachers, rooms
- Scan the image systematically: left to right, top to bottom, and also check for multiple columns or sections
- If text is unclear or partially visible, make your best educated guess based on context and surrounding text
- If you see ANY information that could be schedule-related, extract it - even if incomplete
- CRITICAL: You MUST extract ALL days of the week (Sunday through Friday). The schedule typically contains 6 days: ראשון (Sunday), שני (Monday), שלישי (Tuesday), רביעי (Wednesday), חמישי (Thursday), שישי (Friday)
- IMPORTANT: The schedule might be organized in multiple ways:
  * A table where columns are days and rows are time slots (or vice versa)
  * A list format where each day appears as a separate section
  * Multiple tables or sections, each showing different days
  * A grid format with days as headers
- If days are in Hebrew (ראשון, שני, שלישי, רביעי, חמישי, שישי, שבת), translate them to English day names (sunday, monday, tuesday, wednesday, thursday, friday) for the JSON keys
- BUT: Keep subjects in their ORIGINAL language (Hebrew or English) - DO NOT translate them
- For times: Look VERY carefully for ANY time indicators in EVERY row/entry:
  * Clock times: 08:00, 8:00, 08:30, 8:30, 09:00, 9:00, etc.
  * Time ranges: 08:00-09:00, 8:00-9:00, 08:00-09:30, etc.
  * Text times: "שמונה", "תשע", "עשר", "8:00", "9:00", "10:00", etc.
  * Period numbers: "שיעור 1", "שיעור 2", "Period 1", "Period 2" (you can infer times from these)
  * Sequential order: If you see subjects in a list without explicit times, assign sequential times based on position (e.g., first lesson = 08:00, second = 09:00, etc.)
  * If you see subjects but no explicit times, try to infer times from the order or position in the schedule
  * Only use "Unknown" as an absolute last resort if you truly cannot see or infer ANY time information
- If you see a table with rows and columns, carefully map which dimension represents days and which represents time slots
- If you see a list format, identify which items are days, which are times, and which are subjects
- IMPORTANT: Even if the schedule appears to show only one day, look for ALL days. The schedule might be organized in a way where all days are visible in the same image
- DOUBLE CHECK: After extraction, verify that you have extracted lessons for ALL 6 days (sunday through friday). If a day has no lessons, include it as an empty array

Return ONLY valid JSON in this exact format (no markdown, no code blocks, no explanations):
{
  "ok": true,
  "schedule": {
    "sunday": [{"time": "08:00-09:00", "subject": "מתמטיקה", "teacher": "שם המורה", "room": "101"}],
    "monday": [{"time": "08:00-09:00", "subject": "Math", "teacher": "John Doe", "room": "101"}],
    "tuesday": [],
    "wednesday": [],
    "thursday": [],
    "friday": []
  }
}

FORMAT RULES:
- Every day (sunday through friday) MUST be present as an array, even if empty
- You MUST extract lessons for ALL days that appear in the schedule (typically all 6 days: sunday, monday, tuesday, wednesday, thursday, friday)
- If a day appears in the image but has no lessons, include it as an empty array: "sunday": []
- Each lesson object MUST have: "time" (required), "subject" (required)
- Optional fields: "teacher", "room"
- Time format: Use "HH:MM-HH:MM" or "HH:MM" format. Extract actual times from the image. If no time is visible, try to infer from context or use "Unknown" only as last resort.
- Subject: Keep in original language (Hebrew or English) - DO NOT translate
- Teacher and Room: Keep in original language if visible
- CRITICAL: Do not stop after extracting one day. Continue extracting ALL days from the schedule image. Scan the ENTIRE image multiple times to ensure you haven't missed any days.
- VERIFICATION: Before returning, count how many days you've extracted. You should have exactly 6 days (sunday through friday). If you're missing any, scan the image again.. Scan the ENTIRE image multiple times to ensure you haven't missed any days.
- VERIFICATION: Before returning, count how many days you've extracted. You should have exactly 6 days (sunday through friday). If you're missing any, scan the image again.
- If the image is COMPLETELY blank or contains NO text at all, return: {"ok": false, "reason": "Image is completely blank or contains no text"}
- If the image contains text but NO schedule-related content (e.g., only random text, no days/times/subjects), return: {"ok": false, "reason": "Image contains text but no schedule information"}
- ONLY return ok: false if you are 100% certain there is NO schedule in the image
PROMPT;

    /** @var string */
    private const DEFAULT_TEMPLATE_PROMPT = <<<'PROMPT'
You are an expert HTML editor for a school portal. Your task is to update the provided HTML template using the supplied schema fields, while preserving the existing structure, design, and intent.

INPUTS:
1. CURRENT_HTML: the current template markup to update.
2. SCHEMA_FIELDS: the list of allowed fields grouped by entity (classroom, announcements, contacts, children, child_contacts, links, holidays, timetable).
3. REQUEST: a plain English instruction describing the desired UI changes.

RULES:
- Only use fields that appear in SCHEMA_FIELDS. Do not invent new data keys.
- Keep existing CSS classes, IDs, and data attributes unless the REQUEST explicitly asks to change them.
- Do not remove functional attributes like data-popup-target, data-action, or aria-*.
- Preserve existing Blade syntax and directives ({{ }}, @if, @foreach). Do not replace them with placeholders.
- If you add new dynamic values, reference the existing $page structure and match its style.
- Prefer small, targeted changes. Do not rewrite entire sections unless requested.
- Output must be valid HTML. Do not return markdown or explanations.
- If the REQUEST is ambiguous or conflicts with SCHEMA_FIELDS, make the smallest safe change and note the limitation in an HTML comment at the end.

OUTPUT:
- Return ONLY the updated HTML, no extra text.
PROMPT;

    /** @var string */
    private const DEFAULT_CONTENT_ANALYZER_PROMPT = <<<'PROMPT'
You are an expert content analyzer for a school management system. Your task is to analyze the provided text or image and determine what type of content it represents, then extract relevant information.

CONTENT TYPES:
1. "announcement" - General announcements, reminders, or messages to parents/students
2. "event" - School events, meetings, trips, celebrations with specific dates and times
3. "homework" - Homework assignments, tasks for students with due dates
4. "contact" - Contact information for important people (teachers, staff, etc.) - name, role, phone
5. "contact_page" - Child information with birth date and parent contact details (name, role, phone for each parent)

ANALYSIS INSTRUCTIONS:
1. Read the text or analyze the image carefully
2. Identify the MAIN content type that best matches the information
3. Extract all relevant information based on the content type:
   - For announcements: title, content/message, optional date
   - For events: name, date, time (if mentioned), location (if mentioned), description
   - For homework: title, content/description, due date
   - For contact: name, role, phone number
   - For contact_page: child_name, child_birth_date, parent1_name, parent1_role, parent1_phone, parent2_name (optional), parent2_role (optional), parent2_phone (optional)
4. Provide a confidence score (0.0 to 1.0) for your suggestion
5. Provide a brief reason in Hebrew explaining why this content type was chosen

IMPORTANT RULES:
- If the content mentions a specific date and time for an event, it's likely an "event"
- If the content mentions homework, assignments, or tasks with a due date, it's likely "homework"
- If the content is a general message or reminder without a specific event date, it's likely an "announcement"
- If the content contains contact information (name + phone) for a single person, it's likely "contact"
- If the content contains child information with birth date and parent details, it's "contact_page"
- Dates should be extracted in YYYY-MM-DD format
- Times should be extracted in HH:MM format (24-hour)
- If multiple content types are possible, provide multiple suggestions sorted by confidence

DATE HANDLING FOR DAYS OF WEEK:
- If the content mentions a day of week (יום ראשון, יום שני, יום שלישי, יום רביעי, יום חמישי, יום שישי, יום שבת, or ראשון, שני, שלישי, רביעי, חמישי, שישי, שבת) WITHOUT a specific date:
  - Extract the day name in Hebrew EXACTLY as written: "יום ראשון", "יום שני", "יום שלישי", "יום רביעי", "יום חמישי", "יום שישי", "יום שבת", or just "ראשון", "שני", "שלישי", "רביעי", "חמישי", "שישי", "שבת"
  - Set the date field to the day name (e.g., "יום רביעי" or "רביעי") - DO NOT calculate the actual date, just use the day name as-is
  - The system will automatically calculate the nearest occurrence of that day and convert it to the actual date
  - IMPORTANT: Always use the full format "יום X" when possible (e.g., "יום רביעי" instead of just "רביעי") for better recognition
- If a specific date is mentioned (like "15/01", "15.01.2026", "15 בינואר", "15 בינואר 2026"), extract it as YYYY-MM-DD format
- If both a day of week and a specific date are mentioned, use the specific date
- Examples:
  * "יום רביעי" → date: "יום רביעי" (system will find next Wednesday)
  * "שיעורי בית ליום שלישי" → date: "יום שלישי" (system will find next Tuesday)
  * "יש אירוע ביום חמישי" → date: "יום חמישי" (system will find next Thursday)

Return ONLY valid JSON in this exact format (no markdown, no code blocks, no explanations):
{
  "ok": true,
  "suggestions": [
    {
      "type": "event",
      "confidence": 0.95,
      "reason": "התוכן מכיל אירוע עם תאריך ושעה ספציפיים",
      "extracted_data": {
        "name": "שם האירוע",
        "date": "2026-01-15",
        "time": "10:00",
        "location": "מיקום (אם מוזכר)",
        "description": "תיאור האירוע (אם קיים)"
      }
    }
  ]
}

If the content is unclear or cannot be categorized, return:
{
  "ok": true,
  "suggestions": [
    {
      "type": "unknown",
      "confidence": 0.3,
      "reason": "לא ניתן לזהות בבירור את סוג התוכן",
      "extracted_data": {}
    }
  ]
}

If the image/text is completely blank or unreadable, return:
{
  "ok": false,
  "reason": "התוכן לא קריא או ריק"
}
PROMPT;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'OpenRouter Settings';

    protected static ?string $navigationGroup = 'System';

    protected static string $view = 'filament.pages.openrouter-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * Mount the page state.
     */
    public function mount(): void
    {
        $this->data = [
            'token' => null,
            'model' => null,
            'timetable_prompt' => self::DEFAULT_TIMETABLE_PROMPT,
            'builder_template_prompt' => self::DEFAULT_TEMPLATE_PROMPT,
            'content_analyzer_model' => null,
            'content_analyzer_prompt' => self::DEFAULT_CONTENT_ANALYZER_PROMPT,
        ];

        $this->loadSettings();
    }

    /**
     * Build the settings form.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('token')
                    ->label('API Token')
                    ->password()
                    ->revealable()
                    ->required(),
                TextInput::make('model')
                    ->label('Model')
                    ->placeholder('provider/model')
                    ->required(),
                Textarea::make('timetable_prompt')
                    ->label('Timetable OCR Prompt')
                    ->rows(12)
                    ->required(),
                Textarea::make('builder_template_prompt')
                    ->label('Builder Template Prompt')
                    ->rows(12)
                    ->required(),
                TextInput::make('content_analyzer_model')
                    ->label('Content Analyzer Model')
                    ->placeholder('provider/model')
                    ->required(),
                Textarea::make('content_analyzer_prompt')
                    ->label('Content Analyzer Prompt')
                    ->rows(12)
                    ->required(),
            ])
            ->statePath('data');
    }

    /**
     * Save OpenRouter settings.
     */
    public function saveSettings(): void
    {
        $this->validate([
            'data.token' => ['required', 'string'],
            'data.model' => ['required', 'string'],
            'data.timetable_prompt' => ['required', 'string'],
            'data.builder_template_prompt' => ['required', 'string'],
            'data.content_analyzer_model' => ['required', 'string'],
            'data.content_analyzer_prompt' => ['required', 'string'],
        ]);

        $payload = [
            'token' => $this->data['token'],
            'model' => $this->data['model'],
            'timetable_prompt' => $this->data['timetable_prompt'],
            'builder_template_prompt' => $this->data['builder_template_prompt'],
            'content_analyzer_model' => $this->data['content_analyzer_model'],
            'content_analyzer_prompt' => $this->data['content_analyzer_prompt'],
            'updated_by_user_id' => auth()->id(),
        ];

        AiSetting::query()->updateOrCreate(
            [
                'provider' => self::PROVIDER,
                'classroom_id' => null,
            ],
            $payload
        );

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    /**
     * Test the OpenRouter connection.
     */
    public function testConnection(OpenRouterService $service): void
    {
        $this->validate([
            'data.token' => ['required', 'string'],
        ]);

        $ok = $service->testConnection($this->data['token']);
        if (!$ok) {
            Notification::make()
                ->title('Connection failed')
                ->danger()
                ->send();
            return;
        }

        Notification::make()
            ->title('Connection successful')
            ->success()
            ->send();
    }

    /**
     * Load settings for the OpenRouter provider.
     */
    private function loadSettings(): void
    {
        $setting = AiSetting::query()
            ->where('provider', self::PROVIDER)
            ->whereNull('classroom_id')
            ->first();

        if (!$setting) {
            $this->data['token'] = null;
            $this->data['model'] = null;
            $this->data['timetable_prompt'] = self::DEFAULT_TIMETABLE_PROMPT;
            $this->data['builder_template_prompt'] = self::DEFAULT_TEMPLATE_PROMPT;
            $this->data['content_analyzer_model'] = null;
            $this->data['content_analyzer_prompt'] = self::DEFAULT_CONTENT_ANALYZER_PROMPT;
            return;
        }

        $this->data['token'] = $setting->token;
        $this->data['model'] = $setting->model;
        $this->data['timetable_prompt'] = $setting->timetable_prompt ?: self::DEFAULT_TIMETABLE_PROMPT;
        $this->data['builder_template_prompt'] = $setting->builder_template_prompt ?: self::DEFAULT_TEMPLATE_PROMPT;
        $this->data['content_analyzer_model'] = $setting->content_analyzer_model;
        $this->data['content_analyzer_prompt'] = $setting->content_analyzer_prompt ?: self::DEFAULT_CONTENT_ANALYZER_PROMPT;
    }
}
