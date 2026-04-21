{{--
    Settings page — Admin Screen 7. Thin wrapper around the form defined
    in App\Filament\Pages\Settings.
--}}
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit">
                Save settings
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
