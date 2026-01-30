<?php

namespace App\Filament\Resources\WeatherSettingResource\Pages;

use App\Filament\Resources\WeatherSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWeatherSettings extends ListRecords
{
    protected static string $resource = WeatherSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
