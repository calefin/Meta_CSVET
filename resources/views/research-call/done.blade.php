@extends('research-call._layout', ['pageTitle' => 'Inscrição enviada'])

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6">
    <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-8 text-center">
        <div class="mx-auto w-16 h-16 rounded-full bg-emerald-500/15 border border-emerald-400/30 flex items-center justify-center text-3xl" aria-hidden="true">✓</div>
        @if($duplicate ?? false)
            <h1 class="font-outfit font-extrabold text-2xl text-white mt-5">Você já está inscrito(a)</h1>
            <p class="text-slate-300 mt-3 text-sm leading-relaxed">Este e-mail já tinha uma inscrição nesta seleção. Reenviamos para <strong class="text-white">{{ $application->email }}</strong> o e-mail com o link para acompanhar (e confirmar) a sua inscrição.</p>
        @else
            <h1 class="font-outfit font-extrabold text-2xl text-white mt-5">Inscrição enviada!</h1>
            <p class="text-slate-300 mt-3 text-sm leading-relaxed">
                @if($application)
                    Enviamos para <strong class="text-white">{{ $application->email }}</strong> um e-mail com o comprovante do seu aceite e o botão <strong class="text-white">"Acompanhar minha inscrição"</strong>. <strong class="text-amber-300">Clique nele para confirmar o seu e-mail:</strong> a inscrição só segue com o e-mail confirmado. Se não encontrar, procure no spam ou lixo eletrônico.
                @else
                    Recebemos o seu envio.
                @endif
            </p>
        @endif

        <div class="mt-8 text-left rounded-2xl border border-white/10 bg-dark-950/60 p-5">
            <h2 class="font-semibold text-white text-sm">O que acontece agora</h2>
            <ol class="mt-3 space-y-2 text-sm text-slate-300 list-decimal pl-5">
                <li>Abra o e-mail que acabamos de enviar e clique em "Acompanhar minha inscrição" para confirmar o seu e-mail.</li>
                <li>O Prof. Atilio confere os requisitos da sua inscrição.</li>
                <li>Se estiver tudo certo, você recebe por e-mail a data e o link de uma reunião rápida (5 a 10 minutos).</li>
                <li>Depois da reunião sai o resultado, com os códigos dos projetos em que você vai trabalhar.</li>
            </ol>
        </div>

        <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ $call->publicUrl() }}#passo-a-passo" class="inline-flex justify-center px-6 py-3 rounded-xl border border-white/15 text-slate-200 font-semibold hover:bg-white/5 transition">Rever o passo a passo</a>
        </div>
    </div>
</div>
@endsection
