<x-filament-panels::page>
    <div class="space-y-6">
        <div>
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400 mb-3">Cursos Disponíveis na Plataforma</h2>

            @if($this->catalog()->isEmpty())
                <p class="text-sm text-slate-500 italic">Nenhum curso disponível no momento.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @php $enrollments = $this->enrollmentsByCourseId(); @endphp
                    @foreach($this->catalog() as $course)
                        @php $enrollment = $enrollments->get($course->id); @endphp
                        <button type="button" wire:click="selectCourse({{ $course->id }})"
                            class="text-left p-4 rounded-xl border transition
                                {{ $activeCourseId === $course->id ? 'border-teal-400 ring-2 ring-teal-400/40' : 'border-white/10 hover:border-teal-400/40' }}
                                {{ $enrollment ? 'bg-teal-950/20' : 'bg-slate-900/40' }}">
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-bold text-sm text-slate-100">{{ $course->name }}</span>
                                @if($enrollment && $enrollment->isRequested())
                                    <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400">⏳ AGUARDANDO</span>
                                @elseif($enrollment)
                                    <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $enrollment->isConcluded() ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400' }}">
                                        {{ $enrollment->isConcluded() ? 'CONCLUÍDO' : $enrollment->progressPercent() . '%' }}
                                    </span>
                                @else
                                    <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-700/50 text-slate-400">🔒 SEM ACESSO</span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-400 mt-1">{{ $course->workload }}h de carga horária</p>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        @if($activeCourseId)
            @php
                $course = \App\Models\Course::find($activeCourseId);
                $enrollment = $this->activeEnrollment();
            @endphp

            @if(! $course)
                {{-- curso removido, nada a mostrar --}}
            @elseif(! $enrollment)
                <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-5">
                    <p class="text-sm text-amber-300 mb-3">🔒 Você ainda não tem acesso a <strong>{{ $course->name }}</strong>. Solicite a inscrição e o professor Atilio avalia a liberação.</p>
                    <x-filament::button wire:click="requestEnrollment({{ $course->id }})" icon="heroicon-o-paper-airplane">
                        Solicitar Inscrição
                    </x-filament::button>
                </div>
            @elseif($enrollment->isRequested())
                <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-5 text-sm text-amber-300">
                    ⏳ Solicitação enviada em {{ $enrollment->created_at->format('d/m/Y') }}. Assim que o professor Atilio liberar seu acesso, a trilha de <strong>{{ $course->name }}</strong> aparece aqui automaticamente.
                </div>
            @elseif(blank($this->student()?->cpf))
                <div class="rounded-xl border border-teal-400/30 bg-teal-500/10 p-5 max-w-md">
                    <p class="text-sm text-teal-200 mb-3">Para começar <strong>{{ $course->name }}</strong>, confirme seu CPF — ele será impresso no material de estudo e usado para emitir seu certificado ao concluir o curso.</p>
                    <form wire:submit="saveCpf">
                        {{ $this->cpfForm }}
                        <x-filament::button type="submit" class="mt-3">Confirmar e Começar</x-filament::button>
                    </form>
                </div>
            @else
                @php
                    $completedIds = $enrollment->completions()->pluck('course_module_id')->all();
                    $current = $enrollment->currentModule();
                @endphp

                <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6">
                    <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4 space-y-1 h-fit">
                        <p class="text-xs font-bold uppercase text-slate-400 mb-2">Trilha — {{ $enrollment->progressPercent() }}%</p>
                        <div class="w-full h-1.5 bg-slate-800 rounded-full mb-3 overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-teal-500 to-indigo-500" style="width: {{ $enrollment->progressPercent() }}%"></div>
                        </div>
                        @foreach($enrollment->course->modules as $module)
                            <div class="flex items-center gap-2 text-xs p-2 rounded-lg {{ $current && $current->id === $module->id ? 'bg-teal-500/10 text-teal-300 font-semibold' : (in_array($module->id, $completedIds) ? 'text-emerald-400' : 'text-slate-500') }}">
                                <span>{{ in_array($module->id, $completedIds) ? '✅' : ($current && $current->id === $module->id ? '▶️' : '🔒') }}</span>
                                <span class="flex-1">{{ $module->title }}</span>
                                @if($module->type === 'avaliacao')
                                    <span class="text-[9px] uppercase opacity-60">quiz</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="rounded-xl border border-white/10 bg-slate-900/40 p-6">
                        @if($enrollment->isConcluded())
                            <div class="text-center py-8">
                                <p class="text-3xl mb-2">🎉</p>
                                <p class="font-bold text-emerald-400">Curso concluído!</p>
                                <p class="text-sm text-slate-400 mt-1">Seu certificado foi solicitado e vai aparecer em "Meus Certificados" assim que for assinado pelo professor.</p>
                            </div>
                        @elseif($current)
                            <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
                                <h3 class="font-bold text-lg text-slate-100">{{ $current->title }}</h3>
                                <a href="{{ route('portal.curso.modulo.pdf', $current->id) }}" target="_blank"
                                   class="text-xs font-semibold text-teal-400 hover:underline flex items-center gap-1">
                                    ⬇️ Baixar PDF para impressão
                                </a>
                            </div>

                            @if($current->youtube_embed_url)
                                <div class="mb-6 rounded-xl overflow-hidden border border-white/10 aspect-video">
                                    <iframe src="{{ $current->youtube_embed_url }}" class="w-full h-full" frameborder="0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                </div>
                            @endif

                            @if($current->content)
                                <div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300 mb-6 [&_pre]:bg-slate-950 [&_pre]:text-teal-300 [&_pre]:p-3 [&_pre]:rounded-lg [&_pre]:overflow-x-auto [&_code]:text-teal-300 [&_a]:text-teal-400">
                                    {!! $current->content !!}
                                </div>
                            @endif

                            @if(!empty($current->practice_exercises))
                                <div class="mb-6 space-y-3">
                                    <p class="text-sm font-bold text-slate-200">🧪 Exercícios Práticos <span class="font-normal text-slate-500">(não valem nota — é só para treinar)</span></p>
                                    @foreach($current->practice_exercises as $ex)
                                        <div class="p-4 rounded-xl bg-slate-800/40 border border-white/5"
                                             x-data="{
                                                answer: '',
                                                checked: false,
                                                correct: false,
                                                showSolution: false,
                                                check() {
                                                    const norm = v => (v ?? '').toString().trim().toLowerCase();
                                                    this.correct = norm(this.answer) === norm(@js($ex['expected_output'] ?? ''));
                                                    this.checked = true;
                                                }
                                             }">
                                            <p class="text-xs text-slate-300 mb-2">{{ $ex['prompt'] ?? '' }}</p>
                                            @if(!empty($ex['code_starter']))
                                                <pre class="text-xs bg-slate-950 text-teal-300 p-2.5 rounded-lg overflow-x-auto mb-2">{{ $ex['code_starter'] }}</pre>
                                            @endif
                                            <div class="flex flex-wrap items-center gap-2">
                                                <input type="text" x-model="answer" @keydown.enter.prevent="check()" placeholder="Digite o que você obteve ao rodar..."
                                                    class="flex-1 min-w-[160px] text-xs rounded-lg border-white/10 bg-slate-900 text-slate-200 px-3 py-2 focus:ring-teal-500 focus:border-teal-500">
                                                <button type="button" @click="check()" class="text-xs font-semibold px-3 py-2 rounded-lg bg-teal-600 hover:bg-teal-500 text-white transition">Verificar</button>
                                            </div>
                                            <template x-if="checked && correct">
                                                <p class="mt-2 text-xs text-emerald-400 font-semibold">✅ Isso aí! Resposta correta.</p>
                                            </template>
                                            <template x-if="checked && !correct">
                                                <div class="mt-2 text-xs">
                                                    <p class="text-red-400 font-semibold">❌ Ainda não é isso. Tente de novo{{ !empty($ex['solution_explanation']) ? ' ou veja como resolver.' : '.' }}</p>
                                                    @if(!empty($ex['solution_explanation']))
                                                        <button type="button" @click="showSolution = !showSolution" class="text-teal-400 hover:underline mt-1" x-text="showSolution ? 'Esconder explicação' : 'Ver como resolver'"></button>
                                                        <p class="mt-1 text-slate-400" x-show="showSolution">{{ $ex['solution_explanation'] }}</p>
                                                    @endif
                                                </div>
                                            </template>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if($current->type === 'instrucao')
                                <x-filament::button wire:click="completeInstruction">
                                    Concluir Etapa
                                </x-filament::button>
                            @else
                                <form wire:submit="submitQuiz" class="space-y-4">
                                    {{ $this->quizForm }}
                                    <x-filament::button type="submit">
                                        Enviar Respostas
                                    </x-filament::button>
                                </form>
                            @endif

                            <div class="mt-8 pt-6 border-t border-white/10">
                                <p class="text-sm font-bold text-slate-200 mb-3">💬 Dúvidas sobre esta etapa</p>
                                <div class="space-y-3 mb-4">
                                    @forelse($current->questions as $q)
                                        <div class="text-xs p-3 rounded-lg bg-slate-800/50">
                                            <p class="text-slate-300"><strong>{{ explode(' ', $q->student->user->name ?? 'Aluno')[0] }}:</strong> {{ $q->question }}</p>
                                            @if($q->isAnswered())
                                                <p class="text-teal-300 mt-1"><strong>Professor:</strong> {{ $q->answer }}</p>
                                            @else
                                                <p class="text-slate-500 italic mt-1">Aguardando resposta do professor...</p>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-500 italic">Nenhuma dúvida registrada ainda nesta etapa.</p>
                                    @endforelse
                                </div>
                                <form wire:submit="askQuestion">
                                    {{ $this->questionForm }}
                                    <x-filament::button type="submit" size="sm" color="gray" class="mt-2">
                                        Enviar Pergunta
                                    </x-filament::button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
