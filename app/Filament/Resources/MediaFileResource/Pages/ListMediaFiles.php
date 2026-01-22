<?php

namespace App\Filament\Resources\MediaFileResource\Pages;

use App\Filament\Resources\MediaFileResource;
use App\Services\Builder\MediaService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListMediaFiles extends ListRecords
{
    protected static string $resource = MediaFileResource::class;

    /**
     * Define header actions.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('upload')
                ->label('Upload Media')
                ->form([
                    FileUpload::make('file')
                        ->label('File')
                        ->required()
                        ->storeFiles(false),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'] ?? null;

                    if (!$file instanceof TemporaryUploadedFile) {
                        return;
                    }

                    app(MediaService::class)->storeUploadedFile($file);
                }),
        ];
    }
}
