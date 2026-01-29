<?php

namespace App\Filament\Resources\BuilderTemplateResource\Pages;

use App\Filament\Resources\BuilderTemplateResource;
use App\Services\Builder\TemplateManager;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListBuilderTemplates extends ListRecords
{
    protected static string $resource = BuilderTemplateResource::class;

    /**
     * Ensure default templates exist.
     */
    public function mount(): void
    {
        parent::mount();

        app(TemplateManager::class)->ensureDefaultTemplates();
    }

    /**
     * Define header actions.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refreshPopups')
                ->label('Refresh All Popups')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Refresh All Popup Templates')
                ->modalDescription('This will update all popup templates with the latest default content. Existing customizations will be preserved if templates have been published.')
                ->action(function (): void {
                    $templateManager = app(TemplateManager::class);
                    $templateManager->ensureDefaultTemplates();
                    
                    $this->notification()
                        ->title('Popups Refreshed')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('createPopup')
                ->label('Create Popup Template')
                ->form([
                    TextInput::make('name')
                        ->label('Popup Name')
                        ->required()
                        ->maxLength(100),
                ])
                ->action(function (array $data): void {
                    $template = app(TemplateManager::class)->createPopupTemplate($data['name']);

                    $this->redirect(BuilderTemplateResource::getUrl('edit', ['record' => $template]));
                }),
        ];
    }
}
