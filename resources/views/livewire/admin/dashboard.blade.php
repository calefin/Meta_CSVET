<div>
    <h1 class="font-display text-2xl text-ifa-green">Painel do Administrador</h1>
    <p class="mt-1 text-ebony/70">Bem-vindo de volta, {{ auth()->user()->name }}.</p>

    <div class="mt-4">
        <livewire:shared.events-board />
    </div>

    <!-- Indicators Grid -->
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Total Users Card -->
        <div class="rounded-xl border border-orisa-earth/20 bg-white p-5 shadow-sm transition-all duration-250 hover:shadow-md">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-ebony/40">Total de Usuários</span>
                <span class="rounded bg-ifa-green/10 px-2 py-0.5 text-xs text-ifa-green">Geral</span>
            </div>
            <p class="mt-2 text-3xl font-display text-ebony">{{ $totalUsers }}</p>
            <p class="mt-1 text-xs text-ebony/50">Membros cadastrados no sistema.</p>
        </div>

        <!-- Pending Approvals Card -->
        <a href="{{ route('admin.approvals') }}" class="block rounded-xl border {{ $pendingApprovals > 0 ? 'border-amber-300 bg-amber-50/20' : 'border-orisa-earth/20' }} bg-white p-5 shadow-sm transition-all duration-250 hover:shadow-md hover:scale-[1.01]">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-ebony/40">Cadastros Pendentes</span>
                @if ($pendingApprovals > 0)
                    <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
                @endif
            </div>
            <p class="mt-2 text-3xl font-display {{ $pendingApprovals > 0 ? 'text-amber-700' : 'text-ebony' }}">{{ $pendingApprovals }}</p>
            <p class="mt-1 text-xs text-ebony/50">Aguardando aprovação de perfil.</p>
        </a>

        <!-- Pending Consultations Card -->
        <a href="{{ route('admin.oracular.queue') }}" class="block rounded-xl border {{ $pendingConsultations > 0 ? 'border-orun-blue/30 bg-orun-blue/5' : 'border-orisa-earth/20' }} bg-white p-5 shadow-sm transition-all duration-250 hover:shadow-md hover:scale-[1.01]">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-ebony/40">Solicitações de Consulta</span>
                @if ($pendingConsultations > 0)
                    <span class="h-2 w-2 rounded-full bg-orun-blue animate-pulse"></span>
                @endif
            </div>
            <p class="mt-2 text-3xl font-display {{ $pendingConsultations > 0 ? 'text-orun-blue' : 'text-ebony' }}">{{ $pendingConsultations }}</p>
            <p class="mt-1 text-xs text-ebony/50">Consultas pendentes de atendimento.</p>
        </a>

        <!-- Monthly Revenue Card -->
        <div class="rounded-xl border border-orisa-earth/20 bg-white p-5 shadow-sm transition-all duration-250 hover:shadow-md">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-ebony/40">Faturamento do Mês</span>
                <span class="rounded bg-success/10 px-2 py-0.5 text-xs text-success">Pago</span>
            </div>
            <p class="mt-2 text-2xl font-display text-ebony">R$ {{ number_format($monthlyRevenue, 2, ',', '.') }}</p>
            <p class="mt-1 text-xs text-ebony/50">Consultas e trabalhos pagos no mês.</p>
        </div>
    </div>

    <!-- Calendar and Quick Actions -->
    <div class="mt-6 grid gap-6 md:grid-cols-3">
        <!-- Calendar Widget -->
        <div class="md:col-span-1">
            <x-ose-calendar-widget />
            
            <a href="{{ route('ose-calendar') }}" class="mt-2 inline-flex items-center gap-1 text-xs text-ifa-green hover:underline">
                Visualizar calendário completo &rarr;
            </a>
        </div>

        <!-- Quick Actions Redesigned -->
        <div class="rounded-xl border border-orisa-earth/20 bg-white p-6 shadow-sm md:col-span-2">
            <h2 class="font-display text-lg text-ifa-green mb-1 flex items-center gap-2">
                <span>⚡</span> Ações Principais
            </h2>
            <p class="text-xs text-ebony/60 mb-5">Acesse rapidamente as ferramentas de gestão diária e administrativa.</p>
            
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Diario Liturgico Card (Destaque Celular) -->
                <a href="{{ route('admin.diary') }}" class="group block rounded-lg border-2 border-dashed border-ifa-green bg-gradient-to-br from-ifa-green/5 to-white p-4 transition-all duration-200 hover:border-solid hover:shadow-md hover:scale-[1.01]">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-ifa-green flex items-center gap-1.5">
                            <span>📱</span> Diário Litúrgico (Obi/Rezas)
                        </span>
                        <span class="rounded-full bg-ifa-green/10 px-2 py-0.5 text-[9px] font-bold text-ifa-green uppercase tracking-wide">Uso Diário no Celular 📳</span>
                    </div>
                    <p class="mt-2 text-xs text-ebony/70 leading-relaxed">
                        Acompanhe rezas diárias, obi, orogbo e rituais diários. Desenvolvido para ser usado em tempo real com seu celular durante os ritos.
                    </p>
                </a>

                <!-- Atendimentos Pendentes Card -->
                <a href="{{ route('admin.oracular.pending-fulfillments') }}" class="group block rounded-lg border border-orisa-earth/10 bg-sacred-sand/20 p-4 transition-all duration-200 hover:border-orisa-earth/30 hover:bg-white hover:shadow-sm hover:scale-[1.01]">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-ebony/80 flex items-center gap-1.5">
                            <span>✅</span> Atendimentos Pendentes
                        </span>
                        @if ($pendingFulfillments > 0)
                            <span class="rounded bg-success/10 px-1.5 py-0.5 text-[9px] font-bold text-success">{{ $pendingFulfillments }} pendente(s)</span>
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-ebony/65 leading-relaxed">
                        Trabalhos, links rápidos e consultas já pagos, aguardando você realizar o atendimento.
                    </p>
                </a>

                <!-- Fila Oracular Card -->
                <a href="{{ route('admin.oracular.queue') }}" class="group block rounded-lg border border-orisa-earth/10 bg-sacred-sand/20 p-4 transition-all duration-200 hover:border-orisa-earth/30 hover:bg-white hover:shadow-sm hover:scale-[1.01]">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-ebony/80 flex items-center gap-1.5">
                            <span>🔮</span> Fila Oracular
                        </span>
                        @if ($pendingConsultations > 0)
                            <span class="rounded bg-orun-blue/10 px-1.5 py-0.5 text-[9px] font-bold text-orun-blue">{{ $pendingConsultations }} pendente(s)</span>
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-ebony/65 leading-relaxed">
                        Gerencie e atenda solicitações de consultas e monte orçamentos personalizados de trabalhos litúrgicos.
                    </p>
                </a>

                <!-- Agenda de Consultas Card -->
                <a href="{{ route('admin.agenda') }}" class="group block rounded-lg border border-orisa-earth/10 bg-sacred-sand/20 p-4 transition-all duration-200 hover:border-orisa-earth/30 hover:bg-white hover:shadow-sm hover:scale-[1.01]">
                    <span class="text-sm font-bold text-ebony/80 flex items-center gap-1.5">
                        <span>📅</span> Agenda & Calendário
                    </span>
                    <p class="mt-2 text-xs text-ebony/65 leading-relaxed">
                        Consulte os agendamentos confirmados e organize os horários de atendimento do terreiro.
                    </p>
                </a>

                <!-- Financeiro & Preços Card -->
                <a href="{{ route('admin.finance.index') }}" class="group block rounded-lg border border-orisa-earth/10 bg-sacred-sand/20 p-4 transition-all duration-200 hover:border-orisa-earth/30 hover:bg-white hover:shadow-sm hover:scale-[1.01]">
                    <span class="text-sm font-bold text-ebony/80 flex items-center gap-1.5">
                        <span>📊</span> Financeiro & Preços
                    </span>
                    <p class="mt-2 text-xs text-ebony/65 leading-relaxed">
                        Receitas, despesas e gráficos por mês ou ano, além da cotação histórica de animais e insumos.
                    </p>
                </a>

                <!-- Gestao de Membros Card -->
                <a href="{{ route('admin.users') }}" class="group block rounded-lg border border-orisa-earth/10 bg-sacred-sand/20 p-4 transition-all duration-200 hover:border-orisa-earth/30 hover:bg-white hover:shadow-sm hover:scale-[1.01]">
                    <span class="text-sm font-bold text-ebony/80 flex items-center gap-1.5">
                        <span>👥</span> Controle de Membros
                    </span>
                    <p class="mt-2 text-xs text-ebony/65 leading-relaxed">
                        Gerencie contas cadastradas, visualize o progresso das iniciações e gerencie permissões de acesso.
                    </p>
                </a>

                <!-- Biblioteca Sagrada Card -->
                <a href="{{ route('admin.library.categories') }}" class="group block rounded-lg border border-orisa-earth/10 bg-sacred-sand/20 p-4 transition-all duration-200 hover:border-orisa-earth/30 hover:bg-white hover:shadow-sm hover:scale-[1.01]">
                    <span class="text-sm font-bold text-ebony/80 flex items-center gap-1.5">
                        <span>📚</span> Acervo & Biblioteca
                    </span>
                    <p class="mt-2 text-xs text-ebony/65 leading-relaxed">
                        Organize e disponibilize PDFs sagrados, documentos doutrinários e gravações de áudio.
                    </p>
                </a>
            </div>
        </div>
    </div>
</div>
