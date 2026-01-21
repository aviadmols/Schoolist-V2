<x-filament::page>
    <form wire:submit.prevent="createCode" class="space-y-4">
        {{ $this->form }}

        <button type="submit" class="filament-button filament-button-size-md filament-button-color-primary">
            Create Code
        </button>
    </form>
</x-filament::page>
