<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl text-ifa-green" lang="yo">{{ $user->name }}</h1>
            <p class="text-sm text-ebony/60">{{ $user->email }} · {{ $user->phone }}</p>
        </div>
        <a href="{{ route('admin.users') }}" class="text-sm text-orun-blue hover:underline">&larr; Voltar para Usuários</a>
    </div>

    {{-- Consultas e trabalhos --}}
    <div class="mt-6 rounded border border-orisa-earth/20 bg-white p-4">
        <h2 class="font-display text-lg text-ifa-green">Consultas e trabalhos</h2>

        @forelse ($consultations as $consultation)
            <div class="mt-3 rounded border border-orisa-earth/10 p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-medium">
                        Consulta de {{ $consultation->consulted_at?->format('d/m/Y') }}
                        @if ($consultation->oduMain)
                            — Odù {{ $consultation->oduMain->name }}
                        @endif
                    </p>
                    <a href="{{ route('admin.oracular.show', $consultation->request_id) }}" class="text-xs text-orun-blue hover:underline">ver consulta</a>
                </div>

                @forelse ($consultation->recommendedWorks as $work)
                    @php $budget = $work->budgets->first(); @endphp
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded bg-sacred-sand/50 px-3 py-2 text-sm">
                        <span>{{ $work->ritualWork?->name ?? 'Trabalho' }}</span>
                        <span class="rounded px-2 py-0.5 text-xs {{ match(\App\Livewire\Admin\Oracular\WorksDashboard::recommendedWorkStatusGroup($work->status)) {
                            'paid' => 'bg-success/10 text-success',
                            'cancelled' => 'bg-danger/10 text-danger',
                            default => 'bg-orun-blue/10 text-orun-blue',
                        } }}">
                            {{ \App\Livewire\Admin\Oracular\WorksDashboard::recommendedWorkStatusLabel($work->status) }}
                        </span>
                        @if ($budget)
                            <span class="text-ebony/60">R$ {{ number_format($budget->total, 2, ',', '.') }}</span>
                        @endif
                        <a href="{{ route('admin.oracular.budget', $work) }}" class="text-xs text-orun-blue hover:underline">ver orçamento</a>
                    </div>
                @empty
                    <p class="mt-2 text-xs text-ebony/50">Nenhum trabalho recomendado nesta consulta.</p>
                @endforelse
            </div>
        @empty
            <p class="mt-2 text-sm text-ebony/50">Nenhuma consulta registrada.</p>
        @endforelse
    </div>

    {{-- Links rápidos --}}
    <div class="mt-6 rounded border border-orisa-earth/20 bg-white p-4">
        <h2 class="font-display text-lg text-ifa-green">Links de pagamento</h2>

        @forelse ($quickPayments as $qp)
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded bg-sacred-sand/50 px-3 py-2 text-sm">
                <span>{{ \App\Livewire\Admin\Oracular\WorksDashboard::quickPaymentTypeLabel($qp->type) }}</span>
                <span class="rounded px-2 py-0.5 text-xs {{ match($qp->status) {
                    'paid' => 'bg-success/10 text-success',
                    'cancelled' => 'bg-danger/10 text-danger',
                    default => 'bg-orun-blue/10 text-orun-blue',
                } }}">
                    {{ \App\Livewire\Admin\Oracular\WorksDashboard::quickPaymentStatusLabel($qp->status) }}
                </span>
                <span class="text-ebony/60">R$ {{ number_format($qp->total, 2, ',', '.') }}</span>
                <span class="text-xs text-ebony/40">{{ $qp->created_at->format('d/m/Y') }}</span>
            </div>
        @empty
            <p class="mt-2 text-sm text-ebony/50">Nenhum link de pagamento gerado.</p>
        @endforelse
    </div>

    {{-- Histórico de cobranças --}}
    <div class="mt-6 rounded border border-orisa-earth/20 bg-white p-4">
        <h2 class="font-display text-lg text-ifa-green">Histórico de cobranças</h2>

        @forelse ($payments as $payment)
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded bg-sacred-sand/50 px-3 py-2 text-sm">
                <span>{{ $payment->description() }}</span>
                <span class="rounded px-2 py-0.5 text-xs {{ match($payment->status) {
                    'paid' => 'bg-success/10 text-success',
                    'pending' => 'bg-orun-blue/10 text-orun-blue',
                    'failed' => 'bg-danger/10 text-danger',
                    default => 'bg-orisa-earth/10 text-orisa-earth',
                } }}">
                    {{ match($payment->status) {
                        'paid' => 'Pago',
                        'pending' => 'Pendente',
                        'failed' => 'Falhou',
                        'refunded' => 'Reembolsado',
                        default => $payment->status,
                    } }}
                </span>
                <span class="text-ebony/60">R$ {{ number_format($payment->amount, 2, ',', '.') }}</span>
                <span class="text-xs text-ebony/40">{{ $payment->paid_at?->format('d/m/Y') ?? $payment->created_at->format('d/m/Y') }}</span>
            </div>
        @empty
            <p class="mt-2 text-sm text-ebony/50">Nenhuma cobrança registrada.</p>
        @endforelse
    </div>

    {{-- Anotações --}}
    <div class="mt-6">
        <livewire:admin.admin-notes :target-user-id="$user->id" />
    </div>
</div>
