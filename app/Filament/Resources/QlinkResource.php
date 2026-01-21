<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QlinkResource\Pages;
use App\Models\Qlink;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

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
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
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
}
