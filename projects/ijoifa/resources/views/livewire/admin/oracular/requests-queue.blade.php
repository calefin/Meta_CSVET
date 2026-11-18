<div>
    @if (session()->has('error'))
        <div class="mb-4 rounded-lg bg-danger/10 border border-danger/30 p-4 text-danger text-sm">
            {{ session('error') }}
        </div>
    @endif

    @unless (\App\Services\MercadoPagoService::configured())
        <div class="mb-4 rounded border border-orisa-earth/30 bg-orisa-earth/10 p-3 text-sm text-ebony">
            Mercado Pago não está configurado (falta <code>MERCADO_PAGO_ACCESS_TOKEN</code> no .env). Sem isso, nenhum link de pagamento é gerado — as cobranças ficam pendentes só com confirmação manual.
        </div>
    @endunless

    <h1 class="font-display text-2xl text-ifa-green">Solicitações de consulta</h1>

    <div class="mt-4 rounded-lg border border-orisa-earth/20 bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase text-ebony/50">Preço da consulta</p>
                <p class="font-display text-lg text-ifa-green">
                    R$ {{ number_format($currentPrice->base_price, 2, ',', '.') }}
                    <span class="text-sm font-normal text-ebony/60">+ {{ number_format($currentPrice->fee_percent, 2, ',', '.') }}% taxa = cobrado R$ {{ number_format($currentPrice->chargeAmount(), 2, ',', '.') }}</span>
                </p>
                <p class="mt-1 text-xs text-ebony/50">Toda nova solicitação já gera a cobrança automaticamente nesse valor, sem precisar confirmar manualmente.</p>
            </div>
            <button type="button" wire:click="openPriceForm" class="rounded border border-ifa-green px-3 py-1.5 text-sm text-ifa-green hover:bg-sacred-sand">Alterar preço</button>
        </div>

        @if ($showPriceForm)
            <form wire:submit="savePricing" class="mt-3 flex flex-wrap items-end gap-3 border-t border-orisa-earth/10 pt-3">
                <div>
                    <label class="block text-xs font-medium">Valor base (R$)</label>
                    <input type="number" step="0.01" wire:model="newBasePrice" class="mt-1 w-32 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                    @error('newBasePrice') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium">Taxa (%)</label>
                    <input type="number" step="0.01" wire:model="newFeePercent" class="mt-1 w-24 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                    @error('newFeePercent') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">Salvar novo preço</button>
                <button type="button" wire:click="$set('showPriceForm', false)" class="text-sm text-ebony/60 hover:underline">cancelar</button>
            </form>
        @endif

        @if ($priceHistory->count() > 1)
            <details class="mt-3 text-xs">
                <summary class="cursor-pointer select-none text-ifa-green hover:underline">histórico de alterações ({{ $priceHistory->count() }})</summary>
                <ul class="mt-2 space-y-1 text-ebony/60">
                    @foreach ($priceHistory as $entry)
                        <li>{{ $entry->created_at->format('d/m/Y H:i') }} — R$ {{ number_format($entry->base_price, 2, ',', '.') }} + {{ number_format($entry->fee_percent, 2, ',', '.') }}% — por {{ $entry->admin->name ?? '—' }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>

    <select wire:model.live="statusFilter" class="mt-4 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
        <option value="">Todos os status</option>
        <option value="pending">Cobrança automática falhou</option>
        <option value="awaiting_payment">Aguardando pagamento</option>
        <option value="paid">Paga — aguardando atendimento</option>
        <option value="attended">Atendida</option>
        <option value="cancelled">Cancelada</option>
    </select>

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Consulente</th>
                    <th class="px-4 py-2">Modalidade</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Solicitado em</th>
                    <th class="px-4 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($requests as $request)
                    <tr class="border-t border-orisa-earth/10" wire:key="request-{{ $request->id }}">
                        <td class="px-4 py-2" lang="yo">{{ $request->user->name }}</td>
                        <td class="px-4 py-2">{{ $request->modality === 'online' ? 'Online' : 'Presencial' }}</td>
                        <td class="px-4 py-2">
                            @switch($request->status)
                                @case('pending') Cobrança automática falhou @break
                                @case('awaiting_payment') Aguardando pagamento @break
                                @case('paid') Paga — aguardando atendimento @break
                                @case('attended') Atendida @break
                                @case('cancelled') Cancelada @break
                            @endswitch
                        </td>
                        <td class="px-4 py-2">{{ $request->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2 space-x-2">
                            @if ($request->status === 'pending')
                                @if ($confirmingId === $request->id)
                                    <form wire:submit="confirm" class="inline-flex items-center gap-1">
                                        <input type="number" step="0.01" wire:model="confirmAmount" placeholder="Valor R$" class="w-24 rounded border border-orisa-earth/30 px-2 py-1 text-xs">
                                        <button type="submit" class="text-xs text-ifa-green hover:underline">confirmar</button>
                                        <button type="button" wire:click="$set('confirmingId', null)" class="text-xs text-ebony/50 hover:underline">x</button>
                                    </form>
                                    @error('confirmAmount') <span class="text-xs text-danger">{{ $message }}</span> @enderror
                                @else
                                    <button wire:click="showConfirmForm({{ $request->id }})" class="text-ifa-green hover:underline">Confirmar</button>
                                @endif
                            @endif
                            @if ($request->status === 'awaiting_payment')
                                <button wire:click="markPaid({{ $request->id }})" wire:confirm="Confirmar que o pagamento foi recebido?" class="text-success hover:underline">Marcar pago</button>

                                @php
                                    $payment = \App\Models\Payment::where('payable_type', \App\Models\ConsultationRequest::class)
                                        ->where('payable_id', $request->id)
                                        ->where('status', 'pending')
                                        ->first();
                                    $cleanPhone = preg_replace('/\D/', '', $request->user->phone);
                                    if (strlen($cleanPhone) === 11 && !str_starts_with($cleanPhone, '55')) {
                                        $cleanPhone = '55' . $cleanPhone;
                                    }
                                @endphp

                                @if ($payment && $payment->checkout_url)
                                    <span class="text-ebony/30">|</span>
                                    <button 
                                        type="button" 
                                        x-data="{ copied: false }" 
                                        x-on:click="
                                            navigator.clipboard.writeText('{{ $payment->checkout_url }}');
                                            copied = true;
                                            setTimeout(() => copied = false, 2000);
                                        " 
                                        class="text-orun-blue hover:underline"
                                    >
                                        <span x-show="!copied">Copiar Link</span>
                                        <span x-show="copied" class="text-success font-semibold">Copiado!</span>
                                    </button>
                                    <span class="text-ebony/30">|</span>
                                    <a 
                                        href="https://wa.me/{{ $cleanPhone }}?text={{ urlencode('Olá, aqui está o link para o pagamento da sua consulta oracular: ' . $payment->checkout_url) }}" 
                                        target="_blank" 
                                        rel="noopener" 
                                        class="inline-flex items-center gap-0.5 text-success hover:underline font-semibold"
                                    >
                                        WhatsApp
                                    </a>
                                @endif
                            @endif
                            @if ($request->status === 'paid' && ! $request->consultation)
                                <a href="{{ route('admin.oracular.show', $request) }}" class="text-orun-blue hover:underline">Registrar consulta</a>
                            @elseif ($request->consultation && $request->consultation->admin_id === auth()->id())
                                <a href="{{ route('admin.oracular.show', $request) }}" class="text-orun-blue hover:underline">Ver consulta</a>
                            @elseif ($request->consultation)
                                <span class="text-ebony/40 italic text-xs" title="Consulta já registrada por outro administrador">
                                    🔒 Atendida por {{ $request->consultation->admin->name ?? 'outro administrador' }}
                                </span>
                            @endif
                            @if (! in_array($request->status, ['attended', 'cancelled']))
                                <button wire:click="cancel({{ $request->id }})" wire:confirm="Cancelar esta solicitação?" class="text-danger hover:underline">Cancelar</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>
</div>
