@extends('layouts.admin')

@section('title', 'Meu Perfil')
@section('header')
    <h1>Meu Perfil</h1>
@endsection

@section('content')
<div class="card" style="max-width: 600px;">
    <div class="card-body">
        <form action="{{ route('admin.profile.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>Nome *</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <hr>
            <p class="text-muted small">Preencha os campos abaixo apenas se quiser alterar sua senha.</p>

            <div class="form-group">
                <label>Senha Atual</label>
                <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
                @error('current_password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nova Senha</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Confirmar Nova Senha</label>
                        <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Alterações</button>
        </form>
    </div>
</div>
@endsection
