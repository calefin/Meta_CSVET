<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl text-ifa-green">Calendário Litúrgico</h1>
            <p class="text-sm text-ebony/60">A semana tradicional de Ifá possui 4 dias (Ọ̀sẹ̀). Cada dia é consagrado a divindades (Òrìṣà) específicas.</p>
        </div>
        @if ($isAdmin)
            <button wire:click="editAnchor" class="rounded border border-orisa-earth/30 px-3 py-1.5 text-sm text-ebony/70 hover:bg-sacred-sand">
                Ajustar data de Òrún Awó
            </button>
        @endif
    </div>

    <!-- Calendar Card -->
    <div class="mt-6 rounded-xl border border-orisa-earth/20 bg-white shadow-sm overflow-hidden">
        <!-- Navigation Header -->
        <div class="flex items-center justify-between border-b border-orisa-earth/10 bg-sacred-sand px-6 py-4">
            <button wire:click="prevMonth" class="rounded-lg p-2 text-ebony/70 hover:bg-ebony/5 hover:text-ebony transition-colors" aria-label="Mês anterior">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                    <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.83 10l3.94 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02z" clip-rule="evenodd" />
                </svg>
            </button>
            <h2 class="font-display text-lg text-ifa-green capitalize">{{ $monthName }}</h2>
            <button wire:click="nextMonth" class="rounded-lg p-2 text-ebony/70 hover:bg-ebony/5 hover:text-ebony transition-colors" aria-label="Próximo mês">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.17 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>

        <!-- Calendar Grid -->
        <div class="p-4">
            <!-- Week Days Header -->
            <div class="grid grid-cols-7 gap-1 text-center font-display text-xs text-ebony/40 uppercase tracking-wider mb-2">
                @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $i => $abbrev)
                    <div>
                        {{ $abbrev }}
                        <div class="text-[8px] normal-case font-sans text-ebony/40" lang="yo">{{ $yorubaWeek[$i]['name'] }}</div>
                    </div>
                @endforeach
            </div>

            <!-- Days Grid -->
            <div class="grid grid-cols-7 gap-1">
                @foreach ($weeks as $week)
                    @foreach ($week as $dayData)
                        @if (is_null($dayData))
                            <div class="aspect-square bg-sacred-sand/30 rounded border border-dashed border-orisa-earth/10 hidden md:block"></div>
                        @else
                            @php
                                $oseName = $dayData['ose']['name'];
                                $orisas = $dayData['ose']['orisas'];
                                $index = $dayData['ose']['index'];
                                
                                // Color scheme configurations based on Ose cycle
                                $classMap = [
                                    0 => 'bg-emerald-50 text-emerald-800 border-emerald-100 hover:bg-emerald-100/50', // Ifá
                                    1 => 'bg-blue-50 text-blue-800 border-blue-100 hover:bg-blue-100/50', // Ogun
                                    2 => 'bg-rose-50 text-rose-800 border-rose-100 hover:bg-rose-100/50', // Jàkuta
                                    3 => 'bg-amber-50 text-amber-800 border-amber-100 hover:bg-amber-100/50', // Ọbàtálá
                                ];
                                $pillMap = [
                                    0 => 'bg-emerald-500/10 text-emerald-700 border-emerald-500/20',
                                    1 => 'bg-blue-500/10 text-blue-700 border-blue-500/20',
                                    2 => 'bg-rose-500/10 text-rose-700 border-rose-500/20',
                                    3 => 'bg-amber-500/10 text-amber-800 border-amber-500/20',
                                ];
                                $classColor = $classMap[$index] ?? '';
                                $pillColor = $pillMap[$index] ?? '';
                            @endphp

                            <div 
                                class="aspect-square rounded border p-1.5 flex flex-col justify-between transition-all duration-200 {{ $classColor }} {{ $dayData['isToday'] ? 'ring-2 ring-ifa-green border-transparent font-semibold shadow-md' : 'border-orisa-earth/10' }}"
                            >
                                <!-- Day and Today indicator -->
                                <div class="flex items-center justify-between">
                                    <span class="text-xs md:text-sm font-display leading-none">{{ $dayData['day'] }}</span>
                                    @if ($dayData['isToday'])
                                        <span class="h-1.5 w-1.5 rounded-full bg-ifa-green" title="Hoje"></span>
                                    @endif
                                </div>

                                <!-- Ose Title Mini-Badge -->
                                <div class="mt-1">
                                    <span class="inline-block rounded px-1.5 py-0.5 text-[9px] md:text-[10px] font-medium leading-none border {{ $pillColor }}" lang="yo">
                                        {{ str_replace('Ọjọ́ Ọ̀sẹ̀ ', '', $oseName) }}
                                        @if (isset($dayData['ose']['sub_name']))
                                            ({{ $dayData['ose']['sub_name'] }})
                                        @endif
                                    </span>
                                </div>

                                <!-- Orisa text at the bottom -->
                                <div class="mt-auto hidden md:block text-[9px] font-sans opacity-75 truncate" lang="yo">
                                    {{ implode(', ', $orisas) }}
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>

    <!-- Legend & Liturgical details -->
    <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-emerald-200/50 bg-emerald-50/50 p-4">
            <div class="flex items-center gap-2">
                <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                <h3 class="font-display font-medium text-emerald-800" lang="yo">Ọ̀sẹ̀ Ifá</h3>
            </div>
            <p class="mt-1 text-xs text-emerald-950/70" lang="yo">Consagrado a <strong>Ifá</strong>, <strong>Ajé</strong> (Riqueza) e <strong>Òsùn</strong>.</p>
            <p class="mt-2 text-[11px] text-emerald-950/60">Dia ideal para orações de sabedoria, prosperidade e conexão espiritual profunda.</p>
        </div>

        <div class="rounded-xl border border-blue-200/50 bg-blue-50/50 p-4">
            <div class="flex items-center gap-2">
                <span class="h-3 w-3 rounded-full bg-blue-500"></span>
                <h3 class="font-display font-medium text-blue-800" lang="yo">Ọ̀sẹ̀ Ogun</h3>
            </div>
            <p class="mt-1 text-xs text-blue-950/70" lang="yo">Consagrado a <strong>Ogun</strong> (Trabalho, Abertura) e <strong>Èṣù</strong> (Caminhos).</p>
            <p class="mt-2 text-[11px] text-blue-950/60">Dia para pedir força de trabalho, quebra de obstáculos e proteção contra adversidades.</p>
        </div>

        <div class="rounded-xl border border-rose-200/50 bg-rose-50/50 p-4">
            <div class="flex items-center gap-2">
                <span class="h-3 w-3 rounded-full bg-rose-500"></span>
                <h3 class="font-display font-medium text-rose-800" lang="yo">Ọ̀sẹ̀ Jàkuta</h3>
            </div>
            <p class="mt-1 text-xs text-rose-950/70" lang="yo">Consagrado a <strong>Ṣàngó</strong> (Justiça, Poder) e <strong>Ọya</strong> (Vento, Transformação).</p>
            <p class="mt-2 text-[11px] text-rose-950/60">Dia de justiça, clareza mental e purificação de energias densas.</p>
        </div>

        <div class="rounded-xl border border-amber-200/50 bg-amber-50/50 p-4">
            <div class="flex items-center gap-2">
                <span class="h-3 w-3 rounded-full bg-amber-500"></span>
                <h3 class="font-display font-medium text-amber-900" lang="yo">Ọ̀sẹ̀ Ọbàtálá</h3>
            </div>
            <p class="mt-1 text-xs text-amber-950/70" lang="yo">Consagrado a <strong>Ọbàtálá</strong> (Criação, Paz), <strong>Ẹgbé</strong> e <strong>Egúngún</strong>.</p>
            <p class="mt-2 text-[11px] text-amber-950/60">Dia de recolhimento, vestes brancas, paz, harmonia familiar e respeito aos ancestrais.</p>
        </div>
    </div>

    <!-- Yoruba 7-day week reference -->
    <div class="mt-4 rounded-xl border border-orisa-earth/20 bg-white p-4">
        <h3 class="font-display font-medium text-ifa-green">Calendário Yoruba de 7 dias</h3>
        <p class="mt-1 text-xs text-ebony/50">Distinto da semana litúrgica de Ọ̀sẹ̀ (4 dias) acima — este é o calendário adaptado de 7 dias, alinhado à semana gregoriana.</p>
        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($yorubaWeek as $day)
                <div class="rounded border border-orisa-earth/10 bg-sacred-sand/30 p-2">
                    <p class="text-xs font-medium text-ifa-green" lang="yo">{{ $day['name'] }}</p>
                    <p class="text-[11px] text-ebony/60">{{ $day['meaning'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    @if ($isAdmin && $showAnchorModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">Ajustar data de Òrún Awó</h2>
                <p class="mt-1 text-xs text-ebony/50">Essa data marca o início do ciclo de 17 dias (Òrún Awó → Ìsán Awó → Ìtádógún Ifá) e precisa cair num dia de Ọjọ́ Ọ̀sẹ̀ Ifá.</p>

                <form wire:submit="saveAnchor" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Data de Òrún Awó</label>
                        <input type="date" wire:model="orunAnchor" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('orunAnchor') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showAnchorModal', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
