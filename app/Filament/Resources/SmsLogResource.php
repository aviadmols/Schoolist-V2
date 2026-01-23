<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmsLogResource\Pages;
use App\Models\SmsLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SmsLogResource extends Resource
{
    protected static ?string $model = SmsLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'System';

    /**
     * Define the table for SMS logs.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider')->label('Provider'),
                Tables\Columns\TextColumn::make('phone_mask')->label('Phone'),
                Tables\Columns\TextColumn::make('status')->label('Status'),
                Tables\Columns\TextColumn::make('user.name')->label('User'),
                Tables\Columns\TextColumn::make('classroom_id')->label('Classroom'),
                Tables\Columns\TextColumn::make('request_id')->label('Request ID'),
                Tables\Columns\TextColumn::make('error_message')->label('Error'),
                Tables\Columns\TextColumn::make('provider_request')
                    ->label('Provider Request')
                    ->limit(80)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('provider_response')
                    ->label('Provider Response')
                    ->limit(80)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime(),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Disable creation from the UI.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Limit logs to the last 24 hours.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    /**
     * Define the pages for this resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmsLogs::route('/'),
        ];
    }
}
