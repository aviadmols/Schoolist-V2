<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use App\Filament\Resources\ClassroomResource\RelationManagers\Concerns\FormatsCreatorLabel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChildrenRelationManager extends RelationManager
{
    use FormatsCreatorLabel;

    protected static string $relationship = 'children';

    /**
     * Build the child form.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('birth_date')
                    ->label('Birth Date'),
            ]);
    }

    /**
     * Build the children table.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Name'),
                Tables\Columns\TextColumn::make('birth_date')->label('Birth Date')->date(),
                Tables\Columns\TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_by_label')
                    ->label('Updated By')
                    ->getStateUsing(fn ($record): string => $this->formatCreatorLabel($record))
                    ->toggleable(isToggledHiddenByDefault: true),
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
