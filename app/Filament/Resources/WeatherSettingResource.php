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
            Forms\Components\Section::make('Classroom Selection')
                ->schema([
                    Forms\Components\Select::make('classroom_id')
                        ->label('Classroom')
                        ->relationship('classroom', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->helperText('The city will be automatically pulled from the classroom\'s city association.'),
                ]),
            
            Forms\Components\Section::make('Weather Display Settings')
                ->description('Configure how weather information is displayed on the classroom page')
                ->schema([
                    Forms\Components\Repeater::make('temperature_ranges')
                        ->label('Temperature Ranges')
                        ->schema([
                            Forms\Components\TextInput::make('range')
                                ->label('Temperature Range')
                                ->required()
                                ->placeholder('e.g., 25-30 or 25+ or -10')
                                ->helperText('Format: "min-max" (e.g., 20-25), "min+" (e.g., 25+), or "-max" (e.g., -10)'),
                            Forms\Components\TextInput::make('condition_key')
                                ->label('Condition Key')
                                ->required()
                                ->placeholder('e.g., hot, warm, mild')
                                ->helperText('This key will be used to map to the icon below'),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('Add Temperature Range')
                        ->reorderable(true)
                        ->columnSpanFull(),
                    
                    Forms\Components\Repeater::make('icon_mapping')
                        ->label('Weather Icons')
                        ->schema([
                            Forms\Components\TextInput::make('condition')
                                ->label('Condition Key')
                                ->required()
                                ->placeholder('e.g., hot, warm, mild, cool, cold, rain, default')
                                ->helperText('Must match the condition keys from temperature ranges'),
                            Forms\Components\FileUpload::make('icon')
                                ->label('SVG Icon')
                                ->disk('public')
                                ->directory('weather-icons')
                                ->acceptedFileTypes(['image/svg+xml'])
                                ->maxSize(512) // 512 KB
                                ->helperText('Upload an SVG file for this weather condition')
                                ->required(),
                        ])
                        ->defaultItems(7)
                        ->default([
                            ['condition' => 'hot', 'icon' => null],
                            ['condition' => 'warm', 'icon' => null],
                            ['condition' => 'mild', 'icon' => null],
                            ['condition' => 'cool', 'icon' => null],
                            ['condition' => 'cold', 'icon' => null],
                            ['condition' => 'rain', 'icon' => null],
                            ['condition' => 'default', 'icon' => null],
                        ])
                        ->addActionLabel('Add Icon')
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
                Tables\Columns\TextColumn::make('classroom.city.name')
                    ->label('City')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('icon_mapping')
                    ->label('Icons Configured')
                    ->getStateUsing(fn ($record) => count($record->icon_mapping ?? []))
                    ->suffix(' icons'),
                Tables\Columns\TextColumn::make('temperature_ranges')
                    ->label('Temperature Ranges')
                    ->getStateUsing(fn ($record) => count($record->temperature_ranges ?? []))
                    ->suffix(' ranges'),
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
