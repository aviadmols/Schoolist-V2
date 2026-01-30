<?php

namespace App\Filament\Pages;

use App\Models\AiSetting;
use App\Services\Ai\OpenRouterService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

class OpenRouterSettings extends Page
{
    /** @var string */
    private const PROVIDER = 'openrouter';

    /** @var string */
    private const DEFAULT_TIMETABLE_PROMPT = '';

    /** @var string */
    private const DEFAULT_TEMPLATE_PROMPT = '';

    /** @var string */
    private const DEFAULT_CONTENT_ANALYZER_PROMPT = '';

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'OpenRouter Settings';

    protected static ?string $navigationGroup = 'System';

    protected static string $view = 'filament.pages.openrouter-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var bool */
    public bool $hasToken = false;

    /**
     * Mount the page state.
     */
    public function mount(): void
    {
        $this->data = [
            'token' => null,
            'model' => null,
            'timetable_prompt' => '',
            'builder_template_prompt' => '',
            'content_analyzer_model' => null,
            'content_analyzer_prompt' => '',
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
                    ->helperText($this->hasToken ? 'קיים טוקן שמור במערכת' : null)
                    ->placeholder($this->hasToken ? '********' : 'הכנס טוקן')
                    ->required(fn () => !$this->hasToken),
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
                    ->helperText('שינוי ה‑prompt עלול לשבור את הפענוח ב‑FrontendAiAddController. שמור על פורמט JSON כפי שהקוד מצפה לו.')
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
            'data.token' => ['nullable', 'string'],
            'data.model' => ['required', 'string'],
            'data.timetable_prompt' => ['required', 'string'],
            'data.builder_template_prompt' => ['required', 'string'],
            'data.content_analyzer_model' => ['required', 'string'],
            'data.content_analyzer_prompt' => ['required', 'string'],
        ]);

        $existing = AiSetting::query()
            ->where('provider', self::PROVIDER)
            ->whereNull('classroom_id')
            ->first();

        $token = $this->data['token'] ?: ($existing?->token);
        if (!$token) {
            throw ValidationException::withMessages([
                'data.token' => 'יש להזין טוקן או לשמור טוקן קיים במערכת',
            ]);
        }

        $payload = [
            'token' => $token,
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
            'data.model' => ['required', 'string'],
            'data.content_analyzer_model' => ['required', 'string'],
        ]);

        $ok = $service->testConnection($this->data['token']);
        if (!$ok) {
            Notification::make()
                ->title('Connection failed')
                ->danger()
                ->send();
            return;
        }

        $modelOk = $service->isModelAvailable($this->data['token'], $this->data['model']);
        $contentModelOk = $service->isModelAvailable($this->data['token'], $this->data['content_analyzer_model']);
        if (!$modelOk || !$contentModelOk) {
            Notification::make()
                ->title('Model check failed')
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
            $this->hasToken = false;
            $this->data['token'] = null;
            $this->data['model'] = null;
            $this->data['timetable_prompt'] = '';
            $this->data['builder_template_prompt'] = '';
            $this->data['content_analyzer_model'] = null;
            $this->data['content_analyzer_prompt'] = '';
            return;
        }

        $this->hasToken = !empty($setting->token);
        $this->data['token'] = null;
        $this->data['model'] = $setting->model;
        $this->data['timetable_prompt'] = $setting->timetable_prompt ?: '';
        $this->data['builder_template_prompt'] = $setting->builder_template_prompt ?: '';
        $this->data['content_analyzer_model'] = $setting->content_analyzer_model;
        $this->data['content_analyzer_prompt'] = $setting->content_analyzer_prompt ?: '';
    }
}
