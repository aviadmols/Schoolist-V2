<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HolidayTemplateResource\Pages;
use App\Models\HolidayTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HolidayTemplateResource extends Resource
{
    protected static ?string $model = HolidayTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    /**
     * Define the form for global holiday templates.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('start_date')
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->required()
                    ->afterOrEqual('start_date'),
                Forms\Components\TextInput::make('description')
                    ->maxLength(255),
            ]);
    }

    /**
     * Define the table for listing templates.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('start_date')->date(),
                Tables\Columns\TextColumn::make('end_date')->date(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListHolidayTemplates::route('/'),
            'create' => Pages\CreateHolidayTemplate::route('/create'),
            'edit' => Pages\EditHolidayTemplate::route('/{record}/edit'),
        ];
    }
}
