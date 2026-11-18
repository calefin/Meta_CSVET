@extends('research-call._layout', ['pageTitle' => $title])

@section('content')
<div class="max-w-xl mx-auto px-4 sm:px-6">
    <div class="rounded-3xl border border-white/10 bg-white/[0.03] p-8 text-center">
        <div class="mx-auto w-16 h-16 rounded-full bg-brand-teal/10 border border-brand-teal/30 flex items-center justify-center text-3xl" aria-hidden="true">🎓</div>
        <h1 class="font-outfit font-extrabold text-2xl text-white mt-5">{{ $title }}</h1>
        <p class="text-slate-300 mt-3 text-sm leading-relaxed">{{ $message }}</p>
        <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="/" class="inline-flex justify-center px-6 py-3 rounded-xl bg-gradient-to-r from-brand-teal to-brand-indigo text-white font-bold hover:brightness-110 transition">Página inicial</a>
            <a href="/portal" class="inline-flex justify-center px-6 py-3 rounded-xl border border-white/15 text-slate-200 font-semibold hover:bg-white/5 transition">Portal do Aluno</a>
        </div>
    </div>
</div>
@endsection
