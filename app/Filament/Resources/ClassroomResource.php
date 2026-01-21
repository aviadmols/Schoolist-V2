<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassroomResource\Pages;
use App\Filament\Resources\ClassroomResource\RelationManagers;
use App\Models\Classroom;
use App\Models\School;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

class ClassroomResource extends Resource
{
    protected static ?string $model = Classroom::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Classroom Details')
                    ->tabs([
                        // --- TAB: BASIC SETTINGS ---
                        Tabs\Tab::make('General Settings')
                            ->icon('heroicon-o-cog')
                            ->schema([
                                Section::make('Basic Information')
                                    ->schema([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Select::make('city_id')
                                            ->label('City')
                                            ->relationship('city', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->live(),
                                        Select::make('school_id')
                                            ->label('School Name')
                                            ->options(fn (Forms\Get $get): \Illuminate\Support\Collection => School::where('city_id', $get('city_id'))->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->hidden(fn (Forms\Get $get): bool => ! $get('city_id')),
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('grade_level')
                                                    ->label('Grade Letter')
                                                    ->options([
                                                        'א' => "א'", 'ב' => "ב'", 'ג' => "ג'", 'ד' => "ד'", 'ה' => "ה'", 'ו' => "ו'",
                                                        'ז' => "ז'", 'ח' => "ח'", 'ט' => "ט'", 'י' => "י'", 'יא' => "יא'", 'יב' => "יב'",
                                                        'other' => 'אחר',
                                                    ])
                                                    ->required(),
                                                Select::make('grade_number')
                                                    ->label('Grade Number')
                                                    ->options(array_combine(range(1, 20), range(1, 20)))
                                                    ->nullable(),
                                            ]),
                                    ])->columns(2),

                                Section::make('System Details')
                                    ->schema([
                                        TextInput::make('join_code')
                                            ->maxLength(10)
                                            ->disabled()
                                            ->dehydrated(false),
                                        TextInput::make('timezone')
                                            ->required()
                                            ->default('Asia/Jerusalem'),
                                        Placeholder::make('classroom_url')
                                            ->label('Classroom Dashboard')
                                            ->content(fn (?Classroom $record): ?HtmlString => $record ? new HtmlString("<a href='" . route('classroom.show', $record) . "' target='_blank' class='text-primary-600 underline font-bold'>כניסה לדשבורד הכיתה ↗</a>") : null)
                                            ->visible(fn (?Classroom $record): bool => $record !== null),
                                        Placeholder::make('media_size_bytes')
                                            ->label('Media Size')
                                            ->content(fn (?Classroom $record): string => $record ? number_format($record->media_size_bytes / 1024 / 1024, 2) . ' MB' : '0.00 MB')
                                            ->visible(fn (?Classroom $record): bool => $record !== null),
                                        Placeholder::make('folder_path')
                                            ->label('Storage Path')
                                            ->content(fn (?Classroom $record): string => $record ? "public/classrooms/{$record->id}/" : 'N/A')
                                            ->visible(fn (?Classroom $record): bool => $record !== null),
                                    ])->columns(2),
                            ]),

                        // --- TAB: TIMETABLE ---
                        Tabs\Tab::make('Timetable')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Section::make('Timetable Image')
                                    ->schema([
                                        FileUpload::make('timetable_file_id')
                                            ->label('Upload Image')
                                            ->image()
                                            ->directory('classrooms/timetables'),
                                    ]),
                                
                                Section::make('Configuration')
                                    ->schema([
                                        CheckboxList::make('active_days')
                                            ->label('Select Active Days')
                                            ->options([
                                                0 => 'Sunday (א)',
                                                1 => 'Monday (ב)',
                                                2 => 'Tuesday (ג)',
                                                3 => 'Wednesday (ד)',
                                                4 => 'Thursday (ה)',
                                                5 => 'Friday (ו)',
                                                6 => 'Saturday (ש)',
                                            ])
                                            ->columns(7)
                                            ->live(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull()
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('city.name')->label('City'),
                Tables\Columns\TextColumn::make('grade_level')->label('Grade'),
                Tables\Columns\TextColumn::make('join_code'),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Members'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
            RelationManagers\ImportantContactsRelationManager::class,
            RelationManagers\ChildrenRelationManager::class,
            RelationManagers\LinksRelationManager::class,
            RelationManagers\HolidaysRelationManager::class,
            RelationManagers\AnnouncementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassrooms::route('/'),
            'create' => Pages\CreateClassroom::route('/create'),
            'edit' => Pages\EditClassroom::route('/{record}/edit'),
        ];
    }
}
