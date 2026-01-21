<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmsSettingResource\Pages;
use App\Models\SmsSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SmsSettingResource extends Resource
{
    protected static ?string $model = SmsSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'System';

    /**
     * Define the form for SMS settings.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('provider')
                ->label('Provider')
                ->default('sms019')
                ->disabled()
                ->dehydrated(),
            Forms\Components\TextInput::make('username')
                ->label('Username')
                ->maxLength(255),
            Forms\Components\TextInput::make('password')
                ->label('Password')
                ->password()
                ->maxLength(255),
            Forms\Components\TextInput::make('sender')
                ->label('Sender')
                ->maxLength(255),
        ]);
    }

    /**
     * Define the table for SMS settings.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider')->label('Provider'),
                Tables\Columns\TextColumn::make('username')->label('Username'),
                Tables\Columns\TextColumn::make('sender')->label('Sender'),
                Tables\Columns\TextColumn::make('updated_at')->label('Updated')->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * Define the pages for this resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmsSettings::route('/'),
            'create' => Pages\CreateSmsSetting::route('/create'),
            'edit' => Pages\EditSmsSetting::route('/{record}/edit'),
        ];
    }
}
