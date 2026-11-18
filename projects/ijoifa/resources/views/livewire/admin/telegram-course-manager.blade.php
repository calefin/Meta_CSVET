<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-ebony">Gestão do Curso Telegram</h1>
            <p class="text-sm text-ebony/70">
                Inscritos, mensalidades (R$ 180,00), presença nos encontros presenciais, transmissão bot e emissão de recibos.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="generateUniqueInviteLink"
                class="inline-flex items-center gap-1.5 rounded-lg border border-orun-blue bg-orun-blue/10 px-3 py-2 text-xs font-semibold text-orun-blue hover:bg-orun-blue/20"
                title="Gerar Link de Convite Único de 1 Uso (24h) via API do Telegram"
            >
                🔑 Link Convite Único Bot
            </button>
            <a href="{{ route('public-telegram-course-registration') }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-ifa-green bg-ifa-green/10 px-3 py-2 text-xs font-semibold text-ifa-green hover:bg-ifa-green/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                Ver Formulário Público
            </a>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    @if (session()->has('info'))
        <div class="rounded-lg border border-blue-300 bg-blue-50 p-4 text-sm text-blue-800">
            {{ session('info') }}
        </div>
    @endif

    <!-- Cards de Métricas -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-orisa-earth/15 bg-white p-5 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-ebony/60">Total de Inscritos</span>
            <div class="mt-2 text-2xl font-bold text-ebony">{{ $totalSubscribers }}</div>
        </div>

        <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-800">Ativos / Adimplentes</span>
            <div class="mt-2 text-2xl font-bold text-emerald-700">{{ $activeCount }}</div>
        </div>

        <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-5 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-rose-800">Atrasados / Inadimplentes</span>
            <div class="mt-2 text-2xl font-bold text-rose-700">{{ $overdueCount }}</div>
        </div>

        <div class="rounded-xl border border-orisa-earth/15 bg-white p-5 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-ebony/60">Receita Mensal Esperada</span>
            <div class="mt-2 text-2xl font-bold text-ifa-green">R$ {{ number_format($estimatedMonthlyRevenue, 2, ',', '.') }}</div>
        </div>
    </div>

    <!-- Modal / Caixa de Link de Convite Bot Gerado -->
    @if ($generatedInviteLink)
        <div class="rounded-xl border border-orun-blue/30 bg-orun-blue/10 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-orun-blue">Link de Convite Temporário (1 Uso / Expira em 24h)</h3>
                <button type="button" wire:click="$set('generatedInviteLink', null)" class="text-xs text-orun-blue underline">Fechar</button>
            </div>
            @if ($generatedInviteLinkUser)
                <p class="mt-1 text-sm text-orun-blue">Aluno: <strong>{{ $generatedInviteLinkUser }}</strong></p>
            @endif
            <p class="mt-1 text-xs text-ebony/70">Envie este link para o aluno recém-adimplente entrar no grupo fechado do Telegram:</p>
            <div class="mt-3 flex items-center gap-2" x-data="{ copied: false }">
                <input type="text" readonly value="{{ $generatedInviteLink }}" class="w-full rounded-lg border border-orun-blue/30 bg-white px-3 py-2 text-sm font-mono text-ebony" />
                <button
                    type="button"
                    x-on:click="navigator.clipboard.writeText('{{ $generatedInviteLink }}'); copied = true; setTimeout(() => copied = false, 2500)"
                    class="flex-shrink-0 rounded-lg bg-orun-blue px-4 py-2 text-sm font-semibold text-white hover:bg-orun-blue/90"
                >
                    <span x-show="!copied">Copiar Link</span>
                    <span x-show="copied" x-cloak>Copiado!</span>
                </button>
            </div>
        </div>
    @endif

    <!-- Modal / Caixa de Link MP Gerado -->
    @if ($generatedLink)
        <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-emerald-900">Link de Pagamento Mercado Pago Gerado</h3>
                <button type="button" wire:click="$set('generatedLink', null)" class="text-xs text-emerald-700 underline hover:text-emerald-900">Fechar</button>
            </div>
            <p class="mt-1 text-sm text-emerald-800">Aluno: <strong>{{ $generatedLinkUser }}</strong></p>
            <div class="mt-3 flex items-center gap-2" x-data="{ copied: false }">
                <input type="text" readonly value="{{ $generatedLink }}" class="w-full rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm font-mono text-ebony" />
                <button
                    type="button"
                    x-on:click="navigator.clipboard.writeText('{{ $generatedLink }}'); copied = true; setTimeout(() => copied = false, 2500)"
                    class="flex-shrink-0 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                >
                    <span x-show="!copied">Copiar Link</span>
                    <span x-show="copied" x-cloak>Copiado!</span>
                </button>
            </div>
        </div>
    @endif

    <!-- Navegação de Abas -->
    <div class="border-b border-orisa-earth/15">
        <nav class="-mb-px flex gap-6 overflow-x-auto text-sm font-semibold">
            <button
                type="button"
                wire:click="$set('activeTab', 'subscribers')"
                class="pb-3 border-b-2 transition {{ $activeTab === 'subscribers' ? 'border-ifa-green text-ifa-green' : 'border-transparent text-ebony/60 hover:text-ebony' }}"
            >
                👥 Inscritos e Mensalidades
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'attendance')"
                class="pb-3 border-b-2 transition {{ $activeTab === 'attendance' ? 'border-ifa-green text-ifa-green' : 'border-transparent text-ebony/60 hover:text-ebony' }}"
            >
                📅 Frequência Encontros Presenciais
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'lessons')"
                class="pb-3 border-b-2 transition {{ $activeTab === 'lessons' ? 'border-ifa-green text-ifa-green' : 'border-transparent text-ebony/60 hover:text-ebony' }}"
            >
                📖 Materiais e Aulas
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'broadcast')"
                class="pb-3 border-b-2 transition {{ $activeTab === 'broadcast' ? 'border-ifa-green text-ifa-green' : 'border-transparent text-ebony/60 hover:text-ebony' }}"
            >
                📢 Transmissão Telegram Bot
            </button>
        </nav>
    </div>

    @if ($activeTab === 'subscribers')
        <!-- TAB 1: INSCRITOS E MENSALIDADES -->
        <div class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="w-full sm:max-w-xs">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Buscar por nome, @telegram ou email..."
                        class="w-full rounded-lg border border-orisa-earth/25 px-3.5 py-2 text-sm focus:border-ifa-green focus:outline-none"
                    />
                </div>

                <div class="flex items-center gap-2 overflow-x-auto">
                    <button
                        type="button"
                        wire:click="$set('statusFilter', 'all')"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $statusFilter === 'all' ? 'bg-ifa-green text-white' : 'bg-white border border-orisa-earth/20 text-ebony/70 hover:bg-sacred-sand' }}"
                    >
                        Todos ({{ $totalSubscribers }})
                    </button>
                    <button
                        type="button"
                        wire:click="$set('statusFilter', 'active')"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $statusFilter === 'active' ? 'bg-emerald-700 text-white' : 'bg-white border border-emerald-200 text-emerald-800 hover:bg-emerald-50' }}"
                    >
                        Ativos ({{ $activeCount }})
                    </button>
                    <button
                        type="button"
                        wire:click="$set('statusFilter', 'overdue')"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $statusFilter === 'overdue' ? 'bg-rose-700 text-white' : 'bg-white border border-rose-200 text-rose-800 hover:bg-rose-50' }}"
                    >
                        Atrasados ({{ $overdueCount }})
                    </button>
                    <button
                        type="button"
                        wire:click="$set('statusFilter', 'pending_payment')"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $statusFilter === 'pending_payment' ? 'bg-amber-600 text-white' : 'bg-white border border-amber-200 text-amber-800 hover:bg-amber-50' }}"
                    >
                        Aguardando 1º Pagamento
                    </button>
                    <button
                        type="button"
                        wire:click="$set('statusFilter', 'cancelled')"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $statusFilter === 'cancelled' ? 'bg-slate-700 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-100' }}"
                    >
                        Cancelados
                    </button>
                </div>
            </div>

            <!-- Tabela de Alunos -->
            <div class="overflow-hidden rounded-xl border border-orisa-earth/15 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-ebony">
                        <thead class="bg-sacred-sand/60 text-xs font-semibold uppercase tracking-wider text-ebony/70 border-b border-orisa-earth/15">
                            <tr>
                                <th class="px-4 py-3.5">Aluno</th>
                                <th class="px-4 py-3.5">Telegram</th>
                                <th class="px-4 py-3.5">Status</th>
                                <th class="px-4 py-3.5">Vencimento da Mensalidade</th>
                                <th class="px-4 py-3.5 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-orisa-earth/10">
                            @forelse ($subscriptions as $sub)
                                @php
                                    $lastPayment = $sub->payments()->where('status', 'paid')->latest()->first();
                                @endphp
                                <tr class="hover:bg-sacred-sand/20 transition">
                                    <td class="px-4 py-3.5">
                                        <div class="font-medium text-ebony">{{ $sub->full_name }}</div>
                                        <div class="text-xs text-ebony/60">{{ $sub->email }} @if($sub->phone) • {{ $sub->phone }} @endif</div>
                                        @if ($sub->user)
                                            <div class="mt-0.5">
                                                <span class="inline-block rounded bg-ifa-green/10 px-1.5 py-0.5 text-[10px] font-semibold text-ifa-green">Conta vinculada #{{ $sub->user->id }}</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <a href="https://t.me/{{ ltrim($sub->telegram_username, '@') }}" target="_blank" class="font-mono text-xs font-semibold text-ifa-green hover:underline">
                                            {{ $sub->telegram_username }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $sub->statusBadgeClass() }}">
                                            {{ $sub->statusLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 font-mono text-xs">
                                        @if ($sub->expires_at)
                                            <span class="{{ $sub->isOverdue() ? 'text-rose-700 font-bold' : 'text-ebony/80' }}">
                                                {{ $sub->expires_at->format('d/m/Y') }}
                                            </span>
                                        @else
                                            <span class="text-ebony/40">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <!-- Baixa Manual -->
                                            <button
                                                type="button"
                                                wire:click="markAsPaid({{ $sub->id }})"
                                                wire:confirm="Confirmar pagamento manual de R$ 180,00 para {{ $sub->full_name }}? Vencimento passa para o dia 15 do próximo mês."
                                                title="Registrar pagamento manual (Pix/Dinheiro) — vencimento vai para o dia 15 do próximo mês"
                                                class="rounded border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 hover:bg-emerald-100"
                                            >
                                                Pago
                                            </button>

                                            <!-- Gerar Link MP -->
                                            <button
                                                type="button"
                                                wire:click="generatePaymentLink({{ $sub->id }})"
                                                title="Gerar link de cobrança do Mercado Pago (R$ 180,00)"
                                                class="rounded border border-ifa-green/30 bg-ifa-green/10 px-2.5 py-1 text-xs font-semibold text-ifa-green hover:bg-ifa-green/20"
                                            >
                                                Link MP
                                            </button>

                                            <!-- Gerar Convite do Grupo -->
                                            <button
                                                type="button"
                                                wire:click="generateInviteLinkForSub({{ $sub->id }})"
                                                title="Gerar link de convite exclusivo do grupo do Telegram (1 uso, 24h) para este aluno"
                                                class="rounded border border-orun-blue/30 bg-orun-blue/10 px-2.5 py-1 text-xs font-semibold text-orun-blue hover:bg-orun-blue/20"
                                            >
                                                Convite
                                            </button>

                                            <!-- Enviar Lembrete por E-mail -->
                                            <button
                                                type="button"
                                                wire:click="sendReminderEmail({{ $sub->id }})"
                                                wire:confirm="Enviar e-mail de lembrete com link do Mercado Pago para {{ $sub->email }}?"
                                                title="Enviar e-mail de lembrete com link de pagamento"
                                                class="rounded border border-sky-300 bg-sky-50 px-2 py-1 text-xs font-semibold text-sky-800 hover:bg-sky-100"
                                            >
                                                Lembrete
                                            </button>

                                            @if ($lastPayment)
                                                <!-- Download Recibo PDF -->
                                                <a
                                                    href="{{ route('telegram-subscriptions.receipt', $lastPayment->id) }}"
                                                    target="_blank"
                                                    title="Baixar Recibo PDF da última mensalidade"
                                                    class="rounded border border-orisa-earth/20 bg-white px-2 py-1 text-xs font-semibold text-ebony/80 hover:bg-sacred-sand"
                                                >
                                                    📄 Recibo
                                                </a>
                                            @endif

                                            <!-- Observações -->
                                            <button
                                                type="button"
                                                wire:click="editNotes({{ $sub->id }})"
                                                title="Anotações internas"
                                                class="rounded border border-orisa-earth/20 bg-white px-2 py-1 text-xs text-ebony/70 hover:bg-sacred-sand"
                                            >
                                                Nota
                                            </button>

                                            @if ($sub->isOverdue())
                                                <button
                                                    type="button"
                                                    wire:click="removeFromGroup({{ $sub->id }})"
                                                    wire:confirm="Remover {{ $sub->full_name }} do grupo do Telegram por falta de pagamento? A pessoa poderá voltar com um novo link de convite assim que pagar."
                                                    title="{{ $sub->telegram_user_id ? 'Remover do grupo do Telegram (mensalidade em atraso)' : 'ID do Telegram ainda não identificado — peça pra pessoa mandar uma mensagem pro bot' }}"
                                                    class="rounded border border-amber-300 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                                                >
                                                    Remover do grupo
                                                </button>
                                            @endif

                                            @if ($sub->status !== 'cancelled')
                                                <button
                                                    type="button"
                                                    wire:click="cancelSubscription({{ $sub->id }})"
                                                    wire:confirm="Marcar a inscrição de {{ $sub->full_name }} como cancelada?"
                                                    title="Cancelar inscrição"
                                                    class="rounded text-xs text-rose-600 hover:text-rose-800"
                                                >
                                                    Cancelar
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                @if ($editingSubId === $sub->id)
                                    <tr class="bg-sacred-sand/40">
                                        <td colspan="5" class="px-4 py-3">
                                            <div class="space-y-2">
                                                <label class="block text-xs font-semibold text-ebony">Anotações Internas para {{ $sub->full_name }}</label>
                                                <textarea wire:model.defer="notes" class="w-full rounded-lg border border-orisa-earth/30 p-2 text-xs text-ebony" rows="2" placeholder="Escreva observações ou recados..."></textarea>
                                                <div class="flex items-center gap-2">
                                                    <button type="button" wire:click="saveNotes({{ $sub->id }})" class="rounded bg-ifa-green px-3 py-1 text-xs font-semibold text-white">Salvar</button>
                                                    <button type="button" wire:click="cancelEditNotes" class="text-xs text-ebony/60 hover:underline">Cancelar</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-ebony/60">
                                        Nenhum inscrito encontrado com os filtros selecionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($subscriptions->hasPages())
                    <div class="border-t border-orisa-earth/15 p-4">
                        {{ $subscriptions->links() }}
                    </div>
                @endif
            </div>

            <!-- Gerador do Relatório Mensal para o Telegram -->
            <div class="rounded-xl border border-orisa-earth/15 bg-white p-6 shadow-sm" x-data="{ reportCopied: false }">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-display text-lg font-bold text-ebony">Relatório Mensal Formatado para o Telegram</h2>
                        <p class="text-xs text-ebony/60">Copie o relatório pronto com 1 clique e envie no grupo do Telegram para controle de adimplência.</p>
                    </div>
                    <button
                        type="button"
                        x-on:click="navigator.clipboard.writeText($refs.telegramReportText.value); reportCopied = true; setTimeout(() => reportCopied = false, 3000)"
                        class="inline-flex items-center gap-2 rounded-lg bg-ifa-green px-4 py-2 text-sm font-semibold text-white hover:bg-ifa-green/90"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                        </svg>
                        <span x-show="!reportCopied">Copiar Relatório Formatado</span>
                        <span x-show="reportCopied" x-cloak>Copiado para Transferência!</span>
                    </button>
                </div>

                <div class="mt-4">
                    <textarea
                        x-ref="telegramReportText"
                        readonly
                        rows="8"
                        class="w-full rounded-lg border border-orisa-earth/25 bg-sacred-sand/20 p-3.5 font-mono text-xs text-ebony focus:outline-none"
                    >{{ $formattedReport }}</textarea>
                </div>
            </div>
        </div>
    @elseif ($activeTab === 'attendance')
        <!-- TAB 2: FREQUÊNCIA ENCONTROS PRESENCIAIS -->
        <div class="rounded-xl border border-orisa-earth/15 bg-white p-6 shadow-sm space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-display text-lg font-bold text-ebony">Controle de Frequência — Encontro Presencial Mensal</h2>
                    <p class="text-xs text-ebony/60">Marque a presença dos alunos ativos na data da prática presencial.</p>
                </div>
                <div class="flex items-center gap-2">
                    <label for="meetingDate" class="text-xs font-semibold text-ebony">Data do Encontro:</label>
                    <input
                        type="date"
                        id="meetingDate"
                        wire:model.live="meetingDate"
                        wire:change="loadAttendanceStates"
                        class="rounded-lg border border-orisa-earth/30 px-3 py-1.5 text-xs text-ebony focus:border-ifa-green focus:outline-none"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-ebony">
                    <thead class="bg-sacred-sand/60 text-xs font-semibold uppercase tracking-wider text-ebony/70 border-b border-orisa-earth/15">
                        <tr>
                            <th class="px-4 py-3">Aluno</th>
                            <th class="px-4 py-3">Telegram</th>
                            <th class="px-4 py-3">Status Assinatura</th>
                            <th class="px-4 py-3 text-center">Frequência</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-orisa-earth/10">
                        @forelse ($activeSubscribers as $student)
                            @php
                                $isPresent = $attendanceStates[$student->id] ?? true;
                            @endphp
                            <tr class="hover:bg-sacred-sand/20 transition">
                                <td class="px-4 py-3 font-medium">{{ $student->full_name }}</td>
                                <td class="px-4 py-3 text-xs font-mono text-ifa-green">{{ $student->telegram_username }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $student->statusBadgeClass() }}">
                                        {{ $student->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button
                                        type="button"
                                        wire:click="toggleAttendance({{ $student->id }})"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $isPresent ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300' }}"
                                    >
                                        @if ($isPresent)
                                            <span>✓ Presente</span>
                                        @else
                                            <span>✕ Faltou</span>
                                        @endif
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-xs text-ebony/60">
                                    Nenhum aluno ativo encontrado para registrar presença.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($activeTab === 'lessons')
        <!-- TAB 3: MATERIAIS E AULAS DO CURSO -->
        <div class="grid gap-6 md:grid-cols-12">
            <!-- Form Novo Conteúdo -->
            <div class="md:col-span-5">
                <form wire:submit.prevent="saveLesson" class="rounded-xl border border-orisa-earth/15 bg-white p-6 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-lg font-bold text-ebony">{{ $editingLessonId ? 'Editar Material / Aula' : 'Publicar Material / Aula' }}</h2>
                        @if ($editingLessonId)
                            <button type="button" wire:click="cancelEditLesson" class="text-xs text-ebony/50 hover:underline">cancelar edição</button>
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ebony">Tipo de Conteúdo *</label>
                        <select wire:model.defer="lessonType" class="mt-1 w-full rounded-lg border border-orisa-earth/30 px-3 py-2 text-xs text-ebony focus:border-ifa-green">
                            <option value="weekly_telegram">Aula Semanal Telegram (Áudio / Resumo)</option>
                            <option value="presential_meeting">Encontro Presencial (Instruções / Check-list de Materiais)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ebony">Título do Conteúdo / Aula *</label>
                        <input type="text" wire:model.defer="lessonTitle" placeholder="Ex: Aula 04 — Fundamentos de Orogbo" class="mt-1 w-full rounded-lg border border-orisa-earth/30 px-3 py-2 text-xs text-ebony focus:border-ifa-green" />
                        @error('lessonTitle') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ebony">Descrição / Resumo Teórico</label>
                        <textarea wire:model.defer="lessonDescription" rows="3" placeholder="Resumo das orientações ministradas..." class="mt-1 w-full rounded-lg border border-orisa-earth/30 p-2 text-xs text-ebony focus:border-ifa-green"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ebony">Lista de Materiais Práticos Necessários (Checklist)</label>
                        <textarea wire:model.defer="lessonChecklist" rows="3" placeholder="Ex: 1 Garrafa de Gin, 4 Nozes de Obi, Tecido Branco..." class="mt-1 w-full rounded-lg border border-orisa-earth/30 p-2 text-xs text-ebony focus:border-ifa-green"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ebony">Resumo / Aula em Texto (opcional)</label>
                        <p class="mt-0.5 text-[11px] text-ebony/50">O conteúdo abaixo vira um PDF protegido (marca d'água com nome/e-mail do aluno + aviso de proibição de cópia), disponível para download apenas por assinantes com mensalidade em dia.</p>
                        <div
                            wire:key="editor-{{ $editingLessonId ?? 'new' }}"
                            wire:ignore
                            x-data="{ editor: null }"
                            x-init="editor = window.IjoifaEditor.mount($refs.editorEl, {
                                initialHtml: @js($lessonContent),
                                onChange: (html) => $wire.set('lessonContent', html),
                            })"
                            class="mt-1"
                        >
                            <div x-ref="editorEl" class="bg-white" style="min-height: 10rem"></div>
                        </div>
                        @error('lessonContent') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ebony">Apostila em PDF Pronta (opcional)</label>
                        <p class="mt-0.5 text-[11px] text-ebony/50">Envie um PDF já finalizado (ex: livro/apostila). Cada aluno recebe uma cópia com marca d'água estampada nas páginas com seu nome e e-mail.</p>

                        @if ($currentDocumentPath)
                            <div class="mt-1.5 flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                                <span>📄 Apostila já anexada a esta aula</span>
                                <button type="button" wire:click="removeLessonDocument" wire:confirm="Remover a apostila em PDF desta aula?" class="font-semibold text-rose-600 hover:underline">Remover</button>
                            </div>
                        @endif

                        <input type="file" wire:model="lessonDocument" accept="application/pdf" class="mt-1.5 w-full rounded-lg border border-orisa-earth/30 px-3 py-2 text-xs text-ebony focus:border-ifa-green" />
                        <div wire:loading wire:target="lessonDocument" class="mt-1 text-[11px] text-ebony/50">Enviando arquivo...</div>
                        @error('lessonDocument') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="w-full rounded-lg bg-ifa-green py-2.5 text-xs font-bold text-white hover:bg-ifa-green/90">
                        {{ $editingLessonId ? 'Salvar Alterações' : 'Publicar Conteúdo no Painel' }}
                    </button>
                </form>
            </div>

            <!-- Lista de Aulas e Materiais -->
            <div class="md:col-span-7">
                <div class="rounded-xl border border-orisa-earth/15 bg-white p-6 shadow-sm">
                    <h2 class="font-display text-lg font-bold text-ebony mb-4">Conteúdos Publicados ({{ $lessons->count() }})</h2>

                    <div class="space-y-4">
                        @forelse ($lessons as $lesson)
                            <div class="rounded-lg border border-orisa-earth/15 bg-sacred-sand/20 p-4 relative">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <span class="inline-block rounded px-2 py-0.5 text-[10px] font-bold uppercase {{ $lesson->type === 'presential_meeting' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                            {{ $lesson->type === 'presential_meeting' ? 'Encontro Presencial' : 'Aula Telegram' }}
                                        </span>
                                        <h3 class="font-bold text-sm text-ebony mt-1">{{ $lesson->title }}</h3>
                                        <div class="text-[11px] text-ebony/60">Publicado em {{ $lesson->published_at?->format('d/m/Y H:i') }}</div>
                                        @if ($lesson->content || $lesson->document_path)
                                            <a href="{{ route('telegram-course.lessons.material-pdf', $lesson) }}" target="_blank" class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-ifa-green hover:underline">
                                                📄 Ver PDF (com sua marca d'água de admin)
                                            </a>
                                        @endif
                                    </div>

                                    <span class="space-x-3">
                                        <button
                                            type="button"
                                            wire:click="editLesson({{ $lesson->id }})"
                                            class="text-xs text-orun-blue hover:underline font-semibold"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="deleteLesson({{ $lesson->id }})"
                                            wire:confirm="Remover esta aula?"
                                            class="text-xs text-rose-600 hover:text-rose-800 font-semibold"
                                        >
                                            Excluir
                                        </button>
                                    </span>
                                </div>

                                @if ($lesson->description)
                                    <p class="mt-2 text-xs text-ebony/80 leading-relaxed">{{ $lesson->description }}</p>
                                @endif

                                @if ($lesson->checklist_materials)
                                    <div class="mt-3 rounded border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-900">
                                        <strong>📦 Insumos / Materiais:</strong>
                                        <p class="mt-0.5 whitespace-pre-line">{{ $lesson->checklist_materials }}</p>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center text-xs text-ebony/60 py-8">
                                Nenhuma aula ou material cadastrado ainda.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @elseif ($activeTab === 'broadcast')
        <!-- TAB 4: TRANSMISSÃO TELEGRAM BOT -->
        <div class="rounded-xl border border-orisa-earth/15 bg-white p-6 shadow-sm max-w-2xl mx-auto">
            <h2 class="font-display text-lg font-bold text-ebony">Disparar Comunicado via Bot do Telegram</h2>
            <p class="text-xs text-ebony/60 mt-1">
                Envie uma mensagem instantânea diretamente para o chat do administrador ou grupo de avisos via Telegram Bot API.
            </p>

            <form wire:submit.prevent="sendBroadcast" class="mt-4 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-ebony">Texto da Mensagem *</label>
                    <textarea
                        wire:model.defer="broadcastText"
                        rows="6"
                        placeholder="Digite o comunicado litúrgico ou aviso para os alunos..."
                        class="mt-1 w-full rounded-lg border border-orisa-earth/30 p-3 text-xs text-ebony focus:border-ifa-green"
                    ></textarea>
                    @error('broadcastText') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-lg bg-ifa-green px-5 py-2.5 text-xs font-bold text-white hover:bg-ifa-green/90 disabled:opacity-50"
                >
                    <span wire:loading.remove>Disparar Mensagem pelo Bot</span>
                    <span wire:loading>Enviando...</span>
                </button>
            </form>
        </div>
    @endif
</div>
