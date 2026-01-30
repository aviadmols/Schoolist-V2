<?php

namespace App\Filament\Resources\WeatherGlobalSettingResource\Pages;

use App\Filament\Resources\WeatherGlobalSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWeatherGlobalSetting extends EditRecord
{
    protected static string $resource = WeatherGlobalSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
