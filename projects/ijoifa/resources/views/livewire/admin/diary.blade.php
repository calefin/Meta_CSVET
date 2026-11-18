<div>
    <h1 class="font-display text-2xl text-ifa-green">Diário do sacerdote</h1>
    <p class="text-sm text-ebony/60">Registro privado de consultas e trabalhos feitos por você — pessoais, da casa, da família ou para iniciados. Visível apenas para administradores.</p>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <select wire:model.live="categoryFilter" class="rounded border border-orisa-earth/30 px-3 py-2 text-sm">
            <option value="">Todas as categorias</option>
            <option value="pessoal">Pessoal</option>
            <option value="casa">Da casa</option>
            <option value="familia">Família</option>
            <option value="iniciado_externo">Iniciado (externo)</option>
        </select>

        <div class="flex items-center gap-2">
            <button wire:click="$set('showOduSearchDrawer', true)" class="rounded border border-ifa-green px-3 py-2 text-sm text-ifa-green hover:bg-ifa-green/5 flex items-center gap-1.5 font-medium transition-colors">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd"/>
                </svg>
                Consulta Rápida de Odù
            </button>
            <button wire:click="openForm" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90 transition-colors font-medium">+ novo registro</button>
        </div>
    </div>

    <div class="mt-4 space-y-3">
        @foreach ($entries as $entry)
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-4" wire:key="entry-{{ $entry->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs text-ebony/40">{{ $entry->entry_date->format('d/m/Y') }} — {{ match($entry->category) { 'pessoal' => 'Pessoal', 'casa' => 'Da casa', 'familia' => 'Família', 'iniciado_externo' => 'Iniciado (externo)' } }}</p>
                        <h3 class="font-display text-lg text-ifa-green">{{ $entry->title }}</h3>
                        @if ($entry->subject)
                            <p class="text-xs text-ebony/50" lang="yo">Sobre: {{ $entry->subject->name }}</p>
                        @endif
                        @if ($entry->odu)
                            <p class="text-xs text-ebony/50">Odù: {{ $entry->odu->name }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button wire:click="edit({{ $entry->id }})" class="text-xs text-ifa-green hover:underline">editar</button>
                        <button wire:click="delete({{ $entry->id }})" wire:confirm="Remover este registro?" class="text-xs text-danger hover:underline">remover</button>
                    </div>
                </div>
                <div class="prose max-w-none text-sm text-ebony/80 mt-2">{!! clean($entry->content, 'lesson_content') !!}</div>
            </div>
        @endforeach

        @if ($entries->isEmpty())
            <p class="text-sm text-ebony/50">Nenhum registro ainda.</p>
        @endif
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">{{ $editingId ? 'Editar registro' : 'Novo registro' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-medium">Data</label>
                            <input type="date" wire:model="entryDate" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            @error('entryDate') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Categoria</label>
                            <select wire:model="category" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                                <option value="pessoal">Pessoal</option>
                                <option value="casa">Da casa</option>
                                <option value="familia">Família</option>
                                <option value="iniciado_externo">Iniciado (externo)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Título</label>
                        <input type="text" wire:model="title" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('title') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-medium">Sobre (opcional)</label>
                            <select wire:model="subjectUserId" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                                <option value="">—</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->id }}" lang="yo">{{ $subject->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Odù (opcional)</label>
                            <select wire:model="oduId" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                                <option value="">—</option>
                                @foreach ($odus as $odu)
                                    <option value="{{ $odu->id }}">{{ $odu->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Conteúdo</label>
                        <div
                            wire:key="editor-{{ $editingId ?? 'new' }}"
                            wire:ignore
                            class="mt-1"
                            x-data="{ editor: null }"
                            x-init="editor = window.IjoifaEditor.mount($refs.editorEl, {
                                initialHtml: @js($content),
                                onChange: (html) => $wire.set('content', html),
                            })"
                        >
                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    class="mb-1 text-xs text-ifa-green hover:underline animate-pulse-subtle"
                                    x-on:click="
                                        const md = prompt('Cole o texto em Markdown:');
                                        if (md !== null) { editor.importMarkdown(md); }
                                    "
                                >importar Markdown</button>
                            </div>
                            <div x-ref="editorEl" class="bg-white rounded border border-orisa-earth/20 ql-editor-diary" style="min-height: 12rem"></div>
                        </div>
                        @error('content') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showForm', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Odu Quick Search Drawer --}}
    @if ($showOduSearchDrawer)
        <div class="fixed inset-0 z-50 flex justify-end bg-black/50">
            <div class="absolute inset-0 cursor-pointer" wire:click="$set('showOduSearchDrawer', false)"></div>

            <div class="relative w-full max-w-lg bg-white h-full shadow-2xl flex flex-col z-10 transition-transform">
                <div class="bg-ifa-green text-white p-4 flex justify-between items-center">
                    <h3 class="font-display text-lg">Consulta Rápida de Odù</h3>
                    <button type="button" wire:click="$set('showOduSearchDrawer', false)" class="text-2xl hover:text-white/80">&times;</button>
                </div>

                <div class="p-4 flex-1 overflow-y-auto flex flex-col">
                    @if (!$searchedOdu)
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Buscar por Nome, Número ou Apelido</label>
                                <input type="text" wire:model.live.debounce.300ms="oduSearch" placeholder="Ex: Ejiogbe, 1.1, Ogbè, Oyekú..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green focus:ring-1 focus:outline-none">
                            </div>

                            <div class="space-y-2 mt-4">
                                @forelse ($searchResults as $result)
                                    <button type="button" wire:click="selectSearchedOdu({{ $result->id }})" class="w-full text-left p-3 rounded-lg border border-orisa-earth/10 hover:border-ifa-green/40 hover:bg-ifa-green/5 transition-all flex items-center justify-between">
                                        <div>
                                            <span class="font-semibold text-sm text-ebony" lang="yo">{{ $result->name }}</span>
                                            @if (!empty($result->alternative_names))
                                                <span class="text-xs text-ebony/50 block">({{ implode(', ', $result->alternative_names) }})</span>
                                            @endif
                                        </div>
                                        <span class="text-xs font-mono text-ebony/40 bg-sacred-sand px-2 py-0.5 rounded">#{{ $result->number }}</span>
                                    </button>
                                @empty
                                    @if (strlen($oduSearch) >= 2)
                                        <p class="text-sm text-ebony/40 text-center py-4">Nenhum resultado encontrado.</p>
                                    @else
                                        <p class="text-sm text-ebony/40 text-center py-4">Digite pelo menos 2 caracteres para buscar...</p>
                                    @endif
                                @endforelse
                            </div>
                        </div>
                    @else
                        <div class="flex-1 flex flex-col gap-4">
                            <div class="flex items-center gap-2 border-b border-orisa-earth/10 pb-3">
                                <button type="button" wire:click="$set('searchedOdu', null)" class="text-xs text-ifa-green hover:underline flex items-center gap-1">
                                    &larr; Nova busca
                                </button>
                            </div>

                            <div class="text-center bg-sacred-sand border border-orisa-earth/10 rounded-lg p-4 flex flex-col items-center">
                                <span class="text-xs font-mono uppercase bg-ifa-green/10 text-ifa-green px-2 py-0.5 rounded">Odù #{{ $searchedOdu->number }} — {{ $searchedOdu->category === 'meji' ? 'Méjì' : 'Améjì' }}</span>
                                <h4 class="font-display text-xl text-ifa-green mt-2" lang="yo">{{ $searchedOdu->name }}</h4>
                                @if (!empty($searchedOdu->alternative_names))
                                    <p class="text-xs text-ebony/60">({{ implode(', ', $searchedOdu->alternative_names) }})</p>
                                @endif

                                <div class="mt-4 font-mono text-2xl font-bold tracking-widest text-ifa-green border border-ifa-green/20 rounded-lg p-4 bg-white leading-normal whitespace-pre select-none inline-block">
                                    {{ $searchedOdu->binary_mark }}
                                </div>
                            </div>

                            @if (!empty($searchedOdu->orisas))
                                <div>
                                    <span class="text-xs uppercase font-semibold text-ebony/50 tracking-wider block mb-1">Òrìṣàs Relacionados</span>
                                    <p class="text-sm text-orisa-earth font-medium">{{ implode(', ', $searchedOdu->orisas) }}</p>
                                </div>
                            @endif

                            @if (!empty($searchedOdu->summary))
                                <div>
                                    <span class="text-xs uppercase font-semibold text-ebony/50 tracking-wider block mb-1">Resumo Geral</span>
                                    <ul class="list-disc pl-5 space-y-1 text-sm text-ebony/80 leading-relaxed">
                                        @foreach($searchedOdu->summary as $sumLine)
                                            <li>{{ $sumLine }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (!empty($searchedOdu->ebos))
                                <div>
                                    <span class="text-xs uppercase font-semibold text-ebony/50 tracking-wider block mb-1">Ebós & Recomendações</span>
                                    <ul class="list-disc pl-5 space-y-1 text-sm text-ebony/80 leading-relaxed">
                                        @foreach($searchedOdu->ebos as $eboLine)
                                            <li>{{ $eboLine }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="bg-sacred-sand p-4 border-t border-orisa-earth/10 flex justify-end">
                    <button type="button" wire:click="$set('showOduSearchDrawer', false)" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90 font-medium">Fechar</button>
                </div>
            </div>
        </div>
    @endif
</div>
