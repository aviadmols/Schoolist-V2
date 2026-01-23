<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaFileResource\Pages;
use App\Models\MediaFile;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class MediaFileResource extends Resource
{
    private const BYTES_IN_KILOBYTE = 1024;
    private const BYTES_IN_MEGABYTE = 1048576;

    protected static ?string $model = MediaFile::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Builder';

    /**
     * Define the media table.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('original_name')
                    ->label('Filename')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mime_type')
                    ->label('Type'),
                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(function ($state): string {
                        $size = (int) $state;

                        if ($size >= self::BYTES_IN_MEGABYTE) {
                            return number_format($size / self::BYTES_IN_MEGABYTE, 2).' MB';
                        }

                        if ($size >= self::BYTES_IN_KILOBYTE) {
                            return number_format($size / self::BYTES_IN_KILOBYTE, 2).' KB';
                        }

                        return $size.' B';
                    }),
                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->copyable()
                    ->url(function (MediaFile $record): string {
                        return $record->url;
                    }, shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Uploaded By')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded At')
                    ->dateTime(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function (MediaFile $record): void {
                        app(\App\Services\Builder\MediaService::class)->deleteMediaFile($record);
                    }),
            ]);
    }

    /**
     * Define resource pages.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaFiles::route('/'),
        ];
    }

    /**
     * Disable default create action.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Determine if the user can view media files.
     */
    public static function canViewAny(): bool
    {
        return Gate::allows('manage_media');
    }

    /**
     * Determine if the user can delete media files.
     */
    public static function canDelete($record): bool
    {
        return Gate::allows('manage_media');
    }
}
