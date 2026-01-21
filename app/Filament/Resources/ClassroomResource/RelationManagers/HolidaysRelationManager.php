<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HolidaysRelationManager extends RelationManager
{
    protected static string $relationship = 'holidays';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Holiday Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Start Date')
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('End Date')
                    ->required(),
                Forms\Components\Toggle::make('is_no_school')
                    ->label('Is Summer Camp (יש קייטנה)')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('start_date')->label('Start Date')->date(),
                Tables\Columns\TextColumn::make('end_date')->label('End Date')->date(),
                Tables\Columns\IconColumn::make('is_no_school')
                    ->label('Summer Camp')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
