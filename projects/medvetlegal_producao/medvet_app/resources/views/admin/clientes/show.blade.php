@extends('layouts.admin')

@section('title', 'Cliente: ' . $cliente->nome)
@section('header')
    <h1>
        {{ $cliente->nome }}
        <div class="float-right">
            <a href="{{ route('admin.clientes.edit', $cliente) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-edit"></i> Editar</a>
            <a href="{{ route('admin.clientes.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </h1>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Dados do Cliente</h3>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-2">Email</dt>
            <dd class="col-sm-10">{{ $cliente->email }}</dd>

            <dt class="col-sm-2">Telefone</dt>
            <dd class="col-sm-10">{{ $cliente->telefone ?: '-' }}</dd>

            <dt class="col-sm-2">CPF/CNPJ</dt>
            <dd class="col-sm-10">{{ $cliente->cpf_cnpj ?: '-' }}</dd>

            <dt class="col-sm-2">Cadastrado em</dt>
            <dd class="col-sm-10">{{ $cliente->created_at->format('d/m/Y H:i') }}</dd>
        </dl>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Tickets ({{ $cliente->tickets->count() }})</h3>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Título</th>
                    <th>Status</th>
                    <th>Prioridade</th>
                    <th>Criado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cliente->tickets as $ticket)
                <tr>
                    <td>{{ $ticket->id }}</td>
                    <td>{{ Str::limit($ticket->titulo, 40) }}</td>
                    <td><span class="badge badge-secondary">{{ ucfirst($ticket->status) }}</span></td>
                    <td>{{ ucfirst($ticket->prioridade) }}</td>
                    <td>{{ $ticket->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="btn btn-sm btn-info" title="Ver"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Nenhum ticket para este cliente.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
