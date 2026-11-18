<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Usuários</h1>
        <button wire:click="create" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">
            Novo usuário
        </button>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nome ou e-mail..."
            class="w-full max-w-sm rounded border border-orisa-earth/30 px-3 py-2 text-sm">

        <select wire:model.live="roleFilter" class="rounded border border-orisa-earth/30 px-3 py-2 text-sm">
            <option value="">Todos os papéis</option>
            <option value="admin">Administrador</option>
            <option value="iniciado">Iniciado</option>
            <option value="consulente">Consulente</option>
        </select>

        <select wire:model.live="statusFilter" class="rounded border border-orisa-earth/30 px-3 py-2 text-sm">
            <option value="">Todos os status</option>
            <option value="pending">Pendente</option>
            <option value="active">Ativo</option>
            <option value="suspended">Suspenso</option>
        </select>
    </div>

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">E-mail</th>
                    <th class="px-4 py-2">Papel</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr class="border-t border-orisa-earth/10" wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-2" lang="yo">{{ $user->name }}</td>
                        <td class="px-4 py-2">{{ $user->email }}</td>
                        <td class="px-4 py-2">{{ match($user->role) { 'admin' => 'Administrador', 'iniciado' => 'Iniciado', 'consulente' => 'Consulente' } }}</td>
                        <td class="px-4 py-2">
                            <span class="{{ match($user->status) { 'pending' => 'text-orisa-earth', 'active' => 'text-success', 'suspended' => 'text-danger' } }}">
                                {{ match($user->status) { 'pending' => 'Pendente', 'active' => 'Ativo', 'suspended' => 'Suspenso' } }}
                            </span>
                        </td>
                        <td class="px-4 py-2 space-x-2">
                            <button wire:click="edit({{ $user->id }})" class="text-ifa-green hover:underline">Editar</button>
                            @if (in_array($user->role, ['iniciado', 'consulente']))
                                <a href="{{ route('admin.clients.show', $user) }}" class="text-ifa-green hover:underline">Perfil</a>
                            @endif
                            @if ($user->role === 'iniciado')
                                <a href="{{ route('admin.initiations.show', $user) }}" class="text-orun-blue hover:underline">Iniciação</a>
                            @endif
                            <a href="{{ route('admin.notes.user', $user->id) }}" class="text-orisa-earth hover:underline">Anotações</a>
                            @if (in_array($user->role, ['iniciado', 'consulente']))
                                <a href="{{ route('admin.library.access', $user->id) }}" class="text-orun-blue hover:underline">Biblioteca</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>

    @if ($showCreateForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">Novo usuário</h2>
                <p class="mt-1 text-xs text-ebony/50">Use para cadastrar manualmente uma pessoa que ainda não tem conta (ex.: cliente presencial). Ela receberá um e-mail para definir a própria senha.</p>

                <form wire:submit="store" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Nome</label>
                        <input type="text" wire:model="name" lang="yo" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Nome de solteira da mãe</label>
                        <input type="text" wire:model="mother_maiden_name" lang="yo" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('mother_maiden_name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">E-mail</label>
                        <input type="email" wire:model="email" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('email') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Telefone</label>
                        <input type="text" wire:model="phone" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-medium">Papel</label>
                            <select wire:model="role" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                                <option value="admin">Administrador</option>
                                <option value="iniciado">Iniciado</option>
                                <option value="consulente">Consulente</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Status</label>
                            <select wire:model="status" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                                <option value="pending">Pendente</option>
                                <option value="active">Ativo</option>
                                <option value="suspended">Suspenso</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="cancelCreate" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Criar usuário</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($editingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">Editar usuário</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Nome</label>
                        <input type="text" wire:model="name" lang="yo" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">E-mail</label>
                        <input type="email" wire:model="email" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('email') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Telefone</label>
                        <input type="text" wire:model="phone" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-medium">Papel</label>
                            <select wire:model="role" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                                <option value="admin">Administrador</option>
                                <option value="iniciado">Iniciado</option>
                                <option value="consulente">Consulente</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Status</label>
                            <select wire:model="status" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                                <option value="pending">Pendente</option>
                                <option value="active">Ativo</option>
                                <option value="suspended">Suspenso</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="cancelEdit" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
