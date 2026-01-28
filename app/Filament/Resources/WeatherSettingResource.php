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
                ->maxLength(255),
            Forms\Components\TextInput::make('city_name')
                ->label('City Name')
                ->maxLength(255)
                ->helperText('City name for weather lookup (e.g., "Tel Aviv" or "Jerusalem")'),
            Forms\Components\KeyValue::make('icon_mapping')
                ->label('Icon Mapping')
                ->keyLabel('Condition')
                ->valueLabel('Icon')
                ->helperText('Map weather conditions to emoji icons (e.g., hot: ☀️, rain: 🌧️)'),
        ]);
    }

    /**
     * Define the table for weather settings.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('classroom.name')->label('Classroom'),
                Tables\Columns\TextColumn::make('api_provider')->label('Provider'),
                Tables\Columns\TextColumn::make('city_name')->label('City'),
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
            'index' => Pages\ListWeatherSettings::route('/'),
            'create' => Pages\CreateWeatherSetting::route('/create'),
            'edit' => Pages\EditWeatherSetting::route('/{record}/edit'),
        ];
    }
}
