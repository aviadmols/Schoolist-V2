<?php

namespace App\Filament\Resources\MediaFileResource\Pages;

use App\Filament\Resources\MediaFileResource;
use App\Services\Builder\MediaService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
                    Select::make('folder')
                        ->label('Folder')
                        ->options([
                            'assets' => 'Assets',
                            'fonts' => 'Fonts',
                        ])
                        ->default('assets')
                        ->required(),
                    FileUpload::make('files')
                        ->label('Files')
                        ->required()
                        ->multiple()
                        ->storeFiles(false),
                ])
                ->action(function (array $data): void {
                    $files = $data['files'] ?? [];
                    $directory = is_string($data['folder'] ?? null) ? $data['folder'] : 'assets';

                    if (!is_array($files)) {
                        return;
                    }

                    foreach ($files as $file) {
                        if (!$file instanceof TemporaryUploadedFile) {
                            continue;
                        }

                        app(MediaService::class)->storeUploadedFile($file, $directory);
                    }
                }),
        ];
    }
}
