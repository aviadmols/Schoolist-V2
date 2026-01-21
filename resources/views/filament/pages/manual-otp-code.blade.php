<x-filament::page>
    <x-filament::form wire:submit.prevent="createCode">
        {{ $this->form }}

        <x-filament::button type="submit">
            Create Code
        </x-filament::button>
    </x-filament::form>
</x-filament::page>
