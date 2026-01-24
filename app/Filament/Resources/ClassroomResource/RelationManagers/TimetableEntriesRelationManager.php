<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use App\Filament\Resources\ClassroomResource\RelationManagers\Concerns\FormatsCreatorLabel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TimetableEntriesRelationManager extends RelationManager
{
    use FormatsCreatorLabel;

    protected static string $relationship = 'timetableEntries';

    /**
     * Build the form for timetable entries.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('day_of_week')
                    ->label('Day')
                    ->options([
                        0 => 'Sunday',
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('subject')
                    ->label('Subject')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('teacher')
                    ->label('Teacher')
                    ->maxLength(255),
                Forms\Components\TextInput::make('room')
                    ->label('Room')
                    ->maxLength(255),
                Forms\Components\TimePicker::make('start_time')
                    ->label('Start Time'),
                Forms\Components\TimePicker::make('end_time')
                    ->label('End Time'),
                Forms\Components\TextInput::make('special_message')
                    ->label('Note')
                    ->maxLength(255),
            ]);
    }

    /**
     * Build the table for timetable entries.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->columns([
                Tables\Columns\TextColumn::make('day_of_week')
                    ->label('Day')
                    ->formatStateUsing(fn (int $state): string => [
                        0 => 'Sunday',
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday',
                    ][$state] ?? (string) $state),
                Tables\Columns\TextColumn::make('subject')->label('Subject')->searchable(),
                Tables\Columns\TextColumn::make('start_time')->label('Start'),
                Tables\Columns\TextColumn::make('end_time')->label('End'),
                Tables\Columns\TextColumn::make('teacher')->label('Teacher'),
                Tables\Columns\TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_by_label')
                    ->label('Updated By')
                    ->getStateUsing(fn ($record): string => $this->formatCreatorLabel($record))
                    ->toggleable(isToggledHiddenByDefault: true),
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
