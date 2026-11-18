<x-filament-panels::page>
    {{--
        Página customizada do Filament: classes utilitárias do Tailwind aqui NÃO
        são compiladas no bundle do painel (o AdminPanelProvider não registra um
        tema Vite customizado — mesma limitação que já afeta a página nativa
        "Assinatura via Certificado"). Por isso o visual usa CSS próprio via
        <style>, que sempre funciona independente do build.
    --}}
    <style>
        .pomodoro-wrap { max-width: 36rem; margin: 0 auto; }

        .pomodoro-setup {
            background: #f8fafc;
            border: 1px solid #99f6e4;
            border-radius: 1rem;
            padding: 1.5rem;
        }
        .dark .pomodoro-setup { background: rgba(15, 23, 42, .6); border-color: rgba(45, 212, 191, .25); }

        .pomodoro-field { margin-bottom: 1.25rem; }
        .pomodoro-field:last-child { margin-bottom: 0; }
        .pomodoro-label {
            display: block; font-size: .75rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: .05em; color: #64748b; margin-bottom: .5rem;
        }
        .dark .pomodoro-label { color: #94a3b8; }

        .pomodoro-input, .pomodoro-select {
            width: 100%; box-sizing: border-box; border-radius: .75rem;
            background: #fff; border: 1px solid #cbd5e1; color: #0f172a;
            font-size: .875rem; padding: .625rem .75rem;
        }
        .dark .pomodoro-input, .dark .pomodoro-select {
            background: rgba(30, 41, 59, .7); border-color: rgba(255, 255, 255, .1); color: #fff;
        }

        .pomodoro-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .pomodoro-hint { font-size: .75rem; color: #94a3b8; margin-top: .5rem; }

        .pomodoro-start-btn {
            width: 100%; padding: .875rem; border-radius: .75rem; background: #0d9488;
            color: #fff; font-weight: 600; border: none; cursor: pointer; transition: background .2s;
            margin-top: 1.5rem;
        }
        .pomodoro-start-btn:hover:not(:disabled) { background: #14b8a6; }
        .pomodoro-start-btn:disabled { opacity: .4; cursor: not-allowed; }

        .pomodoro-timer-card {
            border-radius: 1rem; padding: 2rem; text-align: center; border: 1px solid;
            transition: background-color .3s, border-color .3s, box-shadow .6s ease;
        }
        .pomodoro-timer-card.is-focus { background: #f0fdfa; border-color: #99f6e4; }
        .pomodoro-timer-card.is-rest { background: #eef2ff; border-color: #c7d2fe; }
        .dark .pomodoro-timer-card.is-focus { background: rgba(19, 78, 74, .4); border-color: rgba(20, 184, 166, .3); }
        .dark .pomodoro-timer-card.is-rest { background: rgba(49, 46, 129, .4); border-color: rgba(99, 102, 241, .3); }

        .pomodoro-timer-card.is-running.is-focus { animation: pomodoro-glow-focus 2.4s ease-in-out infinite; }
        .pomodoro-timer-card.is-running.is-rest { animation: pomodoro-glow-rest 2.4s ease-in-out infinite; }
        @keyframes pomodoro-glow-focus {
            0%, 100% { box-shadow: 0 0 0 0 rgba(20, 184, 166, .25); }
            50% { box-shadow: 0 0 0 10px rgba(20, 184, 166, 0); }
        }
        @keyframes pomodoro-glow-rest {
            0%, 100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, .25); }
            50% { box-shadow: 0 0 0 10px rgba(99, 102, 241, 0); }
        }

        .pomodoro-icon {
            font-size: 2.5rem; line-height: 1; margin-bottom: .25rem;
            display: inline-block; transform-origin: center;
        }
        .pomodoro-icon.is-running { animation: pomodoro-bob 1.6s ease-in-out infinite; }
        .pomodoro-icon.is-pop { animation: pomodoro-pop .5s ease; }
        @keyframes pomodoro-bob {
            0%, 100% { transform: translateY(0) rotate(-4deg); }
            50% { transform: translateY(-6px) rotate(4deg); }
        }
        @keyframes pomodoro-pop {
            0% { transform: scale(1); }
            35% { transform: scale(1.5) rotate(8deg); }
            60% { transform: scale(.9) rotate(-4deg); }
            100% { transform: scale(1) rotate(0); }
        }

        .pomodoro-phase-label { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .2em; }
        .pomodoro-phase-label.is-focus { color: #0d9488; }
        .pomodoro-phase-label.is-rest { color: #6366f1; }
        .dark .pomodoro-phase-label.is-focus { color: #2dd4bf; }
        .dark .pomodoro-phase-label.is-rest { color: #a5b4fc; }

        .pomodoro-countdown {
            font-size: 4.5rem; line-height: 1; font-family: ui-monospace, "Cascadia Code", monospace;
            font-weight: 700; color: #0f172a; margin: .75rem 0 0; font-variant-numeric: tabular-nums;
        }
        .dark .pomodoro-countdown { color: #fff; }

        .pomodoro-progress-track {
            height: .375rem; width: 100%; border-radius: 9999px; background: #e2e8f0;
            overflow: hidden; margin-top: 1.25rem;
        }
        .dark .pomodoro-progress-track { background: rgba(255, 255, 255, .1); }
        .pomodoro-progress-fill { height: 100%; border-radius: 9999px; transition: width 1s linear; }
        .pomodoro-progress-fill.is-focus { background: #14b8a6; }
        .pomodoro-progress-fill.is-rest { background: #818cf8; }

        .pomodoro-meta { font-size: .75rem; color: #64748b; margin-top: .75rem; }
        .pomodoro-meta.is-small { margin-top: .25rem; }
        .dark .pomodoro-meta { color: #94a3b8; }

        .pomodoro-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; margin-top: 1.25rem; }
        .pomodoro-btn {
            padding: .75rem; border-radius: .75rem; background: #f1f5f9; color: #0f172a;
            font-size: .875rem; font-weight: 600; border: none; cursor: pointer; transition: background .2s;
        }
        .pomodoro-btn:hover { background: #e2e8f0; }
        .dark .pomodoro-btn { background: #1e293b; color: #fff; }
        .dark .pomodoro-btn:hover { background: #334155; }
        .pomodoro-btn.is-danger { background: #fef2f2; color: #b91c1c; }
        .pomodoro-btn.is-danger:hover { background: #fee2e2; }
        .dark .pomodoro-btn.is-danger { background: rgba(127, 29, 29, .5); color: #fecaca; }
        .dark .pomodoro-btn.is-danger:hover { background: rgba(127, 29, 29, .7); }
    </style>

    <div
        x-data="{
            activityId: null,
            focusMinutes: 20,
            restMinutes: 5,
            phase: 'idle',
            secondsLeft: 0,
            running: false,
            cycles: 0,
            focusElapsedThisPhase: 0,
            totalLabel: null,
            justPopped: false,

            get isIdle() { return this.phase === 'idle'; },
            get isFocus() { return this.phase === 'focus'; },
            get isRest() { return this.phase === 'rest'; },

            get phaseTotalSeconds() {
                return (this.isFocus ? this.focusMinutes : this.restMinutes) * 60;
            },

            get progressPercent() {
                if (this.isIdle || this.phaseTotalSeconds === 0) return 0;
                return Math.min(100, Math.max(0, 100 - (this.secondsLeft / this.phaseTotalSeconds * 100)));
            },

            get formatted() {
                const m = Math.floor(this.secondsLeft / 60).toString().padStart(2, '0');
                const s = Math.floor(this.secondsLeft % 60).toString().padStart(2, '0');
                return m + ':' + s;
            },

            beep() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.value = 880;
                    gain.gain.setValueAtTime(0.15, ctx.currentTime);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.35);
                } catch (e) {}
            },

            start() {
                if (!this.activityId) return;
                this.phase = 'focus';
                this.secondsLeft = this.focusMinutes * 60;
                this.focusElapsedThisPhase = 0;
                this.cycles = 0;
                this.running = true;
            },

            toggleRunning() {
                if (this.isIdle) return;
                this.running = !this.running;
            },

            commitFocus() {
                if (this.focusElapsedThisPhase > 0) {
                    $wire.addFocusSeconds(this.activityId, this.focusElapsedThisPhase).then((label) => {
                        if (label) this.totalLabel = label;
                    });
                    this.focusElapsedThisPhase = 0;
                }
            },

            pop() {
                this.justPopped = true;
                setTimeout(() => { this.justPopped = false; }, 500);
            },

            phaseComplete() {
                this.beep();
                this.pop();
                if (this.isFocus) {
                    this.commitFocus();
                    this.cycles++;
                    this.phase = 'rest';
                    this.secondsLeft = this.restMinutes * 60;
                } else {
                    this.phase = 'focus';
                    this.secondsLeft = this.focusMinutes * 60;
                }
            },

            skip() {
                if (this.isIdle) return;
                if (this.isFocus) {
                    this.commitFocus();
                    this.cycles++;
                    this.phase = 'rest';
                    this.secondsLeft = this.restMinutes * 60;
                } else {
                    this.phase = 'focus';
                    this.secondsLeft = this.focusMinutes * 60;
                }
            },

            stop() {
                if (this.isFocus) this.commitFocus();
                this.phase = 'idle';
                this.running = false;
                this.secondsLeft = 0;
                this.cycles = 0;
            },

            tick() {
                if (!this.running || this.isIdle) return;
                this.secondsLeft--;
                if (this.isFocus) this.focusElapsedThisPhase++;
                if (this.secondsLeft <= 0) this.phaseComplete();
            },
        }"
        x-init="setInterval(() => tick(), 1000)"
        class="pomodoro-wrap"
    >
        <!-- Configuração (visível apenas em idle) -->
        <div x-show="isIdle" class="pomodoro-setup">
            <div class="pomodoro-field">
                <label class="pomodoro-label">Atividade</label>
                <select x-model="activityId" class="pomodoro-select">
                    <option value="" disabled selected>Selecione a atividade</option>
                    @foreach ($this->getActivities() as $activity)
                        <option value="{{ $activity->id }}">
                            {{ $activity->title }}@if($activity->category) — {{ $activity->category->name }}@endif
                        </option>
                    @endforeach
                </select>
                @if ($this->getActivities()->isEmpty())
                    <p class="pomodoro-hint">Nenhuma atividade pendente. Cadastre uma em "Atividades & Timer" primeiro.</p>
                @endif
            </div>

            <div class="pomodoro-field pomodoro-grid-2">
                <div>
                    <label class="pomodoro-label">Foco (min)</label>
                    <input type="number" min="1" max="180" x-model.number="focusMinutes" class="pomodoro-input">
                </div>
                <div>
                    <label class="pomodoro-label">Descanso (min)</label>
                    <input type="number" min="1" max="60" x-model.number="restMinutes" class="pomodoro-input">
                </div>
            </div>

            <button type="button" x-on:click="start()" x-bind:disabled="!activityId" class="pomodoro-start-btn">
                Iniciar Pomodoro
            </button>
        </div>

        <!-- Timer em andamento -->
        <div x-show="!isIdle" x-cloak>
            <div class="pomodoro-timer-card" x-bind:class="[isFocus ? 'is-focus' : 'is-rest', running ? 'is-running' : '']">
                <span class="pomodoro-icon" x-bind:class="[running ? 'is-running' : '', justPopped ? 'is-pop' : '']" x-text="isFocus ? '🍅' : '☕'"></span>
                <p class="pomodoro-phase-label" x-bind:class="isFocus ? 'is-focus' : 'is-rest'">
                    <span x-show="isFocus">🎯 Foco</span>
                    <span x-show="isRest">Descanso</span>
                </p>
                <p class="pomodoro-countdown" x-text="formatted"></p>

                <div class="pomodoro-progress-track">
                    <div
                        class="pomodoro-progress-fill"
                        x-bind:class="isFocus ? 'is-focus' : 'is-rest'"
                        x-bind:style="'width:' + progressPercent + '%'"
                    ></div>
                </div>

                <p class="pomodoro-meta">Ciclo <span x-text="cycles"></span> concluído(s)</p>
                <p class="pomodoro-meta is-small" x-show="totalLabel" x-text="'Total acumulado na atividade: ' + totalLabel"></p>
            </div>

            <div class="pomodoro-actions">
                <button type="button" x-on:click="toggleRunning()" class="pomodoro-btn">
                    <span x-show="running">Pausar</span>
                    <span x-show="!running">Retomar</span>
                </button>
                <button type="button" x-on:click="skip()" class="pomodoro-btn">
                    Pular Fase
                </button>
                <button type="button" x-on:click="stop()" class="pomodoro-btn is-danger">
                    Parar
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
