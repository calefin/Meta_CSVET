<x-filament-panels::page>
    <form wire:submit.prevent="submit">
        {{ $this->form }}

        <div style="margin-top: 1.5rem; display: flex; align-items: center; justify-content: flex-end;">
            <x-filament::button type="submit" size="lg" icon="heroicon-o-check-circle">
                Cadastrar Projeto e Iniciar Vigência
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
