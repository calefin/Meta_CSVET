@php
    use App\Models\ResearchApplication;

    $steps = collect($call->steps ?? [])->filter(fn ($s) => filled($s['title'] ?? null))->values();
    $projects = $call->projectsByCode();
    $status = $application->status;
    $stopped = in_array($status, [ResearchApplication::STATUS_REJECTED, ResearchApplication::STATUS_WITHDRAWN, ResearchApplication::STATUS_REMOVED], true);
    $banner = match ($status) {
        ResearchApplication::STATUS_NEW => ['amber', 'Sua inscrição foi recebida e está na fila de análise. Você recebe um e-mail quando a entrevista for marcada.'],
        ResearchApplication::STATUS_INTERVIEW => ['sky', 'Sua reunião está marcada. Os detalhes estão logo abaixo.'],
        ResearchApplication::STATUS_INTERVIEWED => ['indigo', 'Reunião feita! Agora é aguardar o resultado, que chega por e-mail.'],
        ResearchApplication::STATUS_APPROVED => ['emerald', 'Você foi aprovado(a)! Siga os próximos passos abaixo.'],
        ResearchApplication::STATUS_WAITLIST => ['slate', 'Você está na lista de espera. Se abrir uma vaga, você é chamado(a) por e-mail.'],
        ResearchApplication::STATUS_REJECTED => ['rose', 'Desta vez você não foi aprovado(a). Obrigado pelo interesse! Novas chamadas são abertas todo ano.'],
        ResearchApplication::STATUS_WITHDRAWN => ['slate', 'A sua desistência foi registrada.'],
        ResearchApplication::STATUS_REMOVED => ['rose', 'Você foi desligado(a) do projeto. O motivo está no e-mail que você recebeu. As obrigações de confidencialidade do termo continuam valendo.'],
        default => ['slate', $application->statusLabel()],
    };
    $tone = [
        'amber' => 'border-amber-400/40 bg-amber-400/10 text-amber-100',
        'sky' => 'border-sky-400/40 bg-sky-400/10 text-sky-100',
        'indigo' => 'border-indigo-400/40 bg-indigo-400/10 text-indigo-100',
        'emerald' => 'border-emerald-400/40 bg-emerald-400/10 text-emerald-100',
        'slate' => 'border-slate-400/30 bg-slate-400/10 text-slate-200',
        'rose' => 'border-rose-400/40 bg-rose-500/10 text-rose-100',
    ][$banner[0]];
@endphp
@extends('research-call._layout', ['pageTitle' => 'Acompanhar inscrição'])

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6">
    <div class="text-center">
        <span class="text-xs font-bold text-brand-teal uppercase tracking-widest font-outfit">Acompanhar inscrição</span>
        <h1 class="font-outfit font-extrabold text-2xl sm:text-3xl text-white mt-1">Olá, {{ $application->firstName() }}!</h1>
        <p class="text-slate-400 mt-2 text-sm">{{ $call->title }} · inscrição de {{ $application->created_at->format('d/m/Y') }}</p>
    </div>

    @if(session('adhesion_ok'))
        <div class="mt-8 rounded-2xl border border-emerald-400/40 bg-emerald-400/10 px-5 py-4 text-sm text-emerald-100">
            <strong class="block">Aceite registrado.</strong> Enviamos o comprovante para o seu e-mail.
        </div>
    @endif

    @if(!$application->adhesion_sha256 && !$application->isClosed())
        <form method="POST" action="{{ route('research-call.adhesion', request()->route('token')) }}" class="mt-8 rounded-3xl border border-amber-400/40 bg-amber-400/10 p-6">
            @csrf
            <h2 class="font-outfit font-bold text-lg text-white">Falta o seu aceite do termo de adesão</h2>
            <p class="text-sm text-amber-100 mt-1">A sua inscrição foi feita antes deste termo existir. Leia e aceite para que ela possa ser aprovada. O texto exato fica gravado com data e hora, endereço IP, navegador e uma impressão digital (SHA-256), e você recebe uma cópia por e-mail.</p>
            <div class="mt-4 max-h-72 overflow-y-auto rounded-xl border border-white/10 bg-dark-950/80 p-4 text-xs leading-relaxed text-slate-300 whitespace-pre-line" tabindex="0">{{ $call->adhesionText($application->name, $application->email, '[data e hora do aceite]') }}</div>
            <label class="mt-4 flex gap-3 items-start text-sm text-white cursor-pointer">
                <input type="checkbox" name="adhesion" value="1" required class="mt-1 accent-teal-500">
                <span><strong>Li e aceito o termo de adesão</strong>, inclusive a natureza voluntária da participação (mentoria, sem bolsa, sem horário fixo e sem vínculo empregatício), a proibição de delegar a triagem à inteligência artificial e o tratamento dos meus dados conforme a LGPD.</span>
            </label>
            @error('adhesion')<p class="text-xs text-rose-300 mt-2">{{ $message }}</p>@enderror
            <button type="submit" class="mt-4 inline-flex px-6 py-3 rounded-xl bg-gradient-to-r from-brand-teal to-brand-indigo text-white font-bold hover:brightness-110 transition">Aceitar o termo de adesão</button>
        </form>
    @endif

    <div class="mt-8 rounded-2xl border px-5 py-4 text-sm {{ $tone }}">
        <strong class="block">{{ $application->statusLabel() }}</strong>
        {{ $banner[1] }}
    </div>

    @if($status === ResearchApplication::STATUS_INTERVIEW && $application->interview_at)
        <div class="mt-4 rounded-2xl border border-white/10 bg-white/[0.03] p-5 text-sm">
            <div class="text-white font-semibold">📅 {{ ucfirst($application->interview_at->translatedFormat('l, d/m/Y \à\s H:i')) }}</div>
            <div class="text-slate-300 mt-1 break-words">📍 {{ $application->interview_place }}</div>
            <p class="text-slate-400 mt-3">A conversa leva de 5 a 10 minutos: disponibilidade semanal, leitura em inglês, o seu objetivo e o compromisso com os prazos da dupla. Se não puder nesse horário, responda o e-mail da entrevista.</p>
        </div>
    @endif

    @if($status === ResearchApplication::STATUS_APPROVED)
        <div class="mt-4 rounded-2xl border border-white/10 bg-white/[0.03] p-5 text-sm space-y-2">
            <div><span class="text-slate-400">Líder (1º autor):</span> <span class="font-mono font-bold text-brand-teal">{{ $application->lead_project_code }}</span> <span class="text-slate-200">{{ $projects[$application->lead_project_code]['area'] ?? '' }}</span></div>
            @if($application->reviewer_project_code)
                <div><span class="text-slate-400">Revisor(a) cego(a) (coautor):</span> <span class="font-mono font-bold text-indigo-300">{{ $application->reviewer_project_code }}</span> <span class="text-slate-200">{{ $projects[$application->reviewer_project_code]['area'] ?? '' }}</span></div>
            @endif
            <p class="text-slate-400 pt-1">O título real aparece no Portal do Aluno depois que você assina o termo de confidencialidade em "Triagem Cega".</p>
            <a href="{{ url('/portal') }}" class="inline-flex mt-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-brand-teal to-brand-indigo text-white font-bold hover:brightness-110 transition">Abrir o Portal do Aluno</a>
            <p class="text-xs text-slate-500">Ainda não tem login? Use o link do convite que chegou no e-mail do resultado (vale por 30 dias).</p>
        </div>
    @endif

    @if(filled($application->decision_message) && in_array($status, [ResearchApplication::STATUS_APPROVED, ResearchApplication::STATUS_WAITLIST, ResearchApplication::STATUS_REJECTED], true))
        <div class="mt-4 rounded-2xl border border-white/10 bg-white/[0.03] p-5 text-sm text-slate-300">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Recado do Prof. Atilio</div>
            {!! nl2br(e($application->decision_message)) !!}
        </div>
    @endif

    @if($steps->isNotEmpty() && !$stopped)
        <div class="mt-10 rounded-3xl border border-white/10 bg-white/[0.03] p-6 sm:p-8">
            <h2 class="font-outfit font-bold text-lg text-white">Suas etapas</h2>
            <ol class="mt-6 relative">
                @foreach($steps as $i => $step)
                    @php $state = $i < $current ? 'done' : ($i === $current ? 'now' : 'next'); @endphp
                    <li class="flex gap-4 pb-6 last:pb-0 relative">
                        @if(!$loop->last)<span class="absolute left-[1.05rem] top-9 bottom-0 w-px {{ $state === 'done' ? 'bg-brand-teal/60' : 'bg-white/10' }}" aria-hidden="true"></span>@endif
                        <span class="relative shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold
                            {{ $state === 'done' ? 'bg-brand-teal text-dark-950' : ($state === 'now' ? 'bg-gradient-to-br from-brand-teal to-brand-indigo text-white ring-4 ring-brand-teal/25' : 'bg-dark-900 border border-white/15 text-slate-500') }}">
                            {{ $state === 'done' ? '✓' : $i + 1 }}
                        </span>
                        <div class="pt-1.5">
                            <div class="flex flex-wrap items-baseline gap-x-2">
                                <span class="font-semibold {{ $state === 'next' ? 'text-slate-400' : 'text-white' }}">{{ $step['title'] }}</span>
                                @if($state === 'now')<span class="text-[0.65rem] font-bold uppercase tracking-wider text-brand-teal bg-brand-teal/10 rounded-full px-2 py-0.5">Você está aqui</span>@endif
                                @if(filled($step['when'] ?? null))<span class="text-xs text-slate-500">{{ $step['when'] }}</span>@endif
                            </div>
                            @if(filled($step['text'] ?? null) && $state !== 'done')<p class="text-sm {{ $state === 'now' ? 'text-slate-300' : 'text-slate-500' }} mt-1 leading-relaxed">{{ $step['text'] }}</p>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    @if($application->adhesion_sha256)
        <details class="mt-6 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-4 text-sm">
            <summary class="cursor-pointer font-semibold text-white">Comprovante do aceite do termo de adesão</summary>
            <dl class="mt-3 grid gap-1 text-slate-300">
                <div><dt class="inline text-slate-500">E-mail:</dt> <dd class="inline">{{ $application->email }} · <span class="text-emerald-300">confirmado em {{ $application->email_verified_at?->format('d/m/Y H:i') }}</span></dd></div>
                <div><dt class="inline text-slate-500">Aceite:</dt> <dd class="inline">{{ $application->adhesion_accepted_at?->format('d/m/Y H:i:s') }} (horário de Brasília) · IP {{ $application->ip ?: '—' }}</dd></div>
                <div class="break-all"><dt class="inline text-slate-500">SHA-256 do texto:</dt> <dd class="inline font-mono text-xs">{{ $application->adhesion_sha256 }}</dd></div>
            </dl>
            <div class="mt-3 max-h-64 overflow-y-auto rounded-xl border border-white/10 bg-dark-950/80 p-4 text-xs leading-relaxed text-slate-400 whitespace-pre-line">{{ $application->adhesion_text }}</div>
        </details>
    @endif

    <p class="text-center text-xs text-slate-500 mt-8">Este link é pessoal. Dúvidas? Escreva para <a href="mailto:contato@profatilio.com.br" class="text-brand-teal">contato@profatilio.com.br</a>. · <a href="{{ $call->publicUrl() }}" class="text-brand-teal">Página da seleção</a></p>
</div>
@endsection
