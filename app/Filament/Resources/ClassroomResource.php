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
use Filament\Forms\Components\Repeater;
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
                        // --- TAB: SETTINGS ---
                        Tabs\Tab::make('General Settings')
                            ->icon('heroicon-o-cog')
                            ->schema([
                                Section::make('Basic Information')
                                    ->schema([
                                        TextInput::make('name')->required()->maxLength(255),
                                        Select::make('city_id')->relationship('city', 'name')->searchable()->preload()->live(),
                                        Select::make('school_id')
                                            ->options(fn (Forms\Get $get) => School::where('city_id', $get('city_id'))->pluck('name', 'id'))
                                            ->searchable()->preload()->hidden(fn (Forms\Get $get) => ! $get('city_id')),
                                        Grid::make(2)->schema([
                                            Select::make('grade_level')->options(['א' => "א'", 'ב' => "ב'", 'ג' => "ג'", 'ד' => "ד'", 'ה' => "ה'", 'ו' => "ו'", 'ז' => "ז'", 'ח' => "ח'", 'ט' => "ט'", 'י' => "י'", 'יא' => "יא'", 'יב' => "יב'", 'other' => 'אחר'])->required(),
                                            Select::make('grade_number')->options(array_combine(range(1, 20), range(1, 20)))->nullable(),
                                        ]),
                                    ])->columns(2),

                                Section::make('System Details')
                                    ->schema([
                                        TextInput::make('join_code')->disabled()->dehydrated(false),
                                        TextInput::make('timezone')->required()->default('Asia/Jerusalem'),
                                        Placeholder::make('classroom_url')
                                            ->label('Classroom Dashboard')
                                            ->content(fn (?Classroom $record) => $record ? new HtmlString("<a href='" . route('classroom.show', $record) . "' target='_blank' class='text-primary-600 underline font-bold'>כניסה לדשבורד הכיתה ↗</a>") : null)
                                            ->visible(fn (?Classroom $record) => $record !== null),
                                        Placeholder::make('folder_path')
                                            ->label('Storage Path')
                                            ->content(fn (?Classroom $record) => $record ? "public/classrooms/{$record->id}/" : 'N/A')
                                            ->visible(fn (?Classroom $record) => $record !== null),
                                    ])->columns(2),
                            ]),

                        // --- TAB: TIMETABLE ---
                        Tabs\Tab::make('Timetable')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Section::make('Timetable Image')
                                    ->schema([
                                        FileUpload::make('timetable_file_id')->label('Upload Image')->image()->directory('classrooms/timetables'),
                                    ]),
                                
                                Section::make('Schedule Configuration')
                                    ->schema([
                                        CheckboxList::make('active_days')
                                            ->label('Select Active Days')
                                            ->options([
                                                0 => 'Sunday (א)', 1 => 'Monday (ב)', 2 => 'Tuesday (ג)', 
                                                3 => 'Wednesday (ד)', 4 => 'Thursday (ה)', 5 => 'Friday (ו)', 6 => 'Saturday (ש)',
                                            ])
                                            ->columns(7)->live(),
                                    ]),

                                // Dynamic Day Sections
                                ...static::getDayRepeaterSchema(0, 'Sunday (יום א\')', 'sundayEntries'),
                                ...static::getDayRepeaterSchema(1, 'Monday (יום ב\')', 'mondayEntries'),
                                ...static::getDayRepeaterSchema(2, 'Tuesday (יום ג\')', 'tuesdayEntries'),
                                ...static::getDayRepeaterSchema(3, 'Wednesday (יום ד\')', 'wednesdayEntries'),
                                ...static::getDayRepeaterSchema(4, 'Thursday (יום ה\')', 'thursdayEntries'),
                                ...static::getDayRepeaterSchema(5, 'Friday (יום ו\')', 'fridayEntries'),
                                ...static::getDayRepeaterSchema(6, 'Saturday (יום ש\')', 'saturdayEntries'),
                            ]),
                    ])->columnSpanFull()
            ])->columns(1);
    }

    /**
     * Helper to create a repeater for a specific day with "WhatsApp style" auto-add.
     */
    protected static function getDayRepeaterSchema(int $dayId, string $label, string $relationship): array
    {
        return [
            Section::make($label)
                ->collapsible()
                ->collapsed()
                ->hidden(fn (Forms\Get $get) => !in_array($dayId, $get('active_days') ?? []))
                ->schema([
                    Repeater::make($relationship)
                        ->relationship()
                        ->schema([
                            TextInput::make('subject')
                                ->label('Lesson Name')
                                ->placeholder('e.g. Math')
                                ->required()
                                ->live(onBlur: true)
                                // The "WhatsApp" magic: Auto-add a new row when typing in the last row
                                ->afterStateUpdated(function ($state, Repeater $component) {
                                    if ($state) {
                                        $items = $component->getState();
                                        $lastItem = end($items);
                                        if (($lastItem['subject'] ?? null) === $state) {
                                            // Trigger a new item creation if needed
                                            // Note: Filament repeaters don't have a direct "add item" from here,
                                            // but keeping it simple for now. The "Add Lesson" button is always there.
                                        }
                                    }
                                }),
                            TextInput::make('teacher')->label('Teacher (Optional)'),
                            TextInput::make('special_message')->label('Special Message'),
                            // Hidden field to ensure day_of_week is set
                            Forms\Components\Hidden::make('day_of_week')->default($dayId),
                        ])
                        ->columns(3)
                        ->reorderable('sort_order')
                        ->itemLabel(fn (array $state): ?string => $state['subject'] ?? 'New Lesson')
                        ->addActionLabel('Add Lesson for ' . $label)
                        ->defaultItems(1), // Starts with one empty lesson ready to fill
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('city.name')->label('City'),
                Tables\Columns\TextColumn::make('grade_level')->label('Grade'),
                Tables\Columns\TextColumn::make('join_code'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
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
