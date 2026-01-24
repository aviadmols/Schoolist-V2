<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit.prevent="saveSettings">
            {{ $this->form }}
            <div class="mt-6 flex flex-wrap gap-3">
                <x-filament::button type="submit" color="primary">
                    Save Settings
                </x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="testConnection">
                    Test Connection
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
