<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassroomResource\Pages;
use App\Filament\Resources\ClassroomResource\RelationManagers;
use App\Models\Classroom;
use App\Models\School;
use App\Services\Storage\FileStorageService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Repeater;
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

    /**
     * Define the form for creating/editing classrooms.
     */
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
                                            ->label('Classroom Link')
                                            ->content(fn (?Classroom $record): ?HtmlString => $record ? new HtmlString("<a href='" . route('classroom.show', $record) . "' target='_blank' class='text-primary-600 underline'>View Classroom Page</a>") : null)
                                            ->visible(fn (?Classroom $record): bool => $record !== null),
                                        Placeholder::make('media_size_bytes')
                                            ->label('Media Size')
                                            ->content(fn (?Classroom $record): string => $record ? number_format($record->media_size_bytes / 1024 / 1024, 2) . ' MB' : '0.00 MB')
                                            ->visible(fn (?Classroom $record): bool => $record !== null),
                                    ])->columns(2),
                            ]),

                        // --- TAB: TIMETABLE ---
                        Tabs\Tab::make('Timetable')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Section::make('Timetable Reference')
                                    ->schema([
                                        FileUpload::make('timetable_file_id')
                                            ->label('Upload Timetable Image')
                                            ->image()
                                            ->directory('classrooms/timetables'),
                                    ]),
                                
                                Section::make('Weekly Schedule')
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

                                        Repeater::make('timetableEntries')
                                            ->relationship()
                                            ->schema([
                                                Select::make('day_of_week')
                                                    ->options([
                                                        0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
                                                        4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'
                                                    ])
                                                    ->required(),
                                                TextInput::make('subject')
                                                    ->label('Lesson Name')
                                                    ->required()
                                                    ->live(onBlur: true),
                                                TextInput::make('teacher')
                                                    ->label('Teacher (Optional)'),
                                                TextInput::make('special_message')
                                                    ->label('Special Message'),
                                            ])
                                            ->columns(4)
                                            ->reorderable('sort_order')
                                            ->itemLabel(fn (array $state): ?string => ($state['subject'] ?? 'New Lesson') . ($state['teacher'] ? " ({$state['teacher']})" : ""))
                                            ->collapsible()
                                            ->collapsed()
                                            ->addActionLabel('Add Lesson'),
                                    ]),
                            ]),

                        // --- TAB: CONTACTS ---
                        Tabs\Tab::make('Contacts')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Repeater::make('importantContacts')
                                    ->relationship()
                                    ->schema([
                                        TextInput::make('first_name')
                                            ->label('First Name')
                                            ->required(),
                                        TextInput::make('last_name')
                                            ->label('Last Name')
                                            ->required(),
                                        TextInput::make('role')
                                            ->label('Role')
                                            ->required(),
                                        TextInput::make('phone')
                                            ->label('Phone')
                                            ->tel()
                                            ->live(onBlur: true)
                                            ->helperText(fn ($state) => $state && !preg_match('/^05\d{8}$/', $state) ? new HtmlString('<span class="text-warning-600 text-xs">Note: This does not look like a standard Israeli mobile number (e.g. 0503222012)</span>') : null),
                                        TextInput::make('email')
                                            ->label('Email')
                                            ->email(),
                                    ])
                                    ->columns(2)
                                    ->itemLabel(fn (array $state): ?string => ($state['first_name'] ?? '') . ' ' . ($state['last_name'] ?? '') . ($state['role'] ? " - {$state['role']}" : ""))
                                    ->addActionLabel('Add Contact'),
                            ]),

                        // --- TAB: ADMINS ---
                        Tabs\Tab::make('Classroom Admins')
                            ->icon('heroicon-o-users')
                            ->schema([
                                Placeholder::make('manage_users_hint')
                                    ->content('Manage users and their roles in the "Users" section below.'),
                            ]),
                    ])
                    ->columnSpanFull()
            ])->columns(1);
    }

    /**
     * Define the table for listing classrooms.
     */
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

    /**
     * Define the relation managers for this resource.
     */
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
