<div>
    <h1 class="mb-6 font-display text-2xl text-ifa-green">Aprovação de cadastros</h1>

    @if ($pendingUsers->isEmpty())
        <p class="text-sm text-ebony/70">Nenhum cadastro pendente.</p>
    @else
        <div class="overflow-x-auto rounded border border-orisa-earth/20 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-sacred-sand">
                    <tr>
                        <th class="px-4 py-2">Nome</th>
                        <th class="px-4 py-2">E-mail</th>
                        <th class="px-4 py-2">E-mail confirmado</th>
                        <th class="px-4 py-2">País</th>
                        <th class="px-4 py-2">Cadastrado em</th>
                        <th class="px-4 py-2">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pendingUsers as $user)
                        <tr class="border-t border-orisa-earth/10" wire:key="user-{{ $user->id }}">
                            <td class="px-4 py-2" lang="yo">{{ $user->name }}</td>
                            <td class="px-4 py-2">{{ $user->email }}</td>
                            <td class="px-4 py-2">
                                @if ($user->email_verified_at)
                                    <span class="text-success">Sim</span>
                                @else
                                    <span class="text-orisa-earth">Não</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $user->country }}</td>
                            <td class="px-4 py-2">{{ $user->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">
                                <button wire:click="approve({{ $user->id }})" wire:confirm="Aprovar este cadastro?"
                                    class="rounded bg-success px-3 py-1 text-white hover:bg-success/90">Aprovar</button>
                                <button wire:click="reject({{ $user->id }})" wire:confirm="Rejeitar este cadastro?"
                                    class="rounded bg-danger px-3 py-1 text-white hover:bg-danger/90">Rejeitar</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $pendingUsers->links() }}
        </div>
    @endif
</div>
