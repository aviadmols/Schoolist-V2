<?php

namespace App\Filament\Resources\QlinkResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class QlinkVisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'visits';

    /**
     * Define the table for qlink visits.
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Visited At')->dateTime(),
                Tables\Columns\TextColumn::make('user.name')->label('User'),
                Tables\Columns\TextColumn::make('ip_address')->label('IP'),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
