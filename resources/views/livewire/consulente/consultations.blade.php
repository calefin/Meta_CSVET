<div>
    @if (session()->has('error'))
        <div class="mb-4 rounded-lg bg-danger/10 border border-danger/30 p-4 text-danger text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-orun-blue">Minhas consultas</h1>
        <button wire:click="openRequestForm" class="rounded bg-orun-blue px-4 py-2 text-white hover:bg-orun-blue/90">
            Solicitar consulta
        </button>
    </div>

    <div class="mt-6 space-y-4">
        @forelse ($requests as $request)
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-4" wire:key="request-{{ $request->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm text-ebony/60">
                        {{ $request->created_at->format('d/m/Y H:i') }} — {{ $request->modality === 'online' ? 'Online' : 'Presencial' }}
                    </span>
                    <span class="rounded-full px-3 py-1 text-xs font-medium
                        @class([
                            'bg-sacred-sand text-ebony/70' => $request->status === 'pending',
                            'bg-orun-blue/10 text-orun-blue' => $request->status === 'awaiting_payment',
                            'bg-success/10 text-success' => in_array($request->status, ['paid', 'attended']),
                            'bg-danger/10 text-danger' => $request->status === 'cancelled',
                        ])">
                        @switch($request->status)
                            @case('pending') Aguardando confirmacao @break
                            @case('awaiting_payment') Aguardando pagamento @break
                            @case('paid') Paga — aguardando atendimento @break
                            @case('attended') Atendida @break
                            @case('cancelled') Cancelada @break
                        @endswitch
                    </span>
                </div>

                @if ($request->status === 'awaiting_payment')
                    @php
                        $pendingPayment = \App\Models\Payment::where('payable_type', \App\Models\ConsultationRequest::class)
                            ->where('payable_id', $request->id)
                            ->where('status', 'pending')
                            ->latest()
                            ->first();
                    @endphp
                    @if ($pendingPayment?->checkout_url)
                        <a href="{{ $pendingPayment->checkout_url }}" target="_blank" class="mt-2 inline-block rounded bg-orun-blue px-3 py-1.5 text-xs text-white hover:bg-orun-blue/90">
                            Pagar agora — R$ {{ number_format($pendingPayment->amount, 2, ',', '.') }}
                        </a>
                    @endif
                @endif

                <p class="mt-2 text-sm text-ebony/80">{{ $request->description }}</p>
                @if ($request->attachment_path)
                    <a href="{{ route('consultation-requests.attachment', $request) }}" target="_blank" class="text-sm text-orun-blue hover:underline">Ver meu anexo</a>
                @endif

                @if ($request->consultation)
                    @php $consultation = $request->consultation; @endphp
                    <div class="mt-4 rounded border border-orun-blue/30 bg-orun-blue/5 p-4">
                        <h3 class="font-display text-lg text-orun-blue">Resultado da consulta</h3>
                        <p class="mt-1 text-sm"><strong>Odù principal:</strong> <span lang="yo">{{ $consultation->oduMain->name }}</span></p>
                        <p class="mt-1 text-sm">
                            <strong>Classificacao:</strong>
                            {{ $consultation->ire_ibi === 'ire' ? 'Ire' : 'Ibi' }} — <span lang="yo">{{ $consultation->ireIbiType->name }}</span>
                        </p>
                        @if ($consultation->general_guidance)
                            <p class="mt-2 text-sm text-ebony/80">{{ $consultation->general_guidance }}</p>
                        @endif

                        @if ($consultation->recommendedWorks->isNotEmpty())
                            <h4 class="mt-3 font-medium">Trabalhos recomendados</h4>
                            <ul class="mt-1 space-y-2 text-sm">
                                @foreach ($consultation->recommendedWorks as $work)
                                    @php $budget = $work->budgets->whereIn('status', ['sent', 'approved'])->last(); @endphp
                                    <li class="rounded border border-orisa-earth/10 p-2" wire:key="cw-{{ $work->id }}">
                                        <div class="flex items-center justify-between">
                                            <span lang="yo">{{ $work->ritualWork->name }}</span>
                                            <span class="text-xs text-ebony/60">
                                                @switch($work->status)
                                                    @case('recommended') Recomendado @break
                                                    @case('budget_requested') Orçamento solicitado @break
                                                    @case('approved') Aprovado / pagamento confirmado @break
                                                    @case('in_progress') Em andamento @break
                                                    @case('completed') Concluido @break
                                                    @case('cancelled') Cancelado @break
                                                @endswitch
                                            </span>
                                        </div>

                                        @if ($work->status === 'recommended')
                                            <button wire:click="requestBudget({{ $work->id }})" class="mt-2 text-xs text-orun-blue hover:underline">
                                                Solicitar orcamento
                                            </button>
                                        @endif

                                        @if ($budget)
                                            @if ($budget->status === 'sent' && $budget->hasMultipleOptions())
                                                <div class="mt-2 space-y-2">
                                                    <p class="text-xs font-medium text-ebony/70">Escolha uma das opções:</p>
                                                    @foreach ($budget->filledOptions() as $optionIndex => $option)
                                                        <button type="button" wire:click="selectBudgetOption({{ $budget->id }}, {{ $optionIndex }})"
                                                            class="block w-full rounded border p-2 text-left text-xs transition-colors {{ ($selectedBudgetOption[$budget->id] ?? null) === $optionIndex ? 'border-orun-blue bg-orun-blue/10' : 'border-orisa-earth/20 hover:border-orun-blue/40' }}">
                                                            <span class="font-semibold">Opção {{ $optionIndex + 1 }} — R$ {{ number_format($option['total'], 2, ',', '.') }}</span>
                                                            @if (!empty($option['comment']))
                                                                <span class="block text-ebony/60 mt-0.5">{{ $option['comment'] }}</span>
                                                            @endif
                                                        </button>
                                                    @endforeach
                                                    @error('approveBudget')
                                                        <p class="text-danger">{{ $message }}</p>
                                                    @enderror
                                                    <button wire:click="approveBudget({{ $budget->id }})" wire:confirm="Aprovar a opção escolhida e solicitar a cobranca?" class="text-success hover:underline text-xs font-medium">
                                                        Aprovar opção escolhida e solicitar cobranca
                                                    </button>
                                                </div>
                                            @else
                                                <div class="mt-2 flex items-center gap-3 text-xs">
                                                    <span>Total: R$ {{ number_format($budget->total, 2, ',', '.') }}</span>
                                                    <a href="{{ route('budgets.pdf', $budget) }}" target="_blank" class="text-orun-blue hover:underline">Ver PDF</a>
                                                    @if ($budget->status === 'sent')
                                                        <button wire:click="approveBudget({{ $budget->id }})" wire:confirm="Aprovar este orcamento e solicitar a cobranca?" class="text-success hover:underline">
                                                            Aprovar e solicitar cobranca
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif

                                            @if ($budget->status === 'approved')
                                                @php
                                                    $workPayment = \App\Models\Payment::where('payable_type', \App\Models\RecommendedWork::class)
                                                        ->where('payable_id', $work->id)
                                                        ->where('status', 'pending')
                                                        ->latest()
                                                        ->first();
                                                @endphp
                                                @if ($workPayment?->checkout_url)
                                                    <a href="{{ $workPayment->checkout_url }}" target="_blank" class="mt-2 inline-block rounded bg-orun-blue px-3 py-1.5 text-xs text-white hover:bg-orun-blue/90">
                                                        Pagar agora — R$ {{ number_format($workPayment->amount, 2, ',', '.') }}
                                                    </a>
                                                @endif
                                            @endif
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-ebony/60">Nenhuma solicitacao de consulta ainda.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>

    @if ($showRequestForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-orun-blue">Solicitar consulta</h2>

                <form wire:submit="submitRequest" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Modalidade</label>
                        <select wire:model="modality" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            <option value="online">Online</option>
                            <option value="presential">Presencial</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Descreva sua situacao</label>
                        <textarea wire:model="description" rows="4" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
                        @error('description') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Anexo (opcional)</label>
                        <input type="file" wire:model="attachment" class="mt-1 w-full text-sm">
                        @error('attachment') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showRequestForm', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-orun-blue px-4 py-2 text-white hover:bg-orun-blue/90">Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
