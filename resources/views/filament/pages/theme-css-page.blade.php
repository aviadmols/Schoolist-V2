<x-filament::page>
    <form wire:submit.prevent="saveDraft" class="space-y-4">
        {{ $this->form }}

        <div class="flex gap-2">
            <x-filament::button type="submit">
                Save Draft
            </x-filament::button>
            <x-filament::button type="button" color="success" wire:click="publish">
                Publish
            </x-filament::button>
            <x-filament::button type="button" color="danger" wire:click="resetDraft">
                Reset Draft
            </x-filament::button>
        </div>
    </form>
</x-filament::page>
