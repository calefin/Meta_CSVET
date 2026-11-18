@extends('layouts.admin')

@section('title', 'Tickets')
@section('header')
    <h1>
        Tickets
        <div class="float-right">
            @if($pendentesNotificacao > 0)
                <form action="{{ route('admin.tickets.reenviar-notificacoes-pendentes') }}" method="POST" class="d-inline" onsubmit="return confirm('Isso vai reenviar o e-mail de aviso de resposta para {{ $pendentesNotificacao }} cliente(s) que ainda não deram retorno. Continuar?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning btn-sm"><i class="fas fa-paper-plane"></i> Reenviar Notificações Pendentes ({{ $pendentesNotificacao }})</button>
                </form>
            @endif
            <a href="{{ route('admin.tickets.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Novo Ticket</a>
        </div>
    </h1>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
@endif

<ul class="nav nav-tabs mb-0">
    <li class="nav-item">
        <a class="nav-link {{ $filter == 'ativos' ? 'active' : '' }}" href="{{ route('admin.tickets.index', ['filter' => 'ativos']) }}">Ativos</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $filter == 'arquivados' ? 'active' : '' }}" href="{{ route('admin.tickets.index', ['filter' => 'arquivados']) }}">Arquivados</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $filter == 'lixeira' ? 'active' : '' }}" href="{{ route('admin.tickets.index', ['filter' => 'lixeira']) }}">Lixeira</a>
    </li>
</ul>

<div class="card" style="border-top-left-radius: 0;">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>Título</th>
                    <th>Status</th>
                    <th>Prioridade</th>
                    <th>Valor</th>
                    <th>Última Atividade</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                <tr>
                    <td>{{ $ticket->id }}</td>
                    <td>{{ $ticket->cliente->nome }}</td>
                    <td>{{ Str::limit($ticket->titulo, 40) }}</td>
                    <td>
                        @if($ticket->status === 'orcado' && $ticket->resgate_enviado_at && !$ticket->clienteRespondeuDesde($ticket->resgate_enviado_at))
                            <span class="badge badge-danger" title="Enviamos uma tentativa de resgate e o cliente ainda não respondeu">
                                <i class="fas fa-life-ring mr-1"></i>Tentativa de Resgate
                            </span>
                        @elseif($ticket->status === 'orcado' && $ticket->orcamento_lembrete_enviado_at && !$ticket->clienteRespondeuDesde($ticket->orcamento_lembrete_enviado_at))
                            <span class="badge badge-warning" title="Lembrete enviado, aguardando decisão do cliente">
                                <i class="fas fa-clock mr-1"></i>Orçado (Parado)
                            </span>
                        @else
                            <span class="badge badge-{{
                                $ticket->status == 'novo' ? 'info' :
                                ($ticket->status == 'analise' ? 'warning' :
                                ($ticket->status == 'orcado' ? 'primary' :
                                ($ticket->status == 'aceito' ? 'success' :
                                ($ticket->status == 'recusado' ? 'danger' : 'secondary'))))
                            }}">
                                {{ ucfirst($ticket->status) }}
                            </span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{
                            $ticket->prioridade == 'urgente' ? 'danger' :
                            ($ticket->prioridade == 'alta' ? 'warning' :
                            ($ticket->prioridade == 'media' ? 'info' : 'success'))
                        }}">
                            {{ ucfirst($ticket->prioridade) }}
                        </span>
                    </td>
                    <td>{{ $ticket->valor_orcado ? 'R$ ' . number_format($ticket->valor_orcado, 2, ',', '.') : '-' }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($ticket->mensagens_max_created_at ?? $ticket->created_at)->format('d/m/Y H:i') }}</td>
                    <td>
                        @if($filter == 'lixeira')
                            <form action="{{ route('admin.tickets.restore', $ticket) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Restaurar"><i class="fas fa-trash-restore"></i></button>
                            </form>
                            <form action="{{ route('admin.tickets.force-delete', $ticket) }}" method="POST" class="d-inline" onsubmit="return confirm('ATENÇÃO: isso apaga o ticket, as mensagens e os anexos definitivamente. Esta ação não pode ser desfeita. Confirmar?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir definitivamente"><i class="fas fa-times"></i></button>
                            </form>
                        @else
                            <a href="{{ route('admin.tickets.show', $ticket) }}" class="btn btn-sm btn-info" title="Ver"><i class="fas fa-eye"></i></a>

                            @if($ticket->ultimaMensagem && !$ticket->ultimaMensagem->enviado_por_admin && $ticket->status !== 'fechado')
                                <a href="{{ route('admin.tickets.show', $ticket) }}#responder" class="btn btn-sm btn-danger" title="Responder"><i class="fas fa-reply"></i></a>
                            @endif

                            @if($ticket->status !== 'fechado')
                                <form action="{{ route('admin.tickets.close', $ticket) }}" method="POST" class="d-inline" onsubmit="return confirm('Encerrar este ticket?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Encerrar"><i class="fas fa-lock"></i></button>
                                </form>
                            @else
                                <form action="{{ route('admin.tickets.reopen', $ticket) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Reabrir"><i class="fas fa-lock-open"></i></button>
                                </form>
                            @endif

                            @if($filter == 'arquivados')
                                <form action="{{ route('admin.tickets.unarchive', $ticket) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Desarquivar"><i class="fas fa-box-open"></i></button>
                                </form>
                            @else
                                <form action="{{ route('admin.tickets.archive', $ticket) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Arquivar"><i class="fas fa-archive"></i></button>
                                </form>
                            @endif

                            <form action="{{ route('admin.tickets.destroy', $ticket) }}" method="POST" class="d-inline" onsubmit="return confirm('Mover este ticket para a lixeira?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="fas fa-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">Nenhum ticket encontrado nesta aba.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        {{ $tickets->links() }}
    </div>
</div>
@endsection
