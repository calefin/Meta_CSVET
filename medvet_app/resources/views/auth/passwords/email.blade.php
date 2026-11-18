@extends('layouts.auth')

@section('title', 'Recuperar Senha')

@section('content')
<h2>Recuperar Senha</h2>

<p class="auth-intro">Informe seu e-mail e enviaremos um link para você redefinir sua senha.</p>

@if (session('status'))
    <div class="alert alert-success py-2 px-3 small mb-3">
        {{ session('status') }}
    </div>
@endif

<form method="POST" action="{{ route('password.email') }}">
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label">E-mail</label>
        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn-auth">Enviar Link de Redefinição</button>

    <div class="auth-links">
        <a href="{{ route('login') }}">Voltar para o login</a>
    </div>
</form>
@endsection
