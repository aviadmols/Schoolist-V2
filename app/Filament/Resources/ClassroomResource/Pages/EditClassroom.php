<?php

namespace App\Filament\Resources\ClassroomResource\Pages;

use App\Filament\Resources\ClassroomResource;
use App\Models\AiSetting;
use App\Models\Announcement;
use App\Models\Child;
use App\Models\ChildContact;
use App\Models\ImportantContact;
use App\Services\Ai\OpenRouterService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;

class EditClassroom extends EditRecord
{
    protected static string $resource = ClassroomResource::class;

    /** @var string */
    private const AI_PROVIDER = 'openrouter';

    /** @var string */
    private const AI_SUGGESTION_SESSION_KEY = 'ai_quick_add_suggestion_';

    /** @var int */
    private const ANNOUNCEMENT_TITLE_MAX_LENGTH = 80;

    /** @var array<int, string> */
    private const DATE_FORMATS = ['Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y'];

    /** @var array<string, int> */
    private const HEBREW_DAYS = [
        'ראשון' => 0,
        'יום ראשון' => 0,
        'שני' => 1,
        'יום שני' => 1,
        'שלישי' => 2,
        'יום שלישי' => 2,
        'רביעי' => 3,
        'יום רביעי' => 3,
        'חמישי' => 4,
        'יום חמישי' => 4,
        'שישי' => 5,
        'יום שישי' => 5,
        'שבת' => 6,
        'יום שבת' => 6,
    ];

    /** @var string */
    private const CONTENT_ANALYZER_SUFFIX = "SCHEMA FIELDS:\n- announcements: title, content, occurs_on_date, occurs_at_time, location\n- contacts: first_name, last_name, role, phone, email\n- children: name, birth_date\n- child_contacts: name, relation, phone\n\nReturn JSON that matches the schema above.";

    /** @var array<string, mixed>|null */
    public ?array $aiSuggestion = null;

    /**
     * Define header actions.
     *
     * @return array<int, Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('aiQuickAdd')
                ->label('AI Quick Add')
                ->icon('heroicon-o-plus-circle')
                ->extraAttributes([
                    'style' => 'position: fixed; bottom: 24px; right: 24px; z-index: 50;',
                ])
                ->form([
                    Textarea::make('content_text')
                        ->label('Content')
                        ->rows(8),
                    FileUpload::make('content_file')
                        ->label('Attachment')
                        ->acceptedFileTypes(['image/*'])
                        ->storeFiles(false),
                ])
                ->action(function (array $data, OpenRouterService $service): void {
                    $this->analyzeAiContent($data, $service);
                }),
        ];
    }

    /**
     * Analyze content using OpenRouter.
     */
    protected function analyzeAiContent(array $data, OpenRouterService $service): void
    {
        $startTime = microtime(true);
        $requestId = uniqid('admin_ai_analyze_', true);
        
        Log::info("[Admin AI Analyze] Request started", [
            'request_id' => $requestId,
            'classroom_id' => $this->record?->id,
            'user_id' => auth()->id(),
            'has_text' => isset($data['content_text']),
            'has_file' => isset($data['content_file']),
        ]);

        $text = trim((string) ($data['content_text'] ?? ''));
        $file = $data['content_file'] ?? null;

        if ($text === '' && !$file instanceof UploadedFile) {
            Log::warning("[Admin AI Analyze] Empty input", ['request_id' => $requestId]);
            Notification::make()
                ->title('Provide text or an image')
                ->danger()
                ->send();
            return;
        }

        if ($text !== '') {
            $file = null;
        }

        Log::info("[Admin AI Analyze] Input processed", [
            'request_id' => $requestId,
            'text_length' => strlen($text),
            'has_file' => $file instanceof UploadedFile,
            'file_size' => $file instanceof UploadedFile ? $file->getSize() : null,
        ]);

        $setting = $this->getAiSetting();
        if (!$setting || !$setting->token || !$setting->content_analyzer_model || !$setting->content_analyzer_prompt) {
            Log::error("[Admin AI Analyze] Missing AI settings", [
                'request_id' => $requestId,
                'has_setting' => $setting !== null,
            ]);
            Notification::make()
                ->title('OpenRouter settings are missing')
                ->danger()
                ->send();
            return;
        }

        $prompt = $this->buildContentAnalyzerPrompt($setting->content_analyzer_prompt, $text);
        [$imageMime, $imageBase64] = $this->resolveImagePayload($file);

        Log::info("[Admin AI Analyze] Prompt built", [
            'request_id' => $requestId,
            'prompt_length' => strlen($prompt),
            'prompt_preview' => substr($prompt, 0, 200) . '...',
            'has_image' => $imageMime !== null,
        ]);

        $apiStartTime = microtime(true);
        $response = $service->requestContentAnalysis(
            (string) $setting->token,
            (string) $setting->content_analyzer_model,
            $prompt,
            $imageMime,
            $imageBase64,
            $this->record?->id
        );
        $apiDuration = microtime(true) - $apiStartTime;

        Log::info("[Admin AI Analyze] API response received", [
            'request_id' => $requestId,
            'api_duration_seconds' => round($apiDuration, 2),
            'has_response' => $response !== null,
            'response_length' => $response ? strlen($response) : 0,
            'response_preview' => $response ? substr($response, 0, 500) : null,
        ]);

        if (!$response) {
            $error = $service->getLastError();
            Log::error("[Admin AI Analyze] API failed", [
                'request_id' => $requestId,
                'error' => $error,
            ]);
            Notification::make()
                ->title('OpenRouter request failed')
                ->body($error ?: 'OpenRouter returned an empty response.')
                ->danger()
                ->send();
            return;
        }

        $suggestion = $this->parseContentAnalyzerResponse($response);
        
        Log::info("[Admin AI Analyze] Response parsed", [
            'request_id' => $requestId,
            'has_suggestion' => $suggestion !== null,
            'suggestion_type' => $suggestion['type'] ?? null,
            'suggestion_keys' => $suggestion ? array_keys($suggestion) : null,
            'suggestion_data' => $suggestion,
        ]);

        if (!$suggestion) {
            Log::error("[Admin AI Analyze] Parse failed", [
                'request_id' => $requestId,
                'raw_response' => $response,
            ]);
            Notification::make()
                ->title('Unable to parse AI response')
                ->danger()
                ->send();
            return;
        }

        $totalDuration = microtime(true) - $startTime;
        Log::info("[Admin AI Analyze] Request completed", [
            'request_id' => $requestId,
            'total_duration_seconds' => round($totalDuration, 2),
        ]);

        $this->aiSuggestion = $suggestion;
        session()->put($this->getAiSuggestionSessionKey(), $suggestion);
        $this->showAiSuggestionNotification($suggestion);
    }

    /**
     * Confirm AI suggestion and create content.
     */
    #[On('confirm-ai-suggestion')]
    public function confirmAiSuggestion(): void
    {
        if (!$this->aiSuggestion) {
            $this->aiSuggestion = session()->get($this->getAiSuggestionSessionKey());
        }

        if (!$this->aiSuggestion) {
            Notification::make()
                ->title('No suggestion available')
                ->danger()
                ->send();
            return;
        }

        $startTime = microtime(true);
        $requestId = uniqid('admin_ai_store_', true);
        
        Log::info("[Admin AI Store] Request started", [
            'request_id' => $requestId,
            'classroom_id' => $this->record?->id,
            'user_id' => auth()->id(),
            'has_suggestion' => $this->aiSuggestion !== null,
            'suggestion_type' => $this->aiSuggestion['type'] ?? null,
        ]);

        try {
            Log::info("[Admin AI Store] Creating content", [
                'request_id' => $requestId,
                'suggestion' => $this->aiSuggestion,
            ]);
            
            $summary = $this->createContentFromSuggestion($this->aiSuggestion);
            
            $totalDuration = microtime(true) - $startTime;
            Log::info("[Admin AI Store] Request completed", [
                'request_id' => $requestId,
                'total_duration_seconds' => round($totalDuration, 2),
                'summary' => $summary,
            ]);
        } catch (\Throwable $exception) {
            $totalDuration = microtime(true) - $startTime;
            Log::error("[Admin AI Store] Failed", [
                'request_id' => $requestId,
                'classroom_id' => $this->record?->id,
                'duration_seconds' => round($totalDuration, 2),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'suggestion' => $this->aiSuggestion,
            ]);
            Notification::make()
                ->title('Unable to create content')
                ->body('Check logs for details. Request ID: ' . $requestId)
                ->danger()
                ->send();
            return;
        }

        $this->aiSuggestion = null;
        session()->forget($this->getAiSuggestionSessionKey());

        Notification::make()
            ->title('Content created')
            ->body($summary)
            ->success()
            ->send();
    }

    /**
     * Retry AI analysis.
     */
    #[On('retry-ai-suggestion')]
    public function retryAiSuggestion(): void
    {
        $this->aiSuggestion = null;
        session()->forget($this->getAiSuggestionSessionKey());

        Notification::make()
            ->title('Run AI Quick Add again')
            ->success()
            ->send();
    }

    /**
     * Build prompt for content analyzer.
     */
    protected function buildContentAnalyzerPrompt(string $basePrompt, string $text): string
    {
        $prompt = trim($basePrompt);
        
        // Add current date and time context
        $now = \Carbon\Carbon::now($this->record->timezone ?? 'Asia/Jerusalem');
        $prompt .= "\n\nCURRENT_DATE_AND_TIME:\n";
        $prompt .= "Date: ".$now->format('d.m.Y')."\n";
        $prompt .= "Time: ".$now->format('H:i')."\n";
        $prompt .= "Day of week: ".$now->format('l')." (".['ראשון','שני','שלישי','רביעי','חמישי','שישי','שבת'][$now->dayOfWeek].")\n";
        $prompt .= "Use this context to calculate future dates when needed.";
        
        if ($text !== '') {
            $prompt .= "\n\nINPUT_TEXT:\n".$text;
        }

        $prompt .= "\n\n".self::CONTENT_ANALYZER_SUFFIX;

        return $prompt;
    }

    /**
     * Resolve the image payload from an upload.
     *
     * @return array{0: string|null, 1: string|null}
     */
    protected function resolveImagePayload($file): array
    {
        if (!$file instanceof UploadedFile) {
            return [null, null];
        }

        $path = $file->getRealPath();
        if (!$path) {
            return [null, null];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return [null, null];
        }

        return [$file->getMimeType() ?: 'image/jpeg', base64_encode($content)];
    }

    /**
     * Parse content analyzer response.
     *
     * @return array<string, mixed>|null
     */
    protected function parseContentAnalyzerResponse(string $responseText): ?array
    {
        $json = $this->extractJson($responseText);
        $payload = json_decode($json, true);
        if (!is_array($payload) || empty($payload['suggestions'][0])) {
            return null;
        }

        return $payload['suggestions'][0];
    }

    /**
     * Show notification for AI suggestion.
     */
    protected function showAiSuggestionNotification(array $suggestion): void
    {
        $type = (string) ($suggestion['type'] ?? 'unknown');
        $reason = (string) ($suggestion['reason'] ?? '');

        Notification::make()
            ->title('AI suggestion ready')
            ->body($type.($reason ? ' - '.$reason : ''))
            ->actions([
                NotificationAction::make('confirm')
                    ->label('Confirm & Create')
                    ->button()
                    ->dispatch('confirm-ai-suggestion'),
                NotificationAction::make('retry')
                    ->label('Retry')
                    ->dispatch('retry-ai-suggestion'),
            ])
            ->persistent()
            ->send();
    }

    /**
     * Get the session key for storing AI suggestion.
     */
    protected function getAiSuggestionSessionKey(): string
    {
        return self::AI_SUGGESTION_SESSION_KEY.($this->record?->id ?? 'new');
    }

    /**
     * Create content from suggestion.
     */
    protected function createContentFromSuggestion(array $suggestion): string
    {
        $requestId = uniqid('admin_create_', true);
        $type = (string) ($suggestion['type'] ?? 'unknown');
        $data = $suggestion['extracted_data'] ?? [];
        $classroomId = (int) $this->record->id;

        Log::info("[Admin Create Content] Starting", [
            'request_id' => $requestId,
            'type' => $type,
            'classroom_id' => $classroomId,
            'data_keys' => array_keys($data),
            'data' => $data,
        ]);

        if ($type === 'contact') {
            $contacts = $this->normalizeContactItems($data);
            Log::info("[Admin Create Content] Creating contacts", [
                'request_id' => $requestId,
                'contacts_count' => count($contacts),
                'contacts' => $contacts,
            ]);
            
            foreach ($contacts as $index => $contact) {
                $name = (string) ($contact['name'] ?? '');
                [$firstName, $lastName] = $this->splitName($name);

                Log::info("[Admin Create Content] Creating contact", [
                    'request_id' => $requestId,
                    'index' => $index,
                    'name' => $name,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'role' => $contact['role'] ?? '',
                    'phone' => $contact['phone'] ?? '',
                ]);

                try {
                    ImportantContact::create([
                        'classroom_id' => $classroomId,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'role' => (string) ($contact['role'] ?? ''),
                        'phone' => (string) ($contact['phone'] ?? ''),
                        'email' => (string) ($contact['email'] ?? ''),
                    ]);
                } catch (\Exception $e) {
                    Log::error("[Admin Create Content] Failed to create contact", [
                        'request_id' => $requestId,
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'contact_data' => $contact,
                    ]);
                    throw $e;
                }
            }

            Log::info("[Admin Create Content] Contacts created", ['request_id' => $requestId]);
            return 'Contacts created';
        }

        if ($type === 'contact_page') {
            Log::info("[Admin Create Content] Creating child contact page", [
                'request_id' => $requestId,
                'child_name' => $data['child_name'] ?? null,
                'child_birth_date' => $data['child_birth_date'] ?? null,
                'parent1_name' => $data['parent1_name'] ?? null,
                'parent2_name' => $data['parent2_name'] ?? null,
            ]);
            
            try {
                $child = Child::create([
                    'classroom_id' => $classroomId,
                    'name' => (string) ($data['child_name'] ?? ''),
                    'birth_date' => $this->normalizeDate($data['child_birth_date'] ?? null),
                ]);
            } catch (\Exception $e) {
                Log::error("[Admin Create Content] Failed to create child", [
                    'request_id' => $requestId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'child_data' => $data,
                ]);
                throw $e;
            }

            Log::info("[Admin Create Content] Child created", [
                'request_id' => $requestId,
                'child_id' => $child->id,
            ]);

            $this->createChildContact($child, $data['parent1_name'] ?? null, $data['parent1_role'] ?? null, $data['parent1_phone'] ?? null);
            $this->createChildContact($child, $data['parent2_name'] ?? null, $data['parent2_role'] ?? null, $data['parent2_phone'] ?? null);

            Log::info("[Admin Create Content] Child contact page created", ['request_id' => $requestId]);
            return 'Child contact page created';
        }

        if (in_array($type, ['announcement', 'event', 'homework'], true)) {
            $announcementType = $type === 'announcement' ? 'message' : $type;
            $items = $this->normalizeAnnouncementItems($data);
            
            Log::info("[Admin Create Content] Creating announcements", [
                'request_id' => $requestId,
                'announcement_type' => $announcementType,
                'items_count' => count($items),
                'items' => $items,
            ]);
            
            foreach ($items as $index => $item) {
                $dateValue = $item['date'] ?? $item['due_date'] ?? null;
                $dateData = $this->normalizeDateValue($dateValue);
                $title = $this->computeAnnouncementTitle(
                    (string) ($item['title'] ?? $item['name'] ?? ''),
                    (string) ($item['content'] ?? $item['description'] ?? '')
                );

                Log::info("[Admin Create Content] Creating announcement", [
                    'request_id' => $requestId,
                    'index' => $index,
                    'title' => $title,
                    'date_value' => $dateValue,
                    'date_data' => $dateData,
                    'time' => $item['time'] ?? null,
                    'location' => $item['location'] ?? null,
                ]);

                try {
                    $announcementData = [
                        'classroom_id' => $classroomId,
                        'user_id' => auth()->id(),
                        'type' => $announcementType,
                        'title' => $title,
                        'content' => (string) ($item['content'] ?? $item['description'] ?? ''),
                        'occurs_on_date' => $dateData['date'],
                        'day_of_week' => $dateData['day_of_week'],
                        'occurs_at_time' => $this->normalizeTime($item['time'] ?? null),
                        'location' => (string) ($item['location'] ?? ''),
                    ];
                    
                    Log::info("[Admin Create Content] Announcement data prepared", [
                        'request_id' => $requestId,
                        'index' => $index,
                        'data' => $announcementData,
                    ]);
                    
                    $announcement = Announcement::create($announcementData);
                    
                    Log::info("[Admin Create Content] Announcement created", [
                        'request_id' => $requestId,
                        'index' => $index,
                        'announcement_id' => $announcement->id,
                    ]);
                } catch (\Exception $e) {
                    Log::error("[Admin Create Content] Failed to create announcement", [
                        'request_id' => $requestId,
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'data' => [
                            'classroom_id' => $classroomId,
                            'user_id' => auth()->id(),
                            'type' => $announcementType,
                            'title' => $title,
                            'date_data' => $dateData,
                        ],
                    ]);
                    throw $e;
                }
            }

            Log::info("[Admin Create Content] Announcements created", [
                'request_id' => $requestId,
                'count' => count($items),
            ]);
            return 'Announcements created';
        }

        Log::error("[Admin Create Content] Unknown type", [
            'request_id' => $requestId ?? 'unknown',
            'type' => $type,
            'suggestion' => $suggestion,
        ]);
        throw new \InvalidArgumentException("Unknown suggestion type: {$type}");
    }

    /**
     * Create a child contact if data is present.
     */
    protected function createChildContact(Child $child, $name, $role, $phone): void
    {
        $nameText = trim((string) $name);
        $phoneText = trim((string) $phone);
        if ($nameText === '' || $phoneText === '') {
            return;
        }

        ChildContact::create([
            'child_id' => $child->id,
            'name' => $nameText,
            'relation' => $this->mapRelation((string) $role),
            'phone' => $phoneText,
        ]);
    }

    /**
     * Map relation to supported values.
     */
    protected function mapRelation(string $role): string
    {
        $normalized = strtolower($role);
        if (str_contains($normalized, 'father')) {
            return 'father';
        }
        if (str_contains($normalized, 'mother')) {
            return 'mother';
        }

        return 'other';
    }

    /**
     * Normalize date strings.
     */
    protected function normalizeDate($value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1) {
            return $text;
        }

        foreach (self::DATE_FORMATS as $format) {
            $date = \DateTime::createFromFormat($format, $text);
            if ($date instanceof \DateTime) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Normalize time strings.
     */
    protected function normalizeTime($value): ?string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (preg_match('/\b\d{1,2}:\d{2}\b/', $text, $matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Normalize date values for announcements.
     *
     * @return array{date: string|null, day_of_week: int|null}
     */
    protected function normalizeDateValue($value): array
    {
        $text = trim((string) $value);
        if ($text === '') {
            return ['date' => null, 'day_of_week' => null];
        }

        $date = $this->normalizeDate($text);
        if ($date) {
            // Calculate day_of_week from date
            try {
                $carbonDate = Carbon::parse($date, $this->getClassroomTimezone());
                $dayOfWeek = $carbonDate->dayOfWeek; // 0 (Sunday) to 6 (Saturday)
                return ['date' => $date, 'day_of_week' => $dayOfWeek];
            } catch (\Exception $e) {
                Log::warning("[Admin Create Content] Failed to parse date for day_of_week", [
                    'date' => $date,
                    'error' => $e->getMessage(),
                ]);
                return ['date' => $date, 'day_of_week' => null];
            }
        }

        $dayMatch = $this->extractHebrewDay($text);
        if ($dayMatch !== null) {
            $resolvedDate = $this->resolveHebrewDayDate($dayMatch, $text);
            if ($resolvedDate) {
                try {
                    $carbonDate = Carbon::parse($resolvedDate, $this->getClassroomTimezone());
                    $dayOfWeek = $carbonDate->dayOfWeek;
                    return [
                        'date' => $resolvedDate,
                        'day_of_week' => $dayOfWeek,
                    ];
                } catch (\Exception $e) {
                    Log::warning("[Admin Create Content] Failed to parse resolved date for day_of_week", [
                        'date' => $resolvedDate,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            return [
                'date' => $resolvedDate,
                'day_of_week' => null,
            ];
        }

        return ['date' => null, 'day_of_week' => null];
    }

    /**
     * Compute announcement title with a safe fallback.
     */
    protected function computeAnnouncementTitle(string $title, string $content): string
    {
        $titleText = trim($title);
        if ($titleText !== '') {
            return $titleText;
        }

        $contentText = trim($content);
        if ($contentText === '') {
            return 'Untitled';
        }

        return mb_substr($contentText, 0, self::ANNOUNCEMENT_TITLE_MAX_LENGTH);
    }

    /**
     * Normalize contact items from AI response.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeContactItems(array $data): array
    {
        if (!empty($data['contacts']) && is_array($data['contacts'])) {
            return array_values(array_filter($data['contacts'], 'is_array'));
        }

        if (!empty($data['items']) && is_array($data['items'])) {
            return array_values(array_filter($data['items'], 'is_array'));
        }

        return [$data];
    }

    /**
     * Normalize announcement items from AI response.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeAnnouncementItems(array $data): array
    {
        if (!empty($data['items']) && is_array($data['items'])) {
            return array_values(array_filter($data['items'], 'is_array'));
        }

        if (!empty($data['tasks']) && is_array($data['tasks'])) {
            return array_map(function ($task): array {
                return [
                    'title' => is_string($task) ? $task : '',
                    'content' => '',
                ];
            }, $data['tasks']);
        }

        return [$data];
    }

    /**
     * Extract a Hebrew day name from input text.
     */
    protected function extractHebrewDay(string $text): ?string
    {
        foreach (array_keys(self::HEBREW_DAYS) as $dayName) {
            if (str_contains($text, $dayName)) {
                return $dayName;
            }
        }

        return null;
    }

    /**
     * Resolve a Hebrew day name into a date string.
     */
    protected function resolveHebrewDayDate(string $dayName, string $text): ?string
    {
        if (!isset(self::HEBREW_DAYS[$dayName])) {
            return null;
        }

        $timezone = $this->getClassroomTimezone();
        $now = Carbon::now($timezone);
        $useNextWeek = str_contains($text, 'הבא');

        $target = $useNextWeek ? $now->copy()->next(self::HEBREW_DAYS[$dayName]) : $now->copy()->nextOrSame(self::HEBREW_DAYS[$dayName]);

        return $target->format('Y-m-d');
    }

    /**
     * Get the classroom timezone.
     */
    protected function getClassroomTimezone(): string
    {
        return $this->record?->timezone ?? config('app.timezone');
    }

    /**
     * Split a full name into first/last.
     *
     * @return array{0: string, 1: string}
     */
    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $firstName = array_shift($parts) ?: '';
        $lastName = trim(implode(' ', $parts));

        return [$firstName, $lastName];
    }

    /**
     * Extract JSON from a response.
     */
    protected function extractJson(string $responseText): string
    {
        $trimmed = trim($responseText);
        if ($trimmed !== '' && $trimmed[0] === '{') {
            return $trimmed;
        }

        preg_match('/\{.*\}/s', $responseText, $matches);

        return $matches[0] ?? $responseText;
    }

    /**
     * Get AI settings for the classroom.
     */
    protected function getAiSetting(): ?AiSetting
    {
        return AiSetting::query()
            ->where('provider', self::AI_PROVIDER)
            ->whereNull('classroom_id')
            ->first();
    }
}
