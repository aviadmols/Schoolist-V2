<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OpenRouterLogs extends Page implements HasTable
{
    use InteractsWithTable;

    /** @var string */
    private const EVENT_NAME = 'openrouter_request';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'OpenRouter Logs';

    protected static ?string $navigationGroup = 'System';

    protected static string $view = 'filament.pages.openrouter-logs';

    /**
     * Build the logs table.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query($this->getLogQuery())
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('classroom.name')->label('Classroom')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('new_values.request.type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('new_values.request.model')->label('Model')->wrap(),
                Tables\Columns\TextColumn::make('new_values.response.status')->label('Status')->sortable(),
                Tables\Columns\TextColumn::make('new_values.response.error')->label('Error')->wrap(),
                Tables\Columns\TextColumn::make('new_values.request.prompt_preview')
                    ->label('Prompt Preview')
                    ->limit(80)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('new_values.response.content_preview')
                    ->label('Response Preview')
                    ->limit(80)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('new_values.request.type')->label('Type'),
                Tables\Filters\SelectFilter::make('new_values.response.status')->label('Status'),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    /**
     * Get the base query for OpenRouter logs.
     */
    private function getLogQuery(): Builder
    {
        return AuditLog::query()
            ->where('event', self::EVENT_NAME);
    }
}
