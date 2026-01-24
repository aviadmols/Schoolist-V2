<?php

namespace App\Filament\Resources\BuilderTemplateResource\Pages;

use App\Filament\Resources\BuilderTemplateResource;
use App\Models\BuilderTemplateVersion;
use App\Models\AiSetting;
use App\Models\Classroom;
use App\Services\Ai\OpenRouterService;
use App\Services\Builder\TemplateManager;
use Filament\Actions;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

class EditBuilderTemplate extends EditRecord
{
    protected static string $resource = BuilderTemplateResource::class;

    /**
     * Customize form actions.
     */
    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('saveDraft')
                ->label('Save Draft')
                ->action('save'),
        ];
    }

    /**
     * Define header actions for publish/revert.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('insertSchemaFields')
                ->label('Insert Schema Fields')
                ->form([
                    Select::make('classroom_id')
                        ->label('Classroom')
                        ->options(Classroom::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    MultiSelect::make('schema_fields')
                        ->label('Schema Fields')
                        ->options($this->getSchemaFieldOptions())
                        ->required(),
                ])
                ->action(function (array $data, OpenRouterService $service): void {
                    $this->createTemplateHtmlWithSchemaFields($data, $service);
                }),
            Actions\Action::make('publish')
                ->label('Publish')
                ->requiresConfirmation()
                ->action(function (): void {
                    $manager = app(TemplateManager::class);
                    $manager->assertTemplateIsSafe(
                        (string) ($this->record->draft_html ?? ''),
                        $this->record->draft_css,
                        $this->record->draft_js
                    );
                    $manager->publishTemplate($this->record);
                }),
            Actions\Action::make('revert')
                ->label('Revert')
                ->form([
                    Select::make('version_id')
                        ->label('Version')
                        ->options(function (): array {
                            return BuilderTemplateVersion::query()
                                ->where('template_id', $this->record->id)
                                ->orderByDesc('created_at')
                                ->get()
                                ->mapWithKeys(function (BuilderTemplateVersion $version): array {
                                    $label = $version->version_type.' - '.$version->created_at?->toDateTimeString();

                                    return [$version->id => $label];
                                })
                                ->all();
                        })
                        ->required(),
                    Toggle::make('publish_after_revert')
                        ->label('Publish after revert')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $version = BuilderTemplateVersion::query()
                        ->where('template_id', $this->record->id)
                        ->findOrFail($data['version_id']);

                    app(TemplateManager::class)->revertTemplateToVersion(
                        $this->record,
                        $version,
                        (bool) $data['publish_after_revert']
                    );
                }),
        ];
    }

    /**
     * Validate and mutate form data before save.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(TemplateManager::class)->assertTemplateIsSafe(
            (string) ($data['draft_html'] ?? ''),
            $data['draft_css'] ?? null,
            $data['draft_js'] ?? null
        );

        $data['updated_by'] = auth()->id();

        return $data;
    }

    /**
     * Create updated template HTML using schema fields.
     */
    protected function createTemplateHtmlWithSchemaFields(array $data, OpenRouterService $service): void
    {
        $classroomId = (int) ($data['classroom_id'] ?? 0);
        $schemaFields = $data['schema_fields'] ?? [];
        if (!$classroomId || !is_array($schemaFields) || $schemaFields === []) {
            Notification::make()
                ->title('Classroom and fields are required')
                ->danger()
                ->send();
            return;
        }

        $setting = $this->getGlobalAiSettings();
        if (!$setting || !$setting->token || !$setting->model || !$setting->builder_template_prompt) {
            Notification::make()
                ->title('OpenRouter settings are missing')
                ->danger()
                ->send();
            return;
        }

        $html = (string) ($this->record->draft_html ?? '');
        if ($html === '') {
            Notification::make()
                ->title('HTML is empty')
                ->danger()
                ->send();
            return;
        }

        $prompt = $this->buildTemplatePrompt($setting->builder_template_prompt, $schemaFields, $html);
        $responseHtml = $service->requestTemplateUpdate($setting->token, $setting->model, $prompt, $classroomId);
        if (!$responseHtml) {
            Notification::make()
                ->title('OpenRouter returned an empty response')
                ->danger()
                ->send();
            return;
        }

        $this->record->update([
            'draft_html' => $responseHtml,
            'updated_by' => auth()->id(),
        ]);

        $this->record->refresh();
        $this->form->fill($this->record->toArray());

        Notification::make()
            ->title('HTML updated')
            ->success()
            ->send();
    }

    /**
     * Build the prompt for template updates.
     */
    protected function buildTemplatePrompt(string $basePrompt, array $schemaFields, string $html): string
    {
        $fields = implode("\n", array_map(static fn (string $field): string => '- '.$field, $schemaFields));

        return trim($basePrompt)
            ."\n\nAvailable schema fields:\n".$fields
            ."\n\nCurrent HTML:\n".$html
            ."\n\nReturn only the updated HTML with no additional text.";
    }

    /**
     * Build the schema field options.
     *
     * @return array<string, array<string, string>>
     */
    protected function getSchemaFieldOptions(): array
    {
        return [
            'Classroom' => $this->getTableFieldOptions('classrooms'),
            'Timetable Entries' => $this->getTableFieldOptions('timetable_entries'),
            'Important Contacts' => $this->getTableFieldOptions('important_contacts'),
            'Children' => $this->getTableFieldOptions('children'),
            'Child Contacts' => $this->getTableFieldOptions('child_contacts'),
            'Links' => $this->getTableFieldOptions('class_links'),
            'Holidays' => $this->getTableFieldOptions('holidays'),
            'Announcements' => $this->getTableFieldOptions('announcements'),
        ];
    }

    /**
     * Get table field options for a schema table.
     *
     * @return array<string, string>
     */
    protected function getTableFieldOptions(string $table): array
    {
        $columns = Schema::getColumnListing($table);
        $options = [];

        foreach ($columns as $column) {
            $key = $table.'.'.$column;
            $options[$key] = $key;
        }

        return $options;
    }

    /**
     * Get global AI settings.
     */
    protected function getGlobalAiSettings(): ?AiSetting
    {
        return AiSetting::query()
            ->where('provider', 'openrouter')
            ->whereNull('classroom_id')
            ->first();
    }
}
