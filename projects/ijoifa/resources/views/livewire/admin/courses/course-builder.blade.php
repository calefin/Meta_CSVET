<div>
    <a href="{{ route('admin.courses.index') }}" class="text-sm text-ifa-green hover:underline">&larr; Voltar para cursos</a>

    <h1 class="mt-2 font-display text-2xl text-ifa-green">{{ $course->title }}</h1>

    <div class="mt-6 rounded-lg border border-orisa-earth/20 bg-white p-6">
        <h2 class="font-display text-lg text-ifa-green">Módulos e aulas</h2>

        <div class="mt-4 space-y-4">
            @foreach ($modules as $module)
                <div class="rounded border border-orisa-earth/20 p-4" wire:key="module-{{ $module->id }}">
                    <div class="flex items-center justify-between">
                        <h3 class="font-medium">{{ $module->title }}</h3>
                        <button wire:click="deleteModule({{ $module->id }})" wire:confirm="Remover este módulo e suas aulas?" class="text-xs text-danger hover:underline">Remover módulo</button>
                    </div>

                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($module->lessons as $lesson)
                            <li class="flex items-center justify-between rounded bg-sacred-sand px-3 py-1.5" wire:key="lesson-{{ $lesson->id }}">
                                <span>{{ $lesson->title }} <span class="text-xs text-ebony/50">({{ $lesson->type }})</span></span>
                                <span class="space-x-2">
                                    <button wire:click="editLesson({{ $lesson->id }})" class="text-xs text-ifa-green hover:underline">editar</button>
                                    <button wire:click="deleteLesson({{ $lesson->id }})" wire:confirm="Remover esta aula?" class="text-xs text-danger hover:underline">remover</button>
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    @if ($addingLessonToModule === $module->id)
                        <form wire:submit="saveLesson" class="mt-3 space-y-2 rounded border border-orisa-earth/20 bg-sacred-sand p-3">
                            <input type="text" wire:model="lessonTitle" placeholder="Título da aula" class="w-full rounded border border-orisa-earth/30 px-2 py-1.5 text-sm">
                            @error('lessonTitle') <p class="text-xs text-danger">{{ $message }}</p> @enderror

                            <select wire:model.live="lessonType" class="w-full rounded border border-orisa-earth/30 px-2 py-1.5 text-sm">
                                <option value="text">Texto</option>
                                <option value="video">Vídeo (upload)</option>
                                <option value="pdf">PDF (upload)</option>
                                <option value="external">Link externo (YouTube/Vimeo)</option>
                            </select>

                            @if ($lessonType === 'text')
                                <div
                                    wire:key="editor-{{ $editingLessonId ?? 'new' }}-{{ $module->id }}"
                                    wire:ignore
                                    x-data="{ editor: null, htmlMode: false, htmlText: '' }"
                                    x-init="editor = window.IjoifaEditor.mount($refs.editorEl, {
                                        initialHtml: @js($lessonContent),
                                        onChange: (html) => $wire.set('lessonContent', html),
                                    })"
                                >
                                    <div class="flex justify-end gap-3">
                                        <button
                                            type="button"
                                            class="mb-1 text-xs text-orun-blue hover:underline"
                                            x-on:click="
                                                const md = prompt('Cole o texto em Markdown:');
                                                if (md !== null) { editor.importMarkdown(md); }
                                            "
                                        >importar Markdown</button>
                                        <button
                                            type="button"
                                            class="mb-1 text-xs text-orun-blue hover:underline"
                                            x-on:click="
                                                if (!htmlMode) { htmlText = editor.getHtml(); }
                                                htmlMode = !htmlMode;
                                            "
                                            x-text="htmlMode ? 'voltar ao editor visual' : 'ver/editar HTML'"
                                        ></button>
                                    </div>
                                    <div x-show="!htmlMode" x-ref="editorEl" class="bg-white" style="min-height: 8rem"></div>
                                    <div x-show="htmlMode" x-cloak>
                                        <textarea
                                            x-model="htmlText"
                                            rows="10"
                                            class="w-full rounded border border-orisa-earth/30 px-3 py-2 font-mono text-xs"
                                            placeholder="<p>HTML da aula...</p>"
                                        ></textarea>
                                        <button
                                            type="button"
                                            class="mt-2 rounded bg-ifa-green px-3 py-1.5 text-xs text-white hover:bg-ifa-green/90"
                                            x-on:click="editor.setHtml(htmlText); htmlMode = false;"
                                        >Aplicar HTML e voltar ao editor visual</button>
                                    </div>
                                </div>
                                <p class="text-xs text-ebony/40">Cole conteúdo formatado (Word/Google Docs) diretamente, use "importar Markdown", ou edite o HTML puro em "ver/editar HTML".</p>
                            @elseif ($lessonType === 'external')
                                <input type="text" wire:model="lessonExternalUrl" placeholder="URL do vídeo (YouTube/Vimeo)" class="w-full rounded border border-orisa-earth/30 px-2 py-1.5 text-sm">
                            @else
                                <input type="file" wire:model="lessonFile" class="w-full text-sm">
                                @error('lessonFile') <p class="text-xs text-danger">{{ $message }}</p> @enderror
                            @endif

                            <!-- Audio Upload Field for Lesson -->
                            <div class="mt-3 border-t border-orisa-earth/10 pt-2">
                                <label class="block text-xs font-semibold text-ebony/60">Áudio Explicativo da Aula (Opcional)</label>
                                @if ($currentLessonAudioPath)
                                    <div class="mt-1 flex items-center justify-between rounded bg-white p-2 text-xs">
                                        <span class="truncate text-ebony/70">Áudio explicativo associado</span>
                                        <button type="button" wire:click="deleteLessonAudio" wire:confirm="Excluir o áudio atual da aula?" class="text-danger hover:underline">remover áudio</button>
                                    </div>
                                @endif
                                <input type="file" wire:model="lessonAudio" accept="audio/*" class="mt-1 block w-full text-xs text-ebony file:mr-4 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-white file:text-ifa-green hover:file:bg-white/80">
                                @error('lessonAudio') <p class="text-xs text-danger">{{ $message }}</p> @enderror
                                <div wire:loading wire:target="lessonAudio" class="text-xs text-ebony/50 mt-1">enviando áudio...</div>
                            </div>

                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="$set('addingLessonToModule', null); $set('editingLessonId', null)" class="text-xs text-ebony/60 hover:underline">cancelar</button>
                                <button type="submit" class="rounded bg-ifa-green px-3 py-1 text-xs text-white hover:bg-ifa-green/90">{{ $editingLessonId ? 'salvar alterações' : 'adicionar aula' }}</button>
                            </div>
                        </form>
                    @else
                        <button wire:click="showLessonForm({{ $module->id }})" class="mt-2 text-xs text-ifa-green hover:underline">+ adicionar aula</button>
                    @endif
                </div>
            @endforeach
        </div>

        <form wire:submit="addModule" class="mt-4 flex gap-2">
            <input type="text" wire:model="newModuleTitle" placeholder="Novo módulo" class="flex-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
            <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">+ modulo</button>
        </form>
        @error('newModuleTitle') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    @if ($pendingPayments->isNotEmpty())
        <div class="mt-6 rounded-lg border border-orun-blue/30 bg-orun-blue/5 p-6">
            <h2 class="font-display text-lg text-orun-blue">Pagamentos pendentes</h2>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($pendingPayments as $payment)
                    <li class="flex items-center justify-between rounded bg-white px-3 py-1.5" wire:key="pay-{{ $payment->id }}">
                        <span lang="yo">{{ $payment->user->name }} — R$ {{ number_format($payment->amount, 2, ',', '.') }}</span>
                        <button wire:click="confirmPayment({{ $payment->id }})" wire:confirm="Confirmar pagamento recebido?" class="text-xs text-success hover:underline">Marcar pago</button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6 rounded-lg border border-orisa-earth/20 bg-white p-6">
        <h2 class="font-display text-lg text-ifa-green">Acesso individual</h2>
        <p class="text-xs text-ebony/50">Além do público padrão ({{ $course->target }}), você pode liberar para usuários específicos.</p>

        <ul class="mt-3 space-y-1 text-sm">
            @foreach ($enrollments as $enrollment)
                <li class="flex items-center justify-between rounded bg-sacred-sand px-3 py-1.5" wire:key="enr-{{ $enrollment->id }}">
                    <span lang="yo">{{ $enrollment->user->name }}
                        <span class="text-xs text-ebony/40">
                            ({{ match($enrollment->source) {
                                'purchase' => 'comprou',
                                'manual' => 'liberado manualmente',
                                'initiation_criteria' => 'liberado pela iniciação',
                                'open_free' => 'curso gratuito',
                                default => 'sem registro',
                            } }})
                        </span>
                    </span>
                    <button wire:click="revokeAccess({{ $enrollment->user_id }})" class="text-xs text-danger hover:underline">revogar</button>
                </li>
            @endforeach
        </ul>

        <form wire:submit="grantAccess" class="mt-3 flex gap-2">
            <select wire:model="grantUserId" class="flex-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                <option value="">— Selecione um usuário —</option>
                @foreach ($grantableUsers as $user)
                    <option value="{{ $user->id }}" lang="yo">{{ $user->name }} ({{ $user->role }})</option>
                @endforeach
            </select>
            <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">Liberar acesso</button>
        </form>
    </div>
</div>
