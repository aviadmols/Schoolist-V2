<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BuilderTemplateResource\Pages;
use App\Forms\Components\CodeEditor;
use App\Models\BuilderTemplate;
use App\Models\Classroom;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class BuilderTemplateResource extends Resource
{
    protected static ?string $model = BuilderTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-code-bracket-square';

    protected static ?string $navigationGroup = 'Builder';

    /**
     * Define the template form.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('key')
                ->label('Key')
                ->disabled()
                ->dehydrated(),
            Forms\Components\Select::make('type')
                ->label('Type')
                ->options([
                    BuilderTemplate::TYPE_SCREEN => 'Screen',
                    BuilderTemplate::TYPE_SECTION => 'Section',
                ])
                ->disabled()
                ->dehydrated(),
            Forms\Components\Toggle::make('is_override_enabled')
                ->label('Override Enabled'),
            Grid::make(2)
                ->schema([
                    Select::make('preview_classroom_id')
                        ->label('Preview Classroom')
                        ->options(function (): array {
                            return Classroom::query()
                                ->orderBy('name', 'asc')
                                ->limit(50)
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->live()
                        ->dehydrated(false),
                    Select::make('preview_user_id')
                        ->label('Preview User')
                        ->options(function (): array {
                            return User::query()
                                ->orderBy('name', 'asc')
                                ->limit(50)
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->live()
                        ->dehydrated(false),
                ])
                ->columnSpanFull(),
            Grid::make(2)
                ->schema([
                    Section::make('Preview')
                        ->schema([
                            Placeholder::make('preview')
                                ->label('')
                                ->content(function (?BuilderTemplate $record, Get $get): HtmlString {
                                    if (!$record) {
                                        return new HtmlString('');
                                    }

                                    $params = array_filter([
                                        'template' => $record,
                                        'version' => 'draft',
                                        'preview_classroom_id' => $get('preview_classroom_id'),
                                        'preview_user_id' => $get('preview_user_id'),
                                    ]);
                                    $url = route('builder.preview', $params);

                                    return new HtmlString(
                                        view('filament.builder-template-preview', ['previewUrl' => $url])->render()
                                    );
                                }),
                        ])
                        ->columnSpan(1),
                    Section::make('HTML')
                        ->schema([
                            CodeEditor::make('draft_html')
                                ->label('')
                                ->rows(18),
                        ])
                        ->columnSpan(1),
                    Section::make('CSS')
                        ->schema([
                            CodeEditor::make('draft_css')
                                ->label('')
                                ->rows(18),
                        ])
                        ->columnSpan(1),
                    Section::make('JS')
                        ->schema([
                            CodeEditor::make('draft_js')
                                ->label('')
                                ->rows(18),
                        ])
                        ->columnSpan(1),
                ])
                ->columnSpanFull(),
            Forms\Components\Textarea::make('mock_data_json')
                ->label('Mock Data JSON')
                ->rows(6)
                ->helperText('Optional JSON object for preview.')
                ->rule('nullable')
                ->rule('json')
                ->formatStateUsing(function ($state): string {
                    if (!$state) {
                        return '';
                    }

                    return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
                })
                ->dehydrateStateUsing(function ($state): ?array {
                    if (!$state) {
                        return null;
                    }

                    $decoded = json_decode($state, true);

                    return is_array($decoded) ? $decoded : null;
                })
                ->columnSpanFull(),
            Forms\Components\Repeater::make('versions')
                ->label('Versions')
                ->relationship('versions')
                ->schema([
                    Forms\Components\TextInput::make('version_type')->label('Type'),
                    Forms\Components\TextInput::make('created_at')->label('Created At'),
                ])
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->disabled()
                ->dehydrated(false)
                ->columnSpanFull(),
        ]);
    }

    /**
     * Define the template table.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('key')
                    ->label('Key')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('published_html')
                    ->label('Status')
                    ->formatStateUsing(function ($state, BuilderTemplate $record): string {
                        $hasPublished = (bool) (
                            $record->published_html
                            || $record->published_css
                            || $record->published_js
                        );

                        return $hasPublished ? 'Published' : 'Draft';
                    })
                    ->badge(),
                Tables\Columns\IconColumn::make('is_override_enabled')
                    ->label('Override')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('preview_url')
                    ->label('Preview')
                    ->getStateUsing(fn (BuilderTemplate $record): string => $record->key === 'auth.qlink' ? url('/qlink/123456789012') : ($record->key === 'auth.login' ? url('/login') : ''))
                    ->url(fn (string $state): string => $state, true)
                    ->visible(fn (?BuilderTemplate $record): bool => $record && in_array($record->key, ['auth.qlink', 'auth.login'])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    /**
     * Limit records to allowed keys and global scope.
     */
    public static function getEloquentQuery(): Builder
    {
        $allowedKeys = (array) config('builder.allowed_keys', []);
        $popupPrefix = (string) config('builder.popup_prefix');

        return parent::getEloquentQuery()
            ->where('scope', config('builder.scope'))
            ->where(function (Builder $query) use ($allowedKeys, $popupPrefix) {
                $query->whereIn('key', $allowedKeys)
                    ->orWhere('key', 'like', $popupPrefix.'%');
            });
    }

    /**
     * Define resource pages.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBuilderTemplates::route('/'),
            'edit' => Pages\EditBuilderTemplate::route('/{record}/edit'),
        ];
    }

    /**
     * Disable default create action.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Determine if the user can view any templates.
     */
    public static function canViewAny(): bool
    {
        return Gate::allows('manage_screen_builder');
    }

    /**
     * Determine if the user can edit templates.
     */
    public static function canEdit($record): bool
    {
        return Gate::allows('manage_screen_builder');
    }
}
