<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassroomResource\Pages;
use App\Filament\Resources\ClassroomResource\RelationManagers;
use App\Models\AiSetting;
use App\Models\Classroom;
use App\Models\School;
use App\Services\Classroom\TimetableOcrService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
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
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TimePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;
use RuntimeException;

class ClassroomResource extends Resource
{
    protected static ?string $model = Classroom::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    /** @var string */
    private const AI_PROVIDER = 'openrouter';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Classroom Management')
                    ->tabs([
                        // --- TAB: GENERAL ---
                        Tabs\Tab::make('General Settings')
                            ->icon('heroicon-o-cog')
                            ->schema([
                                Section::make('Basic Information')
                                    ->schema([
                                        TextInput::make('name')->required()->maxLength(255),
                                        Select::make('city_id')->relationship('city', 'name')->searchable()->preload(),
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
                                        Placeholder::make('classroom_page_url')
                                            ->label('Classroom Page')
                                            ->content(function (?Classroom $record): HtmlString {
                                                if (!$record) {
                                                    return new HtmlString('N/A');
                                                }

                                                $url = route('classroom.show', $record);

                                                return new HtmlString('<a href="'.$url.'" target="_blank" rel="noopener">'.$url.'</a>');
                                            })
                                            ->visible(fn (?Classroom $record) => $record !== null),
                                        Placeholder::make('media_size_bytes')
                                            ->label('Media Size')
                                            ->content(fn (?Classroom $record): string => $record ? number_format($record->media_size_bytes / 1024 / 1024, 2) . ' MB' : '0.00 MB')
                                            ->visible(fn (?Classroom $record) => $record !== null),
                                    ])->columns(2),
                            ]),

                        // --- TAB: TIMETABLE ---
                        Tabs\Tab::make('Timetable')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Section::make('Configuration')
                                    ->schema([
                                        FileUpload::make('timetable_image_path') // Fixed: Using string column
                                            ->label('Upload Timetable Image')
                                            ->image()
                                            ->disk('public')
                                            ->directory(fn (?Classroom $record) => $record ? "classrooms/{$record->id}/timetable" : "temp")
                                            ->visibility('public'),
                                        Actions::make([
                                            Action::make('extract_timetable')
                                                ->label('Generate Timetable from Image')
                                                ->requiresConfirmation()
                                                ->action(function (?Classroom $record, TimetableOcrService $service): void {
                                                    static::runTimetableExtraction($record, $service);
                                                })
                                                ->disabled(fn (?Classroom $record): bool => !$record || !$record->timetable_image_path),
                                        ]),
                                        CheckboxList::make('active_days')
                                            ->label('Select Active Days')
                                            ->options([
                                                0 => 'Sunday (א)', 1 => 'Monday (ב)', 2 => 'Tuesday (ג)', 
                                                3 => 'Wednesday (ד)', 4 => 'Thursday (ה)', 5 => 'Friday (ו)', 6 => 'Saturday (ש)',
                                            ])
                                            ->columns(7)
                                            ->live(),
                                    ]),

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
                                        static::createdByPlaceholder(),
                                        TextInput::make('first_name')->label('First Name')->required(),
                                        TextInput::make('last_name')->label('Last Name')->required(),
                                        TextInput::make('role')->label('Role')->required(),
                                        TextInput::make('phone')->label('Phone')->tel()
                                            ->helperText(fn ($state) => $state && !preg_match('/^05\d{8}$/', $state) ? new HtmlString('<span class="text-warning-600 text-xs">Note: Standard format is 050-0000000</span>') : null),
                                        TextInput::make('email')->label('Email')->email(),
                                    ])->columns(2)->addActionLabel('Add New Contact')
                                    ->defaultItems(0), // Fixed: Start empty
                            ]),

                        // --- TAB: CHILDREN ---
                        Tabs\Tab::make('Children')
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                Repeater::make('children')
                                    ->relationship()
                                    ->schema([
                                        static::createdByPlaceholder(),
                                        TextInput::make('name')
                                            ->label('Child Name')
                                            ->required(),
                                        DatePicker::make('birth_date')
                                            ->label('Birth Date'),
                                        Repeater::make('contacts')
                                            ->relationship()
                                            ->schema([
                                                static::createdByPlaceholder(),
                                                TextInput::make('name')
                                                    ->label('Contact Name')
                                                    ->required(),
                                                Select::make('relation')
                                                    ->label('Relation')
                                                    ->options([
                                                        'father' => 'Father',
                                                        'mother' => 'Mother',
                                                        'other' => 'Other',
                                                    ])
                                                    ->required(),
                                                TextInput::make('phone')
                                                    ->label('Phone')
                                                    ->tel()
                                                    ->helperText(fn ($state) => $state && !preg_match('/^05\d{8}$/', $state) ? new HtmlString('<span class="text-warning-600 text-xs">Note: Standard format is 050-0000000</span>') : null),
                                            ])
                                            ->columns(3)
                                            ->addActionLabel('Add Contact')
                                            ->defaultItems(0),
                                    ])->columns(1)->addActionLabel('Add New Child')
                                    ->defaultItems(0), // Fixed: Start empty
                            ]),

                        // --- TAB: LINKS ---
                        Tabs\Tab::make('Links & Materials')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Repeater::make('links')
                                    ->relationship()
                                    ->schema([
                                        static::createdByPlaceholder(),
                                        TextInput::make('title')->label('Title')->required(),
                                        TextInput::make('url')
                                            ->label('URL')
                                            ->url()
                                            ->requiredWithout('file_path')
                                            ->nullable(),
                                        FileUpload::make('file_path')
                                            ->label('File')
                                            ->disk('public')
                                            ->directory(function (Forms\Get $get): string {
                                                $classroomId = $get('../../id');
                                                return $classroomId ? "classrooms/{$classroomId}/links" : 'temp';
                                            })
                                            ->visibility('public')
                                            ->requiredWithout('url'),
                                        Select::make('category')
                                            ->label('Category')
                                            ->options([
                                                'group_whatsapp' => 'Group WhatsApp',
                                                'important_links' => 'Important links',
                                            ])
                                            ->required(),
                                    ])->columns(3)->addActionLabel('Add New Link')
                                    ->defaultItems(0), // Fixed: Start empty
                            ]),

                        // --- TAB: HOLIDAYS ---
                        Tabs\Tab::make('Holidays')
                            ->icon('heroicon-o-sun')
                            ->schema([
                                Repeater::make('holidays')
                                    ->relationship()
                                    ->schema([
                                        static::createdByPlaceholder(),
                                        TextInput::make('name')->label('Holiday Name')->required(),
                                        DatePicker::make('start_date')->label('Start Date')->required(),
                                        DatePicker::make('end_date')->label('End Date')->required(),
                                        Toggle::make('is_no_school')->label('Is Summer Camp (יש קייטנה)')->default(false),
                                    ])->columns(4)->addActionLabel('Add New Holiday')
                                    ->defaultItems(0), // Fixed: Start empty
                            ]),

                        // --- TAB: ANNOUNCEMENTS ---
                        Tabs\Tab::make('Announcements')
                            ->icon('heroicon-o-bell')
                            ->schema([
                                Repeater::make('announcements')
                                    ->relationship()
                                    ->schema([
                                        static::createdByPlaceholder(),
                                        Select::make('type')
                                            ->label('Post Type')
                                            ->options([
                                                'event' => 'Event',
                                                'message' => 'Message',
                                                'homework' => 'Homework',
                                            ])
                                            ->default('message'),
                                        TextInput::make('title')
                                            ->label('Title')
                                            ->required(),
                                        RichEditor::make('content')
                                            ->label('Content'),
                                        DatePicker::make('occurs_on_date')
                                            ->label('Target Date'),
                                        DatePicker::make('end_date')
                                            ->label('End Date'),
                                        Select::make('day_of_week')
                                            ->label('Day of Week')
                                            ->options([
                                                0 => 'Sunday',
                                                1 => 'Monday',
                                                2 => 'Tuesday',
                                                3 => 'Wednesday',
                                                4 => 'Thursday',
                                                5 => 'Friday',
                                                6 => 'Saturday',
                                            ]),
                                        Toggle::make('always_show')
                                            ->label('Always Show')
                                            ->default(false),
                                        TimePicker::make('occurs_at_time')
                                            ->label('Time'),
                                        TextInput::make('location')
                                            ->label('Location'),
                                        FileUpload::make('attachment_path')
                                            ->label('Attachment')
                                            ->disk('public')
                                            ->directory(function (Forms\Get $get): string {
                                                $classroomId = $get('../../id');
                                                return $classroomId ? "classrooms/{$classroomId}/announcements" : 'temp';
                                            })
                                            ->visibility('public'),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('Add Announcement')
                                    ->defaultItems(0),
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
                ->hidden(fn (Forms\Get $get) => !in_array((string)$dayId, array_map('strval', $get('active_days') ?? [])))
                ->schema([
                    Repeater::make($relationship)
                        ->relationship()
                        ->schema([
                            static::createdByPlaceholder(),
                            TextInput::make('subject')->label('Lesson Name')->required(),
                            TextInput::make('teacher')->label('Teacher (Optional)'),
                            TextInput::make('special_message')->label('Special Message'),
                            Forms\Components\Hidden::make('day_of_week')->default($dayId),
                        ])
                        ->columns(3)
                        ->reorderable('sort_order')
                        ->addActionLabel('Add Lesson for ' . $label)
                        ->defaultItems(0), // Fixed: Start empty
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('city.name')->label('City'),
                Tables\Columns\TextColumn::make('school.name')->label('School'),
                Tables\Columns\TextColumn::make('grade_level')->label('Grade'),
                Tables\Columns\TextColumn::make('join_code'),
                Tables\Columns\TextColumn::make('media_size_bytes')
                    ->label('Media Size')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024 / 1024, 2) . ' MB'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['city', 'school']);
    }

    /**
     * Build a creator placeholder for repeater items.
     */
    protected static function createdByPlaceholder(): Placeholder
    {
        return Placeholder::make('creator_label')
            ->label('Created By')
            ->content(fn (?Model $record): string => static::formatCreatorLabel($record))
            ->visible(fn (?Model $record): bool => (bool) $record)
            ->columnSpanFull();
    }

    /**
     * Resolve a creator label for a record.
     */
    protected static function formatCreatorLabel(?Model $record): string
    {
        if (!$record) {
            return 'Unknown';
        }

        $creator = $record->creator ?? null;
        if (!$creator) {
            return 'ADMIN';
        }

        if ($creator->role === 'site_admin') {
            return 'ADMIN';
        }

        $name = trim(($creator->first_name ?? '') . ' ' . ($creator->last_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        return $creator->name ?: ($creator->phone ?: 'User');
    }

    /**
     * Run timetable extraction for a classroom.
     */
    protected static function runTimetableExtraction(?Classroom $record, TimetableOcrService $service): void
    {
        if (!$record) {
            Notification::make()
                ->title('Classroom is missing')
                ->danger()
                ->send();
            return;
        }

        $setting = static::getGlobalAiSetting();
        if (!$setting || !$setting->token || !$setting->model || !$setting->timetable_prompt) {
            Notification::make()
                ->title('OpenRouter settings are missing')
                ->danger()
                ->send();
            return;
        }

        try {
            $service->extractAndSaveTimetable($record, $setting);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();
            return;
        }

        Notification::make()
            ->title('Timetable extracted')
            ->success()
            ->send();
    }

    /**
     * Get global AI settings.
     */
    protected static function getGlobalAiSetting(): ?AiSetting
    {
        return AiSetting::query()
            ->where('provider', self::AI_PROVIDER)
            ->whereNull('classroom_id')
            ->first();
    }
}
