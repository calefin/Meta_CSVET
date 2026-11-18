<div>
    <a href="{{ route('admin.oracular.queue') }}" class="text-sm text-ifa-green hover:underline">&larr; Voltar para solicitações</a>

    <h1 class="mt-2 font-display text-2xl text-ifa-green">
        Consulta de <span lang="yo">{{ $consultationRequest->user->name }}</span>
    </h1>
    <p class="text-sm text-ebony/60">{{ $consultationRequest->description }}</p>
    @if ($consultationRequest->attachment_path)
        <a href="{{ route('consultation-requests.attachment', $consultationRequest) }}" target="_blank" class="text-sm text-orun-blue hover:underline">Ver anexo</a>
    @endif

    @if (! $consultation)
        @if ($consultationRequest->status !== 'paid')
            <div class="mt-6 p-6 rounded-lg border border-warning/30 bg-warning/5 text-warning text-center space-y-3">
                <p class="text-sm font-semibold">O registro de Odù e áreas da vida está desabilitado pois este agendamento ainda não consta como pago.</p>
                <p class="text-xs text-ebony/60">Assim que a confirmação de pagamento for efetuada no sistema, o formulário será liberado para preenchimento.</p>
            </div>
        @else
            {{-- Registration form --}}
            <form wire:submit="registerConsultation" class="mt-6 space-y-6 rounded-lg border border-orisa-earth/20 bg-white p-6">
                <h2 class="font-display text-lg text-ifa-green">Registro da consulta</h2>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium">Data e hora da consulta</label>
                        <input type="datetime-local" wire:model="consulted_at" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('consulted_at') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Modalidade efetiva</label>
                        <select wire:model="modality" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            <option value="online">Online</option>
                            <option value="presential">Presencial</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium">Odù principal</label>
                        <x-odu-select wire-model="odu_main_id" :odus="$odus" class="mt-1" />
                        @error('odu_main_id') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Odù testemunha 1</label>
                        <x-odu-select wire-model="odu_witness_1_id" :odus="$odus" placeholder="— Nenhum —" class="mt-1" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Odù testemunha 2</label>
                        <x-odu-select wire-model="odu_witness_2_id" :odus="$odus" placeholder="— Nenhum —" class="mt-1" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium">Classificação</label>
                        <div class="mt-2 flex gap-4">
                            <label class="flex items-center gap-1"><input type="radio" wire:model.live="ire_ibi" value="ire"> Ire</label>
                            <label class="flex items-center gap-1"><input type="radio" wire:model.live="ire_ibi" value="ibi"> Ibi</label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Tipo</label>
                        <select wire:model="ire_ibi_type_id" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            <option value="">— Selecione —</option>
                            @foreach ($ireIbiTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error('ire_ibi_type_id') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium">Orientações gerais (visível ao consulente)</label>
                    <textarea wire:model="general_guidance" rows="3" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
                </div>

                <div class="rounded border border-orun-blue/30 bg-orun-blue/5 p-4">
                    <h3 class="font-medium text-orun-blue">Áreas da vida (somente uso interno — nunca exibidas ao consulente)</h3>

                    <div class="mt-3 space-y-4">
                        @foreach ($areaLabels as $area => $label)
                            <div class="rounded border border-orisa-earth/20 bg-white p-3">
                                <p class="font-medium">{{ $label }}</p>
                                <div class="mt-2 grid gap-2 md:grid-cols-3">
                                    <x-odu-select wire-model="lifeAreas.{{ $area }}.odu_1_id" :odus="$odus" placeholder="Odù 1" size="sm" />
                                    <x-odu-select wire-model="lifeAreas.{{ $area }}.odu_2_id" :odus="$odus" placeholder="Odù 2" size="sm" />
                                    <x-odu-select wire-model="lifeAreas.{{ $area }}.odu_3_id" :odus="$odus" placeholder="Odù 3" size="sm" />
                                </div>
                                <textarea wire:model="lifeAreas.{{ $area }}.notes" rows="2" placeholder="Observações internas"
                                    class="mt-2 w-full rounded border border-orisa-earth/30 px-2 py-1.5 text-sm"></textarea>
                                @error("lifeAreas.{$area}.odu_1_id") <p class="mt-1 text-sm text-danger">Odù 1 de {{ $label }} inválido.</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="rounded bg-ifa-green px-6 py-2 text-white hover:bg-ifa-green/90">
                    Salvar registro (campos de Odù ficam bloqueados após salvar)
                </button>
            </form>
        @endif
    @else
        {{-- Read-only summary + addendums + recommended works --}}
        <div class="mt-6 rounded-lg border border-orisa-earth/20 bg-white p-6">
            <h2 class="font-display text-lg text-ifa-green">Registro (imutável)</h2>
            <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                <div><dt class="text-ebony/60">Odù principal</dt><dd lang="yo">{{ $consultation->oduMain->name }}</dd></div>
                <div><dt class="text-ebony/60">Classificação</dt><dd>{{ $consultation->ire_ibi === 'ire' ? 'Ire' : 'Ibi' }} — <span lang="yo">{{ $consultation->ireIbiType->name }}</span></dd></div>
                @if ($consultation->oduWitness1)
                    <div><dt class="text-ebony/60">Testemunha 1</dt><dd lang="yo">{{ $consultation->oduWitness1->name }}</dd></div>
                @endif
                @if ($consultation->oduWitness2)
                    <div><dt class="text-ebony/60">Testemunha 2</dt><dd lang="yo">{{ $consultation->oduWitness2->name }}</dd></div>
                @endif
                <div><dt class="text-ebony/60">Data da consulta</dt><dd>{{ $consultation->consulted_at->format('d/m/Y H:i') }}</dd></div>
            </dl>

            @if ($consultation->general_guidance)
                <p class="mt-3 text-sm"><strong>Orientações gerais:</strong> {{ $consultation->general_guidance }}</p>
            @endif

            @if ($consultation->guidanceAddendums->isNotEmpty())
                <div class="mt-3">
                    <p class="text-sm font-medium">Aditamentos</p>
                    @foreach ($consultation->guidanceAddendums as $addendum)
                        <p class="mt-1 rounded bg-sacred-sand p-2 text-sm">
                            <span class="text-xs text-ebony/50">{{ $addendum->created_at->format('d/m/Y H:i') }}</span><br>
                            {{ $addendum->body }}
                        </p>
                    @endforeach
                </div>
            @endif

            <form wire:submit="addAddendum" class="mt-4 flex gap-2">
                <input type="text" wire:model="addendumBody" placeholder="Adicionar orientação (nova entrada datada)"
                    class="flex-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">Adicionar</button>
            </form>
            @error('addendumBody') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror

            <h3 class="mt-6 font-display text-lg text-ifa-green">Áreas da vida (interno)</h3>
            <div class="mt-2 grid gap-2 md:grid-cols-2">
                @foreach ($consultation->lifeAreas as $area)
                    <div class="rounded border border-orisa-earth/20 p-3 text-sm">
                        <p class="font-medium">{{ $areaLabels[$area->area] }}</p>
                        <p lang="yo">{{ $area->odu1->name }}@if($area->odu2), {{ $area->odu2->name }}@endif@if($area->odu3), {{ $area->odu3->name }}@endif</p>
                        @if ($area->notes)<p class="mt-1 text-ebony/60">{{ $area->notes }}</p>@endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-6 rounded-lg border border-orisa-earth/20 bg-white p-6">
            <h2 class="font-display text-lg text-ifa-green">Trabalhos recomendados</h2>

            <ul class="mt-3 space-y-2">
                @forelse ($consultation->recommendedWorks as $work)
                    <li class="flex items-center justify-between rounded border border-orisa-earth/10 p-2 text-sm" wire:key="rw-{{ $work->id }}">
                        <span lang="yo">{{ $work->ritualWork->name }}</span>
                        <span class="flex items-center gap-2">
                            <span class="text-xs text-ebony/60">{{ $work->status }}</span>
                            @if (in_array($work->status, ['budget_requested', 'approved']))
                                <a href="{{ route('admin.oracular.budget', $work) }}" class="text-xs text-orun-blue hover:underline">Orçamento</a>
                            @endif
                            @if ($pendingPaymentWorkIds->contains($work->id))
                                <button wire:click="markWorkPaid({{ $work->id }})" wire:confirm="Confirmar que o pagamento do trabalho foi recebido?" class="text-xs text-success hover:underline">
                                    Marcar pago
                                </button>
                            @endif
                        </span>
                    </li>
                @empty
                    <p class="text-sm text-ebony/60">Nenhum trabalho recomendado ainda.</p>
                @endforelse
            </ul>

            <form wire:submit="addRecommendedWork" class="mt-4 flex flex-wrap items-end gap-2">
                <div class="flex-1">
                    <label class="block text-sm font-medium">Trabalho</label>
                    <select wire:model="newWorkId" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                        <option value="">— Selecione —</option>
                        @foreach ($ritualWorks as $work)
                            <option value="{{ $work->id }}">{{ $work->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium">Observação</label>
                    <input type="text" wire:model="newWorkNotes" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">Adicionar</button>
            </form>
            @error('newWorkId') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>
    @endif
</div>
