<?php

namespace App\Filament\Pages;

use App\Services\Auth\OtpService;
use App\Services\Audit\AuditService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManualOtpCode extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'System';

    protected static string $view = 'filament.pages.manual-otp-code';

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * Define the form for manual OTP creation.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('phone')
                    ->label('Phone')
                    ->required()
                    ->maxLength(10),
            ])
            ->statePath('data');
    }

    /**
     * Create a one-time code without SMS.
     */
    public function createCode(OtpService $otpService, AuditService $auditService): void
    {
        $this->validate([
            'data.phone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
        ]);

        $code = $otpService->generateManual($this->data['phone']);

        $auditService->log('otp.manual_generated', null, null, [
            'phone_mask' => substr($this->data['phone'], 0, 3) . '****' . substr($this->data['phone'], -3),
        ]);

        Notification::make()
            ->title('One-time code generated')
            ->body('Code: ' . $code)
            ->success()
            ->send();
    }
}
