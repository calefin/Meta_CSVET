@extends('layouts.auth')

@section('title', 'Entrar')

@section('content')
<h2>Entrar</h2>

@if ($errors->any())
    <div class="alert alert-danger py-2 px-3 small mb-3">
        {{ $errors->first() }}
    </div>
@endif

<form method="POST" action="{{ route('login') }}">
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label">E-mail</label>
        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Senha</label>
        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3 form-check">
        <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
        <label class="form-check-label" for="remember">Manter conectado</label>
    </div>

    <button type="submit" class="btn-auth">Entrar</button>

    @if (Route::has('password.request'))
        <div class="auth-links">
            <a href="{{ route('password.request') }}">Esqueceu sua senha?</a>
        </div>
    @endif
</form>
@endsection
