<?php

namespace App\Filament\Resources\SmsSettingResource\Pages;

use App\Filament\Resources\SmsSettingResource;
use App\Models\SmsSetting;
use Filament\Resources\Pages\ListRecords;

class ListSmsSettings extends ListRecords
{
    protected static string $resource = SmsSettingResource::class;

    /**
     * Ensure a default SMS settings row exists.
     */
    protected function mount(): void
    {
        parent::mount();

        if (!SmsSetting::where('provider', 'sms019')->exists()) {
            SmsSetting::create([
                'provider' => 'sms019',
                'username' => null,
                'password' => null,
                'sender' => null,
            ]);
        }
    }
}
