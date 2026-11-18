@extends('layouts.auth')

@section('title', 'Confirme seu E-mail')

@section('content')
<h2>Confirme seu E-mail</h2>

@if (session('resent'))
    <div class="alert alert-success py-2 px-3 small mb-3">
        Um novo link de confirmação foi enviado para o seu e-mail.
    </div>
@endif

<p class="auth-intro mb-0">
    Antes de continuar, verifique seu e-mail e clique no link de confirmação que enviamos.
    Se você não recebeu o e-mail,
    <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
        @csrf
        <button type="submit" class="btn btn-link p-0 m-0 align-baseline" style="color: var(--primary-color); font-weight: 700;">clique aqui para reenviar</button>.
    </form>
</p>
@endsection
