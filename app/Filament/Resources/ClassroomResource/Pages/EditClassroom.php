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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EditClassroom extends EditRecord
{
    protected static string $resource = ClassroomResource::class;

    /** @var string */
    private const AI_PROVIDER = 'openrouter';

    /** @var string */
    private const AI_SUGGESTION_SESSION_KEY = 'ai_quick_add_suggestion_';

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
        $text = trim((string) ($data['content_text'] ?? ''));
        $file = $data['content_file'] ?? null;

        if ($text === '' && !$file instanceof UploadedFile) {
            Notification::make()
                ->title('Provide text or an image')
                ->danger()
                ->send();
            return;
        }

        if ($text !== '') {
            $file = null;
        }

        $setting = $this->getAiSetting();
        if (!$setting || !$setting->token || !$setting->content_analyzer_model || !$setting->content_analyzer_prompt) {
            Notification::make()
                ->title('OpenRouter settings are missing')
                ->danger()
                ->send();
            return;
        }

        $prompt = $this->buildContentAnalyzerPrompt($setting->content_analyzer_prompt, $text);
        [$imageMime, $imageBase64] = $this->resolveImagePayload($file);

        $response = $service->requestContentAnalysis(
            (string) $setting->token,
            (string) $setting->content_analyzer_model,
            $prompt,
            $imageMime,
            $imageBase64,
            $this->record?->id
        );

        if (!$response) {
            $error = $service->getLastError();
            Notification::make()
                ->title('OpenRouter request failed')
                ->body($error ?: 'OpenRouter returned an empty response.')
                ->danger()
                ->send();
            return;
        }

        $suggestion = $this->parseContentAnalyzerResponse($response);
        if (!$suggestion) {
            Notification::make()
                ->title('Unable to parse AI response')
                ->danger()
                ->send();
            return;
        }

        $this->aiSuggestion = $suggestion;
        session()->put($this->getAiSuggestionSessionKey(), $suggestion);
        $this->showAiSuggestionNotification($suggestion);
    }

    /**
     * Confirm AI suggestion and create content.
     */
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

        try {
            $summary = $this->createContentFromSuggestion($this->aiSuggestion);
        } catch (\Throwable $exception) {
            Log::error('AI quick add failed', [
                'classroom_id' => $this->record?->id,
                'error' => $exception->getMessage(),
            ]);
            Notification::make()
                ->title('Unable to create content')
                ->body('Check logs for details.')
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
                    ->action(function (): void {
                        $this->confirmAiSuggestion();
                    }),
                NotificationAction::make('retry')
                    ->label('Retry')
                    ->action(function (): void {
                        $this->retryAiSuggestion();
                    }),
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
        $type = (string) ($suggestion['type'] ?? 'unknown');
        $data = $suggestion['extracted_data'] ?? [];
        $classroomId = (int) $this->record->id;

        if ($type === 'contact') {
            $name = (string) ($data['name'] ?? '');
            [$firstName, $lastName] = $this->splitName($name);

            ImportantContact::create([
                'classroom_id' => $classroomId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => (string) ($data['role'] ?? ''),
                'phone' => (string) ($data['phone'] ?? ''),
                'email' => (string) ($data['email'] ?? ''),
            ]);

            return 'Contact created';
        }

        if ($type === 'contact_page') {
            $child = Child::create([
                'classroom_id' => $classroomId,
                'name' => (string) ($data['child_name'] ?? ''),
                'birth_date' => $this->normalizeDate($data['child_birth_date'] ?? null),
            ]);

            $this->createChildContact($child, $data['parent1_name'] ?? null, $data['parent1_role'] ?? null, $data['parent1_phone'] ?? null);
            $this->createChildContact($child, $data['parent2_name'] ?? null, $data['parent2_role'] ?? null, $data['parent2_phone'] ?? null);

            return 'Child contact page created';
        }

        if (in_array($type, ['announcement', 'event', 'homework'], true)) {
            $announcementType = $type === 'announcement' ? 'message' : $type;
            $date = $this->normalizeDate($data['date'] ?? $data['due_date'] ?? null);

            Announcement::create([
                'classroom_id' => $classroomId,
                'user_id' => auth()->id(),
                'type' => $announcementType,
                'title' => (string) ($data['title'] ?? $data['name'] ?? ''),
                'content' => (string) ($data['content'] ?? $data['description'] ?? ''),
                'occurs_on_date' => $date,
                'occurs_at_time' => $this->normalizeTime($data['time'] ?? null),
                'location' => (string) ($data['location'] ?? ''),
            ]);

            return 'Announcement created';
        }

        return 'No content created';
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

        return null;
    }

    /**
     * Normalize time strings.
     */
    protected function normalizeTime($value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
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
