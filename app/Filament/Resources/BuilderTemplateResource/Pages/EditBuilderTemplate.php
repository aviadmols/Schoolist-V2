<?php

namespace App\Filament\Resources\BuilderTemplateResource\Pages;

use App\Filament\Resources\BuilderTemplateResource;
use Filament\Actions;
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
            Actions\Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }

    /**
     * Define header actions.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Validate and mutate form data before save.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
