@extends('layouts.admin')

@section('title', 'Novo Ticket')
@section('header')
    <h1>Novo Ticket</h1>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.tickets.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nome do Cliente *</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>CPF/CNPJ</label>
                        <input type="text" name="cpf_cnpj" class="form-control">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Título *</label>
                <input type="text" name="titulo" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Descrição *</label>
                <textarea name="descricao" class="form-control" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label>Prioridade</label>
                <select name="prioridade" class="form-control">
                    <option value="baixa">Baixa</option>
                    <option value="media" selected>Média</option>
                    <option value="alta">Alta</option>
                    <option value="urgente">Urgente</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Criar Ticket</button>
            <a href="{{ route('admin.tickets.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection