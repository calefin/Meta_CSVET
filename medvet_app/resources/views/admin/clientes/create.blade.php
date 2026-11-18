@extends('layouts.admin')

@section('title', 'Novo Cliente')
@section('header')
    <h1>Novo Cliente</h1>
@endsection

@section('content')
<div class="card" style="max-width: 700px;">
    <div class="card-body">
        <form action="{{ route('admin.clientes.store') }}" method="POST">
            @csrf
            @include('admin.clientes._form')
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Cadastrar Cliente</button>
            <a href="{{ route('admin.clientes.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
