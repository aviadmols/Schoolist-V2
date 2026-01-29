<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WeatherSettingResource\Pages;
use App\Models\WeatherSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WeatherSettingResource extends Resource
{
    protected static ?string $model = WeatherSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud';

    protected static ?string $navigationGroup = 'System';

    /**
     * Define the form for weather settings.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('API Configuration')
                ->schema([
                    Forms\Components\Select::make('classroom_id')
                        ->label('Classroom')
                        ->relationship('classroom', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('api_provider')
                        ->label('API Provider')
                        ->options([
                            'openweathermap' => 'OpenWeatherMap',
                        ])
                        ->default('openweathermap')
                        ->required(),
                    Forms\Components\TextInput::make('api_key')
                        ->label('API Key')
                        ->password()
                        ->maxLength(255)
                        ->helperText('Get your API key from https://openweathermap.org/api'),
                    Forms\Components\TextInput::make('city_name')
                        ->label('City Name')
                        ->maxLength(255)
                        ->helperText('City name for weather lookup (e.g., "Tel Aviv" or "Jerusalem")')
                        ->required(),
                ])
                ->columns(2),
            
            Forms\Components\Section::make('Weather Display Settings')
                ->description('Configure how weather information is displayed on the classroom page')
                ->schema([
                    Forms\Components\KeyValue::make('icon_mapping')
                        ->label('Weather Icons')
                        ->keyLabel('Condition')
                        ->valueLabel('Emoji Icon')
                        ->helperText('Map weather conditions to emoji icons that will be displayed on the classroom page.')
                        ->default([
                            'hot' => '☀️',
                            'warm' => '☀️',
                            'mild' => '⛅',
                            'cool' => '☁️',
                            'cold' => '❄️',
                            'rain' => '🌧️',
                            'default' => '☀️',
                        ])
                        ->addable(true)
                        ->deletable(true)
                        ->reorderable(true)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Define the table for weather settings.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('classroom.name')
                    ->label('Classroom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city_name')
                    ->label('City')
                    ->searchable()
                    ->sortable(),
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
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    /**
     * Define the pages for this resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWeatherSettings::route('/'),
            'create' => Pages\CreateWeatherSetting::route('/create'),
            'edit' => Pages\EditWeatherSetting::route('/{record}/edit'),
        ];
    }
}
