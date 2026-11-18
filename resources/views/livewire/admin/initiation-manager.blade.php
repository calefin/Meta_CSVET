<div>
    <a href="{{ route('admin.users') }}" class="text-sm text-ifa-green hover:underline">&larr; Voltar para usuários</a>

    <h1 class="mt-2 font-display text-2xl text-ifa-green">
        Iniciação — <span lang="yo">{{ $user->name }}</span>
    </h1>

    <div class="mt-6 rounded-lg border border-orisa-earth/20 bg-white p-6">
        <h2 class="font-display text-lg text-ifa-green">Dados da iniciação</h2>

        <form wire:submit="saveInitiation" class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium">Nome Espiritual / Orúkọ Ifá</label>
                <input type="text" wire:model="spiritual_name" placeholder="Ex: Sọlá Ifátóògùn" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                @error('spiritual_name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Odù da iniciação</label>
                <input type="text" wire:model="odu_name" list="odus-list" placeholder="Digite ou selecione o Odù" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                @error('odu_id') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Data da iniciação</label>
                <input type="date" wire:model="initiation_date" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                @error('initiation_date') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium">Observações</label>
                <textarea wire:model="notes" rows="3" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
            </div>

            <div class="md:col-span-2">
                <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Salvar</button>
            </div>
        </form>
    </div>

    @if ($initiation)
        <div class="mt-6 rounded-lg border border-orisa-earth/20 bg-white p-6">
            <h2 class="font-display text-lg text-ifa-green">Odù dos ebós e demais dias</h2>

            <ul class="mt-3 space-y-2">
                @forelse ($initiation->dayRecords as $day)
                    <li class="flex items-center justify-between rounded border border-orisa-earth/10 p-2 text-sm" wire:key="day-{{ $day->id }}">
                        <span><strong>{{ $day->day_label }}</strong> — <span lang="yo">{{ $day->odu->name }}</span> @if($day->notes) — {{ $day->notes }} @endif</span>
                        <button wire:click="deleteDayRecord({{ $day->id }})" class="text-xs text-danger hover:underline">remover</button>
                    </li>
                @empty
                    <p class="text-sm text-ebony/60">Nenhum dia registrado ainda.</p>
                @endforelse
            </ul>

            <form wire:submit="addDayRecord" class="mt-4 flex flex-wrap items-end gap-2">
                <div class="flex-1">
                    <label class="block text-sm font-medium">Dia</label>
                    <input type="text" wire:model="dayLabel" placeholder="ex: Ebó, 2º dia, Saída..." class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium">Odù</label>
                    <input type="text" wire:model="dayOduName" list="odus-list" placeholder="Digite ou selecione o Odù" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium">Observação</label>
                    <input type="text" wire:model="dayNotes" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">+ dia</button>
            </form>
            @error('dayLabel') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            @error('dayOduId') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        <div class="mt-6 rounded-lg border border-orisa-earth/20 bg-white p-6">
            <h2 class="font-display text-lg text-ifa-green">Ewó (restrições)</h2>

            <ul class="mt-3 space-y-1">
                @forelse ($initiation->ewos as $ewo)
                    <li class="flex items-center justify-between rounded bg-sacred-sand px-3 py-1.5 text-sm" wire:key="ewo-{{ $ewo->id }}">
                        <span>{{ $ewo->description }}</span>
                        <button wire:click="deleteEwo({{ $ewo->id }})" class="text-xs text-danger hover:underline">remover</button>
                    </li>
                @empty
                    <p class="text-sm text-ebony/60">Nenhum ewó registrado ainda.</p>
                @endforelse
            </ul>

            <form wire:submit="addEwo" class="mt-3 flex gap-2">
                <input type="text" wire:model="newEwo" placeholder="Descrever o ewó" class="flex-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">Adicionar</button>
            </form>
            @error('newEwo') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
    @endif

    <div class="mt-6 rounded-lg border border-orisa-earth/20 bg-white p-6">
        <div class="flex items-center justify-between">
            <h2 class="font-display text-lg text-ifa-green">Outros Orisás feitos (sem ser iniciação)</h2>
            <button wire:click="openOrisaForm" class="rounded bg-ifa-green px-3 py-1.5 text-sm text-white hover:bg-ifa-green/90">+ registrar</button>
        </div>

        <ul class="mt-3 space-y-2">
            @forelse ($orisaRecords as $record)
                <li class="flex items-center justify-between rounded border border-orisa-earth/10 p-2 text-sm" wire:key="orisa-{{ $record->id }}">
                    <span>
                        <strong>{{ $record->orisa_name }}</strong>
                        @if ($record->spiritual_name) (Orúkọ: <em class="font-bold">{{ $record->spiritual_name }}</em>) @endif
                        — {{ $record->performed_at->format('d/m/Y') }}
                        @if ($record->odu) — <span lang="yo">{{ $record->odu->name }}</span> @endif
                        @if ($record->notes) — {{ $record->notes }} @endif
                    </span>
                    <button wire:click="deleteOrisaRecord({{ $record->id }})" class="text-xs text-danger hover:underline">remover</button>
                </li>
            @empty
                <p class="text-sm text-ebony/60">Nenhum registro ainda.</p>
            @endforelse
        </ul>
    </div>

    @if ($showOrisaForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">Registrar Orisá feito</h2>

                <form wire:submit="saveOrisaRecord" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Orisá</label>
                        <input type="text" wire:model="orisaName" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('orisaName') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Nome Espiritual / Orúkọ do Orisá</label>
                        <input type="text" wire:model="orisaSpiritualName" placeholder="Ex: Adébísí" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('orisaSpiritualName') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Data</label>
                        <input type="date" wire:model="orisaDate" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('orisaDate') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Odù (opcional)</label>
                        <input type="text" wire:model="orisaOduName" list="odus-list" placeholder="Digite ou selecione o Odù" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Observações</label>
                        <textarea wire:model="orisaNotes" rows="2" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showOrisaForm', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
    <datalist id="odus-list">
        @foreach ($odus as $odu)
            <option value="{{ $odu->name }}"></option>
        @endforeach
    </datalist>
</div>
