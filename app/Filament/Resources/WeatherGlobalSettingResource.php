<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WeatherGlobalSettingResource\Pages;
use App\Models\WeatherGlobalSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WeatherGlobalSettingResource extends Resource
{
    protected static ?string $model = WeatherGlobalSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Weather API Settings';

    protected static ?string $modelLabel = 'Weather API Setting';

    protected static ?int $navigationSort = 1;

    /**
     * Define the form for global weather settings.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('API Configuration')
                ->description('Configure the global API key for weather data. This key will be used for all classrooms.')
                ->schema([
                    Forms\Components\Select::make('api_provider')
                        ->label('API Provider')
                        ->options([
                            'openweathermap' => 'OpenWeatherMap',
                        ])
                        ->default('openweathermap')
                        ->required()
                        ->disabled(),
                    Forms\Components\TextInput::make('api_key')
                        ->label('API Key')
                        ->password()
                        ->maxLength(255)
                        ->required()
                        ->helperText('Get your API key from https://openweathermap.org/api. This key will be used for all classrooms.'),
                ])
                ->columns(1),
        ]);
    }

    /**
     * Define the table for global weather settings.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('api_provider')
                    ->label('API Provider')
                    ->badge(),
                Tables\Columns\IconColumn::make('api_key')
                    ->label('API Configured')
                    ->boolean()
                    ->getStateUsing(fn ($record) => !empty($record->api_key))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    /**
     * Define the pages for this resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageWeatherGlobalSettings::route('/'),
            'edit' => Pages\EditWeatherGlobalSetting::route('/{record}/edit'),
        ];
    }
}
