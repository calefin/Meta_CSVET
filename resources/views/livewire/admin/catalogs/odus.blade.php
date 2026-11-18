<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Catálogo de Odù</h1>
        <button wire:click="create" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Novo Odù
        </button>
    </div>

    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nome..."
        class="mt-4 w-full max-w-sm rounded border border-orisa-earth/30 px-3 py-2 text-sm">

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Número</th>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Categoria</th>
                    <th class="px-4 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($odus as $odu)
                    <tr class="border-t border-orisa-earth/10" wire:key="odu-{{ $odu->id }}">
                        <td class="px-4 py-2 font-mono">{{ $odu->number }}</td>
                        <td class="px-4 py-2" lang="yo">
                            <span class="font-medium text-ebony">{{ $odu->name }}</span>
                            @if(!empty($odu->alternative_names))
                                <span class="text-xs text-ebony/50 block">({{ implode(', ', $odu->alternative_names) }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $odu->category === 'meji' ? 'Méjì' : 'Améjì' }}</td>
                        <td class="px-4 py-2">
                            <button wire:click="viewOdu({{ $odu->id }})" class="text-ifa-green hover:underline font-medium">Visualizar</button>
                            <button wire:click="edit({{ $odu->id }})" class="ml-3 text-ebony/60 hover:underline">Editar</button>
                            <button wire:click="delete({{ $odu->id }})" wire:confirm="Remover este Odù?" class="ml-3 text-danger hover:underline">Remover</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $odus->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">{{ $editingId ? 'Editar' : 'Novo' }} Odù</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Nome</label>
                        <input type="text" wire:model="name" lang="yo" autocorrect="off" autocapitalize="none" spellcheck="false"
                            id="odu-name" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        <x-yoruba-keyboard target="odu-name" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Número</label>
                        <input type="number" wire:model="number" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('number') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Categoria</label>
                        <select wire:model="category" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            <option value="meji">Méjì</option>
                            <option value="ameji">Améjì</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Descrição interna</label>
                        <textarea wire:model="description" rows="3" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Detailed Odu Viewing Modal --}}
    @if ($showViewModal && $viewingOdu)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-3xl rounded-lg bg-white overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                <div class="bg-ifa-green text-white p-5 flex justify-between items-start">
                    <div>
                        <span class="text-xs font-mono uppercase bg-white/20 px-2 py-0.5 rounded">Odù #{{ $viewingOdu->number }} — {{ $viewingOdu->category === 'meji' ? 'Méjì' : 'Améjì' }}</span>
                        <h2 class="font-display text-2xl mt-1" lang="yo">
                            {{ $viewingOdu->name }}
                            @if(!empty($viewingOdu->alternative_names))
                                <span class="text-lg opacity-80">({{ implode(', ', $viewingOdu->alternative_names) }})</span>
                            @endif
                        </h2>
                    </div>
                    <button type="button" wire:click="$set('showViewModal', false)" class="text-2xl hover:text-white/80" aria-label="Fechar">&times;</button>
                </div>

                <div class="p-6 overflow-y-auto grid gap-6 md:grid-cols-3 flex-1">
                    {{-- Column 1: Binary Mark and Info --}}
                    <div class="flex flex-col items-center justify-start border-r border-orisa-earth/10 pr-0 md:pr-6 gap-4">
                        <div class="text-center">
                            <span class="text-xs uppercase font-semibold text-ebony/50 tracking-wider">Assinatura Sagrada</span>
                            <div class="mt-2 font-mono text-3xl font-bold tracking-widest text-ifa-green border border-ifa-green/20 rounded-lg p-6 bg-sacred-sand leading-relaxed whitespace-pre select-none inline-block">
                                {{ $viewingOdu->binary_mark }}
                            </div>
                        </div>

                        @if(!empty($viewingOdu->orisas))
                            <div class="w-full mt-2 text-center md:text-left">
                                <span class="text-xs uppercase font-semibold text-ebony/50 tracking-wider block mb-1">Òrìṣàs Relacionados</span>
                                <p class="text-sm text-orisa-earth font-medium">{{ implode(', ', $viewingOdu->orisas) }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Column 2 & 3: Summary and recommendations --}}
                    <div class="md:col-span-2 space-y-5">
                        @if(!empty($viewingOdu->summary))
                            <div>
                                <h3 class="font-display text-lg text-ifa-green border-b border-orisa-earth/10 pb-1 mb-2">Resumo Geral</h3>
                                <ul class="list-disc pl-5 space-y-1 text-sm text-ebony/80 leading-relaxed">
                                    @foreach($viewingOdu->summary as $sumLine)
                                        <li>{{ $sumLine }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(!empty($viewingOdu->ebos))
                            <div>
                                <h3 class="font-display text-lg text-ifa-green border-b border-orisa-earth/10 pb-1 mb-2">Ebós & Recomendações</h3>
                                <ul class="list-disc pl-5 space-y-1.5 text-sm text-ebony/80 leading-relaxed">
                                    @foreach($viewingOdu->ebos as $eboLine)
                                        <li>{{ $eboLine }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="bg-sacred-sand px-6 py-4 flex justify-between items-center border-t border-orisa-earth/10">
                    <span class="text-xs text-ebony/50 font-mono">Resumos de Odu</span>
                    <button type="button" wire:click="$set('showViewModal', false)" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90 font-medium">Fechar</button>
                </div>
            </div>
        </div>
    @endif
</div>
