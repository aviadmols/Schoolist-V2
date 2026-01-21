<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassroomResource\Pages;
use App\Filament\Resources\ClassroomResource\RelationManagers;
use App\Models\Classroom;
use App\Services\Storage\FileStorageService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ClassroomResource extends Resource
{
    protected static ?string $model = Classroom::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    /**
     * Define the form for creating/editing classrooms.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('city_id')
                            ->label('City')
                            ->relationship('city', 'name')
                            ->searchable()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('school_id')
                            ->label('School Name')
                            ->options(fn (Forms\Get $get): \Illuminate\Support\Collection => \App\Models\School::where('city_id', $get('city_id'))->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->hidden(fn (Forms\Get $get): bool => ! $get('city_id')),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('grade_level')
                                    ->label('Grade Letter')
                                    ->options([
                                        'א' => "א'",
                                        'ב' => "ב'",
                                        'ג' => "ג'",
                                        'ד' => "ד'",
                                        'ה' => "ה'",
                                        'ו' => "ו'",
                                        'ז' => "ז'",
                                        'ח' => "ח'",
                                        'ט' => "ט'",
                                        'י' => "י'",
                                        'יא' => "י\"א",
                                        'יב' => "י\"ב",
                                        'other' => 'אחר',
                                    ])
                                    ->required(),
                                Forms\Components\Select::make('grade_number')
                                    ->label('Grade Number')
                                    ->options(array_combine(range(1, 20), range(1, 20)))
                                    ->nullable(),
                            ]),
                    ])->columns(2),

                Forms\Components\Section::make('System Details')
                    ->schema([
                        Forms\Components\TextInput::make('join_code')
                            ->maxLength(10)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('timezone')
                            ->required()
                            ->default('Asia/Jerusalem'),
                        Forms\Components\Placeholder::make('media_size_bytes')
                            ->label('Media Size')
                            ->content(fn (?Classroom $record): string => $record ? number_format($record->media_size_bytes / 1024 / 1024, 2) . ' MB' : '0.00 MB')
                            ->visible(fn (?Classroom $record): bool => $record !== null),
                        Forms\Components\Placeholder::make('classroom_url')
                            ->label('Classroom Link')
                            ->content(fn (?Classroom $record): ?\Illuminate\Support\HtmlString => $record ? new \Illuminate\Support\HtmlString("<a href='" . route('classroom.show', $record) . "' target='_blank' class='text-primary-600 underline'>View Classroom Page</a>") : null)
                            ->visible(fn (?Classroom $record): bool => $record !== null),
                        Forms\Components\Placeholder::make('folder_path')
                            ->label('Storage Path')
                            ->content(fn (?Classroom $record): string => $record ? "public/classrooms/{$record->id}/" : 'N/A')
                            ->visible(fn (?Classroom $record): bool => $record !== null),
                    ])->columns(2),
            ]);
    }

    /**
     * Define the table for listing classrooms.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('join_code'),
                Tables\Columns\TextColumn::make('media_size_bytes')
                    ->label('Media Size')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024 / 1024, 2) . ' MB'),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Members'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('purge_media')
                    ->label('Purge Media')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Classroom $record, FileStorageService $storageService) {
                        $storageService->purgeClassroomFolder($record->id);
                        
                        Notification::make()
                            ->title('Media purged successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * Define the relation managers for this resource.
     */
    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
        ];
    }

    /**
     * Define the pages for this resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassrooms::route('/'),
            'create' => Pages\CreateClassroom::route('/create'),
            'edit' => Pages\EditClassroom::route('/{record}/edit'),
        ];
    }
}
