<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400">
        Por segurança, você precisa definir uma nova senha antes de continuar usando o portal.
    </div>

    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-6 flex items-center justify-end">
            <x-filament::button type="submit" size="lg">
                Salvar Nova Senha
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
