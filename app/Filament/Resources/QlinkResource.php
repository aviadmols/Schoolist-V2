<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QlinkResource\Pages;
use App\Filament\Resources\QlinkResource\RelationManagers;
use App\Models\Qlink;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

class QlinkResource extends Resource
{
    protected static ?string $model = Qlink::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'System';

    /** @var int */
    private const TOKEN_LENGTH = 12;

    /**
     * Define the form for qlinks.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('token')
                ->label('Token')
                ->disabled()
                ->dehydrated(),
            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    /**
     * Define the table for qlinks.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('token')->label('Token'),
                Tables\Columns\TextColumn::make('classroom.name')->label('Classroom'),
                Tables\Columns\TextColumn::make('visits_count')->label('Visits')->counts('visits'),
                Tables\Columns\TextColumn::make('visits_max_created_at')
                    ->label('Last Visit')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('public_url')
                    ->label('Open Link')
                    ->state(fn (Qlink $record): string => url('/qlink/' . $record->token))
                    ->url(fn (Qlink $record): string => url('/qlink/' . $record->token), true),
                Tables\Columns\TextColumn::make('template_status')
                    ->label('Template Status')
                    ->getStateUsing(function (Qlink $record): string {
                        $template = \App\Models\BuilderTemplate::where('key', 'auth.qlink')
                            ->where('scope', 'global')
                            ->first();

                        if (!$template) return 'No Template';
                        if (!$template->is_override_enabled) return 'Override Disabled (Using Default)';

                        $hasPublished = (bool) ($template->published_html || $template->published_css || $template->published_js);
                        return $hasPublished ? 'Custom Template Active' : 'Draft Only (Using Default)';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Custom Template Active' => 'success',
                        'No Template' => 'danger',
                        'Override Disabled (Using Default)', 'Draft Only (Using Default)' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(fn (Qlink $record): bool => (bool) $record->classroom_id),
                Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * Mutate form data before create.
     *
     * @param array $data
     * @return array
     */
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['token'] = $data['token'] ?: static::generateToken();
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    /**
     * Generate a random numeric token.
     */
    private static function generateToken(): string
    {
        $token = '';
        for ($i = 0; $i < self::TOKEN_LENGTH; $i++) {
            $token .= (string) random_int(0, 9);
        }

        return $token;
    }

    /**
     * Define the pages for this resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQlinks::route('/'),
            'create' => Pages\CreateQlink::route('/create'),
            'edit' => Pages\EditQlink::route('/{record}/edit'),
        ];
    }

    /**
     * Define the relation managers for qlinks.
     */
    public static function getRelations(): array
    {
        return [
            RelationManagers\QlinkVisitsRelationManager::class,
        ];
    }

    /**
     * Optimize the table query.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['classroom'])
            ->withCount('visits')
            ->withMax('visits', 'created_at');
    }
}
