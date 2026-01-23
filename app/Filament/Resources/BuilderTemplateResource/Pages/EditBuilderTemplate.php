<?php

namespace App\Filament\Resources\BuilderTemplateResource\Pages;

use App\Filament\Resources\BuilderTemplateResource;
use App\Models\BuilderTemplateVersion;
use App\Services\Builder\TemplateManager;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;

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
}
