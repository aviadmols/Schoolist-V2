<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BuilderTemplateResource\Pages;
use App\Models\BuilderTemplate;
use Filament\Forms;
use Filament\Forms\Form;
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
            Forms\Components\Placeholder::make('preview')
                ->label('Preview')
                ->content(function (?BuilderTemplate $record): HtmlString {
                    if (!$record) {
                        return new HtmlString('');
                    }

                    $draftUrl = route('builder.preview', [
                        'template' => $record,
                        'version' => 'draft',
                    ]);
                    $publishedUrl = route('builder.preview', [
                        'template' => $record,
                        'version' => 'published',
                    ]);

                    $html = '<div style="display:flex; gap:8px; margin-bottom:8px;">';
                    $html .= '<a href="'.$draftUrl.'" target="_blank">Open Draft Preview</a>';
                    $html .= '<a href="'.$publishedUrl.'" target="_blank">Open Published Preview</a>';
                    $html .= '</div>';
                    $html .= '<iframe src="'.$draftUrl.'" style="width:100%; min-height:480px; border:1px solid #e5e7eb;"></iframe>';

                    return new HtmlString($html);
                })
                ->columnSpanFull(),
            Forms\Components\CodeEditor::make('draft_html')
                ->label('Draft HTML')
                ->language('html')
                ->rows(20)
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
                    ->formatStateUsing(function ($state): string {
                        return $state ? 'Published' : 'Draft';
                    })
                    ->badge(),
                Tables\Columns\IconColumn::make('is_override_enabled')
                    ->label('Override')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime(),
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
