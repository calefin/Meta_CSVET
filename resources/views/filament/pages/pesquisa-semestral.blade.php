<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-teal-300 bg-teal-50 p-4 text-sm text-teal-800 dark:border-teal-500/30 dark:bg-teal-500/10 dark:text-teal-400">
        Antes de continuar, conte pro professor o que você está fazendo neste semestre. Isso leva 10 segundos e só é perguntado uma vez por semestre.
    </div>

    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-6 flex items-center justify-end">
            <x-filament::button type="submit" size="lg">
                Confirmar
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
