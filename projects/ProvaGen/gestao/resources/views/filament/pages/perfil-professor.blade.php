<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-6 flex items-center justify-end">
            <x-filament::button type="submit" size="lg">
                Salvar Configurações
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
