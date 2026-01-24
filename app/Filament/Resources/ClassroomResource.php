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
                            ]),
                    ])->columnSpanFull()
            ])->columns(1);
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
            RelationManagers\TimetableEntriesRelationManager::class,
            RelationManagers\ImportantContactsRelationManager::class,
            RelationManagers\ChildrenRelationManager::class,
            RelationManagers\LinksRelationManager::class,
            RelationManagers\HolidaysRelationManager::class,
            RelationManagers\AnnouncementsRelationManager::class,
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
