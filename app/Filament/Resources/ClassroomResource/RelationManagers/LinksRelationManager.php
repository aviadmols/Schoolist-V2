<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use App\Filament\Resources\ClassroomResource\RelationManagers\Concerns\FormatsCreatorLabel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LinksRelationManager extends RelationManager
{
    use FormatsCreatorLabel;

    protected static string $relationship = 'links';

    /**
     * Build the link form.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('category')
                    ->label('Category')
                    ->options([
                        'group_whatsapp' => 'Group WhatsApp',
                        'important_links' => 'Important links',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('url')
                    ->label('URL')
                    ->url()
                    ->nullable(),
                Forms\Components\FileUpload::make('file_path')
                    ->label('File')
                    ->disk('public')
                    ->directory(fn (): string => $this->ownerRecord ? "classrooms/{$this->ownerRecord->id}/links" : 'temp')
                    ->visibility('public'),
            ]);
    }

    /**
     * Build the links table.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Title'),
                Tables\Columns\TextColumn::make('category')->label('Category'),
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
