<div>
    <div class="mb-6">
        <h1 class="font-display text-2xl text-orisa-earth">Família Espiritual (Ẹgbẹ́)</h1>
        <p class="text-sm text-ebony/60">A egrégora de iniciados da casa sob a tutela espiritual do Babalawo.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Family Hierarchy Card -->
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-lg border border-orisa-earth/20 bg-white shadow-sm overflow-hidden">
                <div class="bg-sacred-sand px-4 py-3 border-b border-orisa-earth/10 flex items-center justify-between">
                    <h2 class="font-display text-base text-orisa-earth font-semibold">Membros da Egrégora</h2>
                    <span class="text-xs font-mono text-ebony/60 bg-white border border-orisa-earth/10 px-2 py-0.5 rounded-full">
                        {{ $initiates->count() }} Iniciados
                    </span>
                </div>

                <div class="divide-y divide-orisa-earth/10">
                    @foreach ($initiates as $index => $initiate)
                        <div class="p-4 flex flex-wrap items-center justify-between gap-4 transition hover:bg-sacred-sand/10 duration-200" wire:key="family-{{ $initiate->id }}">
                            <div class="flex items-start gap-3">
                                <!-- Seniority Badge -->
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ifa-green font-mono text-xs text-white shadow-sm" title="Ordem de Senioridade">
                                    {{ $index + 1 }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-ebony" lang="yo">{{ $initiate->name }}</h3>
                                        @if ($initiate->id === auth()->id())
                                            <span class="rounded-full bg-ifa-green/15 px-2 py-0.5 text-[10px] font-bold text-ifa-green uppercase tracking-wider">Você</span>
                                        @endif
                                    </div>
                                    @if ($initiate->spiritual_name)
                                        <p class="text-sm font-medium text-orisa-earth mt-0.5 font-display" lang="yo">
                                            Orúkọ Ifá: <span class="italic font-bold">{{ $initiate->spiritual_name }}</span>
                                        </p>
                                    @endif

                                    @if ($initiate->orisaRecords->isNotEmpty())
                                        <div class="mt-1 text-xs text-ebony/75 space-y-0.5">
                                            @foreach ($initiate->orisaRecords as $rec)
                                                <p><span class="font-semibold">{{ $rec->orisa_name }}:</span> <span class="italic font-bold text-orisa-earth" lang="yo">{{ $rec->spiritual_name }}</span></p>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if (!$initiate->spiritual_name && $initiate->orisaRecords->isEmpty())
                                        <p class="text-xs text-ebony/40 mt-0.5 italic">Sem nome espiritual registrado</p>
                                    @endif
                                    
                                    @if ($initiate->initiation_date)
                                        <p class="text-xs text-ebony/50 mt-1">
                                            Iniciado em: {{ \Carbon\Carbon::parse($initiate->initiation_date)->format('d/m/Y') }}
                                        </p>
                                    @else
                                        <p class="text-xs text-ebony/40 mt-1 italic">Data de iniciação pendente</p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($initiate->id !== auth()->id())
                                    <a href="{{ route('iniciado.messages', ['user_id' => $initiate->id]) }}" class="inline-flex items-center gap-1 rounded bg-ifa-green px-3 py-1.5 text-xs font-semibold text-white hover:bg-ifa-green/90 transition-colors shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                        </svg>
                                        Enviar Mensagem
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Lineage Sidebar Card -->
        <div class="space-y-4">
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-6 shadow-sm">
                <h3 class="font-display text-lg text-orisa-earth mb-3 border-b border-orisa-earth/10 pb-2">Sobre a Hierarquia (Ẹgbẹ́)</h3>
                <div class="space-y-3 text-sm text-ebony/80 leading-relaxed">
                    <p>
                        Na tradição tradicional Yorùbá de Ifá, a <strong>senioridade iniciática</strong> é estabelecida pela data exata do nascimento espiritual (a data da iniciação - <em>Itẹfá</em>), e não pela idade biológica ou data de entrada física na casa.
                    </p>
                    <p>
                        Esta listagem reflete a estrutura hierárquica e respeitosa de nossa egrégora, ordenando os filhos espirituais dos mais velhos aos mais novos.
                    </p>
                    <div class="rounded border border-orisa-earth/15 bg-sacred-sand/20 p-3 text-xs text-ebony/70 mt-4">
                        <strong class="text-orisa-earth block mb-1">Nota Ética:</strong>
                        Mantenha sempre a ética iniciática (<em>Ìwà Pẹ̀lẹ́</em>) ao se comunicar com seus irmãos espirituais. Trate a todos com respeito e zele pela união de nossa família.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
