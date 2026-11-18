@extends('layouts.admin')

@section('title', 'Editar Cliente')
@section('header')
    <h1>Editar Cliente</h1>
@endsection

@section('content')
<div class="card" style="max-width: 700px;">
    <div class="card-body">
        <form action="{{ route('admin.clientes.update', $cliente) }}" method="POST">
            @csrf
            @method('PUT')
            @include('admin.clientes._form')
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Alterações</button>
            <a href="{{ route('admin.clientes.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
