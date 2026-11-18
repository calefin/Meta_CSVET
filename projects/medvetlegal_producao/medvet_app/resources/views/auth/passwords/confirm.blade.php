@extends('layouts.auth')

@section('title', 'Confirmar Senha')

@section('content')
<h2>Confirmar Senha</h2>

<p class="auth-intro">Por segurança, confirme sua senha antes de continuar.</p>

<form method="POST" action="{{ route('password.confirm') }}">
    @csrf

    <div class="mb-3">
        <label for="password" class="form-label">Senha</label>
        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn-auth">Confirmar</button>

    @if (Route::has('password.request'))
        <div class="auth-links">
            <a href="{{ route('password.request') }}">Esqueceu sua senha?</a>
        </div>
    @endif
</form>
@endsection
