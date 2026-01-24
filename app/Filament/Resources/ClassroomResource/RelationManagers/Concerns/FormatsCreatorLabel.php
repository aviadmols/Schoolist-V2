<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers\Concerns;

use Illuminate\Database\Eloquent\Model;

trait FormatsCreatorLabel
{
    /**
     * Format a creator label for a record.
     */
    private function formatCreatorLabel(?Model $record): string
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
}
