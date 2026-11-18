<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Cursos</h1>
        <button wire:click="create" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Novo curso
        </button>
    </div>

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Título</th>
                    <th class="px-4 py-2">Público</th>
                    <th class="px-4 py-2">Preço</th>
                    <th class="px-4 py-2">Módulos</th>
                    <th class="px-4 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($courses as $course)
                    <tr class="border-t border-orisa-earth/10" wire:key="course-{{ $course->id }}">
                        <td class="px-4 py-2">{{ $course->title }}</td>
                        <td class="px-4 py-2">{{ match($course->target) { 'iniciado' => 'Iniciados', 'consulente' => 'Consulentes', 'both' => 'Ambos' } }}</td>
                        <td class="px-4 py-2">{{ $course->price > 0 ? 'R$ '.number_format($course->price, 2, ',', '.') : 'Gratuito' }}</td>
                        <td class="px-4 py-2">{{ $course->modules_count }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ route('admin.courses.show', $course) }}" class="text-ifa-green hover:underline">Gerenciar</a>
                            <button wire:click="edit({{ $course->id }})" class="ml-2 text-orun-blue hover:underline">Editar</button>
                            <button wire:click="delete({{ $course->id }})" wire:confirm="Remover este curso?" class="ml-2 text-danger hover:underline">Remover</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $courses->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 max-h-[90vh] overflow-y-auto">
                <h2 class="font-display text-xl text-ifa-green">{{ $editingId ? 'Editar' : 'Novo' }} curso</h2>

                @if ($editingId)
                    <div class="mt-3 rounded border border-ifa-green/20 bg-sacred-sand/30 p-3 text-xs text-ebony/80 flex items-center justify-between gap-2">
                        <span>Deseja editar o conteúdo do curso (aulas e módulos)?</span>
                        <a href="{{ route('admin.courses.show', $editingId) }}" class="shrink-0 rounded bg-ifa-green px-2.5 py-1 text-white hover:bg-ifa-green/90 font-bold transition-colors">Gerenciar Aulas</a>
                    </div>
                @endif

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Título</label>
                        <input type="text" wire:model="title" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('title') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Descrição</label>
                        <textarea wire:model="description" rows="3" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Vitrine (quem vê o curso no catálogo)</label>
                        <select wire:model="target" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            <option value="both">Iniciados e consulentes</option>
                            <option value="iniciado">Somente iniciados</option>
                            <option value="consulente">Somente consulentes</option>
                        </select>
                        <p class="mt-1 text-xs text-ebony/50">Consulentes nunca conseguem abrir um curso, mesmo aparecendo na vitrine — isso vale para qualquer opção acima.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Preço (R$, 0 = gratuito)</label>
                        <input type="number" step="0.01" wire:model="price" class="mt-1 w-full max-w-[10rem] rounded border border-orisa-earth/30 px-3 py-2">
                    </div>

                    <div class="rounded border border-orisa-earth/20 bg-sacred-sand/30 p-3">
                        <label class="block text-sm font-medium">Liberar automaticamente para quem já recebeu</label>
                        <p class="mt-1 text-xs text-ebony/50">Deixe os dois campos em branco se o curso só deve ser liberado por pagamento. Se preencher um deles, quem já tiver essa característica entra de graça, sem precisar comprar.</p>

                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs text-ebony/60">Orisá recebido</label>
                                <input type="text" wire:model="required_orisa_name" placeholder="Ex: Yemanjá" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-ebony/60">Odù de iniciação</label>
                                <select wire:model="required_odu_id" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                                    <option value="">— Nenhum —</option>
                                    @foreach ($odus as $odu)
                                        <option value="{{ $odu->id }}">{{ $odu->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @error('required_orisa_name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        @error('required_odu_id') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="active" id="course-active">
                        <label for="course-active" class="text-sm">Ativo</label>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
