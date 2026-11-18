<div>
    @if (session()->has('error'))
        <div class="mb-4 rounded-lg bg-danger/10 border border-danger/30 p-4 text-danger text-sm">
            {{ session('error') }}
        </div>
    @endif

    <!-- Premium Welcome Header -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-ifa-green to-orisa-earth px-6 py-8 text-white shadow-md">
        <div class="relative z-10">
            <span class="text-xs font-semibold uppercase tracking-wider text-sacred-sand/70">Área de Membros</span>
            <h1 class="mt-1 font-display text-3xl font-bold tracking-tight md:text-4xl">Sistema de Estudos</h1>
            <p class="mt-2 max-w-xl text-sm text-sacred-sand/90">
                Acompanhe suas aulas, confira as rezas diárias e consulte os oráculos de forma prática e direta.
            </p>
        </div>
        <!-- Decorative abstract circles -->
        <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10 blur-xl"></div>
        <div class="absolute -right-20 -bottom-20 h-60 w-60 rounded-full bg-white/5 blur-2xl"></div>
    </div>

    <!-- Seção do Curso Contínuo Telegram -->
    <div class="mt-6 rounded-2xl border border-orisa-earth/15 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="text-xl">✈️</span>
                    <h2 class="font-display text-lg font-bold text-ebony">Curso de Formação Contínua no Telegram</h2>
                </div>
                <p class="text-xs text-ebony/70">
                    2 aulas por semana no grupo do Telegram + 1 encontro presencial mensal para atividades práticas. (R$ 180,00/mês)
                </p>
            </div>

            <div class="flex-shrink-0">
                @if ($telegramSub && $telegramSub->isActive())
                    <div class="flex items-center gap-3">
                        <div class="text-right text-xs">
                            <span class="inline-block rounded-full bg-emerald-100 px-2.5 py-0.5 font-bold text-emerald-800">Ativo / Adimplente</span>
                            <div class="mt-0.5 text-ebony/60">Vence em {{ $telegramSub->expires_at?->format('d/m/Y') }}</div>
                        </div>
                        @if ($telegramLastPayment)
                            <a href="{{ route('telegram-subscriptions.receipt', $telegramLastPayment->id) }}" target="_blank" class="inline-flex items-center gap-1 rounded-xl border border-orisa-earth/20 bg-sacred-sand/40 px-3 py-2 text-xs font-semibold text-ebony hover:bg-sacred-sand" title="Baixar Recibo PDF">
                                📄 Recibo PDF
                            </a>
                        @endif
                        <a href="https://t.me/{{ ltrim($telegramSub->telegram_username, '@') }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-xl bg-ifa-green px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-ifa-green/90">
                            <span>Abrir Telegram</span>
                        </a>
                    </div>
                @elseif ($telegramSub)
                    <div class="flex items-center gap-3">
                        <div class="text-right text-xs">
                            <span class="inline-block rounded-full bg-rose-100 px-2.5 py-0.5 font-bold text-rose-800">Mensalidade Pendente</span>
                        </div>
                        <a href="{{ route('public-telegram-course-registration') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-orun-blue px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-orun-blue/90">
                            <span>Pagar Mensalidade (R$ 180)</span>
                        </a>
                    </div>
                @else
                    <a href="{{ route('public-telegram-course-registration') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-ifa-green px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-ifa-green/90">
                        <span>Inscrever-se no Curso (R$ 180/mês)</span>
                    </a>
                @endif
            </div>
        </div>

        @if ($telegramSub && $telegramSub->isActive() && $telegramLessons->isNotEmpty())
            <div class="mt-5 border-t border-orisa-earth/10 pt-4">
                <h3 class="font-display text-sm font-bold text-ifa-green flex items-center gap-1.5">
                    <span>📦</span> Conteúdos & Materiais dos Encontros Práticos
                </h3>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($telegramLessons as $tLesson)
                        <div class="rounded-xl border border-orisa-earth/15 bg-sacred-sand/20 p-3.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-ebony">{{ $tLesson->title }}</span>
                                <span class="text-[10px] uppercase px-1.5 py-0.5 rounded font-semibold {{ $tLesson->type === 'presential_meeting' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ $tLesson->type === 'presential_meeting' ? 'Presencial' : 'Telegram' }}
                                </span>
                            </div>
                            @if ($tLesson->description)
                                <p class="mt-1 text-ebony/70 leading-relaxed">{{ $tLesson->description }}</p>
                            @endif
                            @if ($tLesson->checklist_materials)
                                <div class="mt-2 rounded bg-amber-50 border border-amber-200 p-2 text-amber-900">
                                    <strong>Insumos Práticos:</strong> {{ $tLesson->checklist_materials }}
                                </div>
                            @endif
                            @if ($tLesson->content || $tLesson->document_path)
                                <a href="{{ route('telegram-course.lessons.material-pdf', $tLesson) }}" target="_blank" class="mt-2 inline-flex items-center gap-1 rounded-lg bg-ifa-green/10 px-2.5 py-1 text-[11px] font-semibold text-ifa-green hover:bg-ifa-green/20">
                                    📄 Baixar Apostila (PDF)
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Daily Quick Access Section -->
    @if ($quickAccessLessons->isNotEmpty())
        <div class="mt-8">
            <h2 class="font-display text-lg font-bold text-ifa-green flex items-center gap-2">
                <span>⚡</span> Acesso Rápido Diário
            </h2>
            <p class="text-xs text-ebony/60 mt-1">Consulte as rezas e oráculos essenciais da nossa prática cotidiana.</p>
            
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($quickAccessLessons as $lesson)
                    <a href="{{ route('student.courses.show', $lesson->course_id) }}?lesson={{ $lesson->id }}" 
                       class="group flex flex-col justify-between rounded-xl border border-orisa-earth/10 p-4 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md {{ $lesson->color_bg }}">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-3xl">{{ $lesson->icon }}</span>
                                <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full bg-white/60 text-ebony/70 group-hover:bg-white transition-colors duration-300">Acessar</span>
                            </div>
                            <h3 class="mt-4 text-sm font-semibold tracking-tight leading-snug {{ $lesson->color_text }}">{{ $lesson->display_title }}</h3>
                            <p class="mt-1 text-xs text-ebony/60 leading-normal">{{ $lesson->description }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Available Courses Grid -->
    <div class="mt-8">
        <h2 class="font-display text-lg font-bold text-ifa-green flex items-center gap-2">
            <span>🎓</span> Cursos Disponíveis
        </h2>
        <p class="text-xs text-ebony/60 mt-1">Estude os manuais tradicionais e de formação sacerdotal.</p>
        
        <div class="mt-4 grid gap-6 md:grid-cols-2">
            @forelse ($courses as $course)
                <div class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-orisa-earth/15 bg-white p-5 shadow-sm transition-all duration-300 hover:shadow-md" wire:key="course-{{ $course->id }}">
                    <div>
                        <div class="flex items-start justify-between gap-4">
                            <h3 class="font-display text-base font-bold text-ifa-green group-hover:text-ifa-green/90 transition-colors duration-300">{{ $course->title }}</h3>
                            @if ($course->locked_for_consulente)
                                <span class="shrink-0 inline-flex items-center gap-1 rounded-full bg-sacred-sand border border-orisa-earth/10 px-2.5 py-0.5 text-[10px] font-semibold text-ebony/50">
                                    🔒 Restrito
                                </span>
                            @elseif ($course->needs_purchase)
                                <span class="shrink-0 inline-flex items-center gap-1 rounded-full bg-amber-500/10 border border-amber-500/25 px-2.5 py-0.5 text-[10px] font-semibold text-amber-800">
                                    Pago
                                </span>
                            @else
                                <span class="shrink-0 inline-flex items-center gap-1 rounded-full bg-emerald-500/10 border border-emerald-500/25 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-800">
                                    Liberado
                                </span>
                            @endif
                        </div>
                        
                        <p class="mt-2 text-sm text-ebony/70 leading-relaxed">{{ $course->description }}</p>
                    </div>

                    <div class="mt-6 border-t border-orisa-earth/5 pt-4">
                        @if ($course->locked_for_consulente)
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-ebony/50">Disponível apenas para Iniciados no Ifá.</span>
                                <span class="text-xs font-semibold text-ebony/40">Bloqueado</span>
                            </div>
                        @elseif ($course->needs_purchase)
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="text-xs">
                                    <span class="text-ebony/50">Valor do Investimento:</span>
                                    <div class="text-sm font-bold text-ebony">R$ {{ number_format($course->price, 2, ',', '.') }}</div>
                                </div>
                                
                                @if ($course->has_pending_payment)
                                    @if ($course->checkout_url)
                                        <a href="{{ $course->checkout_url }}" target="_blank" class="inline-flex items-center justify-center rounded-xl bg-orun-blue px-4 py-2 text-xs font-bold text-white shadow-sm transition-all duration-300 hover:bg-orun-blue/90 hover:shadow">
                                            Pagar agora
                                        </a>
                                    @else
                                        <span class="text-xs font-semibold text-orun-blue flex items-center gap-1">
                                            🕒 Pagamento pendente
                                        </span>
                                    @endif
                                @else
                                    <button wire:click="buy({{ $course->id }})" class="inline-flex items-center justify-center rounded-xl bg-ifa-green px-4 py-2 text-xs font-bold text-white shadow-sm transition-all duration-300 hover:bg-ifa-green/90 hover:shadow">
                                        Adquirir Acesso
                                    </button>
                                @endif
                            </div>
                        @else
                            <div class="flex flex-col gap-3">
                                @if ($course->unlocked_by_criteria)
                                    <div class="text-xs font-medium text-emerald-700 flex items-center gap-1">
                                        <span>✓</span> Liberado pela sua iniciação
                                    </div>
                                @endif
                                
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="h-2 w-full rounded-full bg-sacred-sand overflow-hidden">
                                            <div class="h-full rounded-full bg-gradient-to-r from-ifa-green to-emerald-600 transition-all duration-500" style="width: {{ $course->progress_percent }}%"></div>
                                        </div>
                                        <div class="mt-1 text-[11px] font-semibold text-ebony/60">{{ $course->progress_percent }}% concluído</div>
                                    </div>
                                    
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('student.courses.show', $course) }}" class="inline-flex items-center justify-center rounded-xl bg-ifa-green px-4 py-2 text-xs font-bold text-white shadow-sm transition-all duration-300 hover:bg-ifa-green/90 hover:shadow">
                                            {{ $course->progress_percent > 0 ? 'Continuar' : 'Acessar' }}
                                        </a>
                                        
                                        @if ($course->is_completed)
                                            <a href="{{ route('student.courses.certificate', $course) }}" target="_blank" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-orun-blue/30 text-orun-blue hover:bg-orun-blue/10 transition-all duration-300" title="Ver Certificado">
                                                📜
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-2 rounded-2xl border border-dashed border-orisa-earth/20 p-8 text-center bg-white/50">
                    <span class="text-4xl">📚</span>
                    <p class="mt-2 text-sm text-ebony/60">Nenhum curso disponível para visualização no momento.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
