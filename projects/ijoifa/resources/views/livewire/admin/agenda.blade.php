<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Agenda</h1>
        <button wire:click="create" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Novo agendamento
        </button>
    </div>

    <div
        wire:key="calendar-{{ $events->count() }}-{{ $events->pluck('id')->implode('-') }}"
        wire:ignore
        x-data
        x-init="
            const calendar = window.initAgendaCalendar($refs.calendarEl, @js($events), (id) => $wire.edit(parseInt(id)));
            $el.addEventListener('livewire:navigating', () => calendar.destroy());
        "
        class="mt-4 rounded-lg border border-orisa-earth/20 bg-white p-4"
    >
        <div x-ref="calendarEl"></div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">{{ $editingId ? 'Editar' : 'Novo' }} agendamento</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Consulente</label>
                        <select wire:model="user_id" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            <option value="">— Selecione —</option>
                            @foreach ($consulentes as $consulente)
                                <option value="{{ $consulente->id }}" lang="yo">{{ $consulente->name }}</option>
                            @endforeach
                        </select>
                        @error('user_id') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Data e hora</label>
                        <input type="datetime-local" wire:model="scheduled_at" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('scheduled_at') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-medium">Modalidade</label>
                            <select wire:model="modality" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                                <option value="online">Online</option>
                                <option value="presential">Presencial</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Status</label>
                            <select wire:model="status" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                                <option value="pending">Pendente</option>
                                <option value="confirmed">Confirmado</option>
                                <option value="completed">Realizado</option>
                                <option value="cancelled">Cancelado</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Tipo de atendimento</label>
                        <input type="text" wire:model="type" placeholder="ex: Jogo de Ifá, retorno..." class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Link de videochamada</label>
                        <input type="text" wire:model="video_link" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Observações</label>
                        <textarea wire:model="notes" rows="2" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
                    </div>

                    @if ($status === 'completed')
                        <p class="rounded bg-orun-blue/10 p-2 text-xs text-orun-blue">
                            Ao marcar como Realizado, registre a consulta oracular do consulente na area "Ciclo oracular".
                        </p>
                    @endif

                    <div class="flex justify-between gap-2">
                        @if ($editingId)
                            <button type="button" wire:click="delete({{ $editingId }})" wire:confirm="Remover este agendamento?" class="text-sm text-danger hover:underline">Remover</button>
                        @else
                            <span></span>
                        @endif
                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('showModal', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                            <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Salvar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
