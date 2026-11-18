@extends('layouts.admin')

@section('title', 'Clientes')
@section('header')
    <h1>Clientes <a href="{{ route('admin.clientes.create') }}" class="btn btn-primary btn-sm float-right"><i class="fas fa-plus"></i> Novo Cliente</a></h1>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <form method="GET" action="{{ route('admin.clientes.index') }}" class="form-inline">
            <input type="text" name="busca" value="{{ $busca }}" class="form-control mr-2" placeholder="Buscar por nome, email ou CPF/CNPJ" style="min-width: 300px;">
            <button type="submit" class="btn btn-outline-secondary"><i class="fas fa-search"></i> Buscar</button>
            @if($busca)
                <a href="{{ route('admin.clientes.index') }}" class="btn btn-link">Limpar</a>
            @endif
        </form>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Telefone</th>
                    <th>CPF/CNPJ</th>
                    <th>Tickets</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientes as $cliente)
                <tr>
                    <td>{{ $cliente->id }}</td>
                    <td>{{ $cliente->nome }}</td>
                    <td>{{ $cliente->email }}</td>
                    <td>{{ $cliente->telefone ?: '-' }}</td>
                    <td>{{ $cliente->cpf_cnpj ?: '-' }}</td>
                    <td><span class="badge badge-secondary">{{ $cliente->tickets_count }}</span></td>
                    <td>
                        <a href="{{ route('admin.clientes.show', $cliente) }}" class="btn btn-sm btn-info" title="Ver"><i class="fas fa-eye"></i></a>
                        <a href="{{ route('admin.clientes.edit', $cliente) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-edit"></i></a>
                        <form action="{{ route('admin.clientes.destroy', $cliente) }}" method="POST" class="d-inline" onsubmit="return confirm('Excluir este cliente?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">Nenhum cliente encontrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        {{ $clientes->links() }}
    </div>
</div>
@endsection
