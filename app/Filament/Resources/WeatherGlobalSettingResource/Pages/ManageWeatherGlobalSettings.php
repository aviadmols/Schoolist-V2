<?php

namespace App\Filament\Resources\WeatherGlobalSettingResource\Pages;

use App\Filament\Resources\WeatherGlobalSettingResource;
use App\Models\WeatherGlobalSetting;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageWeatherGlobalSettings extends ManageRecords
{
    protected static string $resource = WeatherGlobalSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Settings')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function mount(): void
    {
        // Ensure the singleton instance exists
        WeatherGlobalSetting::getInstance();
        
        parent::mount();
    }
}
