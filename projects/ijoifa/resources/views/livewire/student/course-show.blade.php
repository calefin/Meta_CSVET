<div>
    <!-- Back to Dashboard link -->
    <a href="{{ route('student.courses.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-xs font-bold text-ebony/60 hover:text-ifa-green transition-colors duration-200">
        <span>←</span> Voltar para todos os cursos
    </a>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Sidebar: Modules and Lessons -->
        <div class="rounded-2xl border border-orisa-earth/15 bg-white shadow-sm overflow-hidden lg:col-span-1 h-fit">
            <div class="bg-gradient-to-r from-ifa-green to-ifa-green/90 p-4 text-white">
                <span class="text-[10px] font-bold uppercase tracking-wider text-sacred-sand/70">Curso em andamento</span>
                <h1 class="font-display text-base font-bold tracking-tight leading-snug mt-0.5">{{ $course->title }}</h1>
            </div>

            <div class="divide-y divide-orisa-earth/10 max-h-[70vh] overflow-y-auto">
                @foreach ($course->modules as $module)
                    <div class="p-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-orisa-earth/80 px-2 mb-1.5">{{ $module->title }}</h3>
                        <ul class="space-y-1">
                            @foreach ($module->lessons as $lesson)
                                <li>
                                    <button
                                        wire:click="openLesson({{ $lesson->id }})"
                                        class="flex w-full items-start justify-between rounded-xl px-2.5 py-2 text-left text-xs transition-all duration-200 {{ $activeLesson?->id === $lesson->id ? 'bg-ifa-green/10 text-ifa-green font-semibold shadow-sm' : 'hover:bg-sacred-sand text-ebony/80 hover:text-ebony' }}"
                                    >
                                        <div class="flex items-start gap-2 max-w-[85%]">
                                            <span class="text-sm shrink-0">
                                                @if ($lesson->type === 'external' || $lesson->type === 'video')
                                                    🎥
                                                @elseif ($lesson->audio_path)
                                                    🔊
                                                @else
                                                    📖
                                                @endif
                                            </span>
                                            <span class="leading-tight">{{ $lesson->title }}</span>
                                        </div>
                                        @if ($viewedIds->contains($lesson->id))
                                            <span class="text-xs font-bold text-success shrink-0 ml-1" title="Aula concluída">✓</span>
                                        @endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Main Content Area: Lesson Viewer -->
        <div class="lg:col-span-2 space-y-6">
            @if ($activeLesson)
                <div class="rounded-2xl border border-orisa-earth/15 bg-white p-6 shadow-sm">
                    <!-- Lesson Header Metadata -->
                    <div class="border-b border-orisa-earth/10 pb-4 mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-orisa-earth/65">
                            {{ $activeLesson->module?->title }}
                        </span>
                        <h2 class="font-display text-xl font-bold text-ifa-green mt-0.5 leading-snug">
                            {{ $activeLesson->title }}
                        </h2>
                    </div>

                    <!-- Explanatory Audio Component (Rezas, Obi, Orogbo, etc.) -->
                    @if ($activeLesson->audio_path)
                        <div class="mb-6 rounded-xl border border-orun-blue/15 bg-gradient-to-br from-sacred-sand to-orun-blue/5 p-4 shadow-sm">
                            <p class="text-xs font-bold text-orun-blue mb-2 flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                                </svg>
                                Áudio Explicativo da Aula
                            </p>
                            <audio controls src="{{ route('student.lessons.audio', $activeLesson) }}" class="w-full focus:outline-none" controlsList="nodownload"></audio>
                            <p class="text-[10px] text-ebony/50 mt-1.5">Nota: O áudio contém a pronúncia correta das rezas e encantamentos em Yorùbá.</p>
                        </div>
                    @endif

                    <!-- Main Lesson Content -->
                    <div class="mt-4">
                        @if ($activeLesson->type === 'text')
                            <div 
                                x-data
                                x-init="
                                    $el.addEventListener('contextmenu', (e) => e.preventDefault());
                                    $el.addEventListener('dragstart', (e) => e.preventDefault());
                                    $el.addEventListener('copy', (e) => e.preventDefault());
                                    $el.addEventListener('selectstart', (e) => e.preventDefault());
                                "
                                class="prose prose-sm sm:prose-base max-w-none text-ebony/90 select-none relative overflow-hidden p-6 rounded-xl bg-sacred-sand/20 border border-orisa-earth/10"
                                oncontextmenu="return false"
                            >
                                <!-- Dynamic CSS Watermark overlay -->
                                <div class="pointer-events-none absolute inset-0 z-0 flex flex-wrap items-center justify-center opacity-[0.02] select-none" style="transform: rotate(-30deg);">
                                    @for ($i = 0; $i < 20; $i++)
                                        <span class="m-8 text-[10px] font-sans font-semibold text-black uppercase tracking-widest">{{ auth()->user()->name }} — {{ auth()->user()->email }}</span>
                                    @endfor
                                </div>
                                
                                <div class="relative z-10 select-none font-serif leading-relaxed">
                                    {!! $activeLesson->content !!}
                                </div>
                            </div>
                        @elseif ($activeLesson->type === 'external')
                            <div class="aspect-video w-full overflow-hidden rounded-xl border border-orisa-earth/15 bg-black shadow-inner">
                                <iframe src="{{ $activeLesson->external_url }}" class="h-full w-full" allowfullscreen></iframe>
                            </div>
                        @elseif ($activeLesson->file_path)
                            <div class="rounded-xl border border-orisa-earth/15 bg-sacred-sand/30 p-6 text-center">
                                <span class="text-4xl">📄</span>
                                <h3 class="mt-2 text-sm font-bold text-ebony">Material em Anexo</h3>
                                <p class="text-xs text-ebony/60 mb-4">Esta aula possui um arquivo de suporte disponível para download ou visualização externa.</p>
                                <a href="{{ route('student.lessons.material', $activeLesson) }}" target="_blank" class="inline-flex items-center justify-center rounded-xl bg-orun-blue px-4 py-2 text-xs font-bold text-white shadow-sm transition-all duration-300 hover:bg-orun-blue/90 hover:shadow">
                                    {{ $activeLesson->type === 'video' ? 'Assistir Vídeo' : 'Visualizar Material (PDF)' }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Assignment / Quiz card -->
                <div class="rounded-2xl border border-orisa-earth/15 bg-sacred-sand/50 p-5 shadow-sm space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">📝</span>
                        <div>
                            <h3 class="text-sm font-bold text-ifa-green">Exercício de Fixação (Opcional)</h3>
                            <p class="text-xs text-ebony/60">Escreva sua dúvida ou resposta dissertativa para acompanhamento.</p>
                        </div>
                    </div>

                    @if ($mySubmission)
                        <div class="space-y-3 mt-3">
                            <div class="rounded-xl bg-white border border-orisa-earth/10 p-3 text-xs leading-relaxed text-ebony/80">
                                <span class="block font-semibold text-[10px] uppercase text-ebony/50 mb-1">Sua Resposta:</span>
                                {{ $mySubmission->answer }}
                            </div>
                            @if ($mySubmission->feedback)
                                <div class="rounded-xl bg-emerald-500/5 border border-emerald-500/15 p-3 text-xs leading-relaxed text-emerald-900">
                                    <span class="block font-semibold text-[10px] uppercase text-emerald-700/80 mb-1">💡 Feedback do Babalawo:</span>
                                    {{ $mySubmission->feedback }}
                                </div>
                            @else
                                <div class="text-[10px] font-semibold text-orun-blue/80 flex items-center gap-1.5 px-1">
                                    <span>🕒</span> Enviado para correção e orientações.
                                </div>
                            @endif
                        </div>
                    @else
                        <form wire:submit="submitQuiz" class="mt-3 space-y-3">
                            <textarea wire:model="quizAnswer" rows="3" placeholder="Digite sua resposta ou comente sobre o seu aprendizado com o material..." class="w-full rounded-xl border border-orisa-earth/20 px-3 py-2 text-xs focus:border-ifa-green focus:ring-1 focus:ring-ifa-green outline-none bg-white"></textarea>
                            @error('quizAnswer') <p class="text-xs text-danger font-semibold">{{ $message }}</p> @enderror
                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-ifa-green px-4 py-2 text-xs font-bold text-white shadow-sm transition-all duration-300 hover:bg-ifa-green/90 hover:shadow">
                                    Enviar Resposta
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-orisa-earth/20 p-12 text-center bg-white/50">
                    <span class="text-4xl">📚</span>
                    <p class="mt-2 text-sm text-ebony/60 font-semibold">Este curso ainda não possui aulas cadastradas.</p>
                </div>
            @endif
        </div>
    </div>
</div>
