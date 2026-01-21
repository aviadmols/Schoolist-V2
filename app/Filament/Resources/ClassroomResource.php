<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassroomResource\Pages;
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
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\HtmlString;

class ClassroomResource extends Resource
{
    protected static ?string $model = Classroom::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Classroom Management')
                    ->tabs([
                        // --- TAB: GENERAL SETTINGS ---
                        Tabs\Tab::make('General')
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
                                Section::make('System')
                                    ->schema([
                                        TextInput::make('join_code')->disabled()->dehydrated(false),
                                        TextInput::make('timezone')->required()->default('Asia/Jerusalem'),
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
                                FileUpload::make('timetable_file_id')->label('Upload Timetable Image')->image()->directory('classrooms/timetables'),
                                CheckboxList::make('active_days')
                                    ->label('Select Active Days')
                                    ->options([
                                        0 => 'Sunday (א)', 1 => 'Monday (ב)', 2 => 'Tuesday (ג)', 
                                        3 => 'Wednesday (ד)', 4 => 'Thursday (ה)', 5 => 'Friday (ו)', 6 => 'Saturday (ש)',
                                    ])
                                    ->columns(7)->live(),

                                ...static::getDayRepeaterSchema(0, 'Sunday (יום א\')', 'sundayEntries'),
                                ...static::getDayRepeaterSchema(1, 'Monday (יום ב\')', 'mondayEntries'),
                                ...static::getDayRepeaterSchema(2, 'Tuesday (יום ג\')', 'tuesdayEntries'),
                                ...static::getDayRepeaterSchema(3, 'Wednesday (יום ד\')', 'wednesdayEntries'),
                                ...static::getDayRepeaterSchema(4, 'Thursday (יום ה\')', 'thursdayEntries'),
                                ...static::getDayRepeaterSchema(5, 'Friday (יום ו\')', 'fridayEntries'),
                                ...static::getDayRepeaterSchema(6, 'Saturday (יום ש\')', 'saturdayEntries'),
                            ]),

                        // --- TAB: CONTACTS ---
                        Tabs\Tab::make('Contacts')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Repeater::make('importantContacts')
                                    ->relationship()
                                    ->schema([
                                        TextInput::make('first_name')->label('First Name')->required(),
                                        TextInput::make('last_name')->label('Last Name')->required(),
                                        TextInput::make('role')->label('Role')->required(),
                                        TextInput::make('phone')->label('Phone')->tel()
                                            ->helperText(fn ($state) => $state && !preg_match('/^05\d{8}$/', $state) ? new HtmlString('<span class="text-warning-600 text-xs">Note: Standard format is 0503222012</span>') : null),
                                        TextInput::make('email')->label('Email')->email(),
                                    ])->columns(2)->addActionLabel('Add New Contact'),
                            ]),

                        // --- TAB: CHILDREN ---
                        Tabs\Tab::make('Children')
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                Repeater::make('children')
                                    ->relationship()
                                    ->schema([
                                        TextInput::make('first_name')->required(),
                                        TextInput::make('last_name')->required(),
                                        DatePicker::make('birthday')->label('Birthday'),
                                        Textarea::make('notes')->label('Medical/Other Notes')->rows(2),
                                    ])->columns(2)->addActionLabel('Add New Child'),
                            ]),

                        // --- TAB: LINKS ---
                        Tabs\Tab::make('Links & Materials')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Repeater::make('links')
                                    ->relationship()
                                    ->schema([
                                        TextInput::make('title')->label('Title')->required(),
                                        TextInput::make('url')->label('URL')->url()->required(),
                                        TextInput::make('category')->label('Category')->placeholder('e.g. Homework'),
                                    ])->columns(3)->addActionLabel('Add New Link'),
                            ]),

                        // --- TAB: HOLIDAYS ---
                        Tabs\Tab::make('Holidays')
                            ->icon('heroicon-o-sun')
                            ->schema([
                                Repeater::make('holidays')
                                    ->relationship()
                                    ->schema([
                                        TextInput::make('name')->label('Holiday Name')->required(),
                                        DatePicker::make('start_date')->label('Start Date')->required(),
                                        DatePicker::make('end_date')->label('End Date')->required(),
                                        Toggle::make('is_no_school')->label('No School Day')->default(true),
                                    ])->columns(4)->addActionLabel('Add New Holiday'),
                            ]),
                    ])->columnSpanFull()
            ])->columns(1);
    }

    protected static function getDayRepeaterSchema(int $dayId, string $label, string $relationship): array
    {
        return [
            Section::make($label)
                ->collapsible()
                ->collapsed()
                // Lenient check for string vs integer keys
                ->hidden(fn (Forms\Get $get) => !in_array((string)$dayId, array_map('strval', $get('active_days') ?? [])))
                ->schema([
                    Repeater::make($relationship)
                        ->relationship()
                        ->schema([
                            TextInput::make('subject')->label('Lesson')->required(),
                            TextInput::make('teacher')->label('Teacher'),
                            TextInput::make('special_message')->label('Note'),
                            Forms\Components\Hidden::make('day_of_week')->default($dayId),
                        ])
                        ->columns(3)
                        ->reorderable('sort_order')
                        ->addActionLabel('Add Lesson')
                        ->defaultItems(1),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassrooms::route('/'),
            'create' => Pages\CreateClassroom::route('/create'),
            'edit' => Pages\EditClassroom::route('/{record}/edit'),
        ];
    }
}
