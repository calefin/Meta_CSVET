@extends('layouts.admin')

@section('title', 'Dashboard')

@section('header')
    <h1>Dashboard</h1>
@endsection

@php
    $statusLabels = [
        'novo' => 'Novo',
        'analise' => 'Em Análise',
        'orcado' => 'Orçado',
        'aceito' => 'Aceito',
        'recusado' => 'Recusado',
        'fechado' => 'Fechado',
    ];
    $statusColors = [
        'novo' => 'info',
        'analise' => 'warning',
        'orcado' => 'primary',
        'aceito' => 'success',
        'recusado' => 'danger',
        'fechado' => 'secondary',
    ];
    $prioridadeColors = [
        'urgente' => 'danger',
        'alta' => 'warning',
        'media' => 'info',
        'baixa' => 'success',
    ];
@endphp

@section('content')

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $totalAbertos }}</h3>
                <p>Tickets em Aberto</p>
            </div>
            <div class="icon"><i class="fas fa-ticket-alt"></i></div>
            <a href="{{ route('admin.tickets.index') }}" class="small-box-footer">Ver todos <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box {{ $aguardandoResposta->count() > 0 ? 'bg-danger' : 'bg-secondary' }}">
            <div class="inner">
                <h3>{{ $aguardandoResposta->count() }}</h3>
                <p>Aguardando Sua Resposta</p>
            </div>
            <div class="icon"><i class="fas fa-reply"></i></div>
            <a href="#aguardando-resposta" class="small-box-footer">Ver lista <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $orcamentosPendentesCount }}</h3>
                <p>Orçamentos Pendentes{{ $orcamentosPendentesValor > 0 ? ' (R$ ' . number_format($orcamentosPendentesValor, 2, ',', '.') . ')' : '' }}</p>
            </div>
            <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <a href="{{ route('admin.tickets.index') }}" class="small-box-footer">Ver todos <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>R$ {{ number_format($valorAceitoMes, 2, ',', '.') }}</h3>
                <p>Aceito Este Mês</p>
            </div>
            <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
            <a href="{{ route('admin.tickets.index') }}" class="small-box-footer">Ver todos <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Tickets por Status</h3>
            </div>
            <div class="card-body">
                @foreach($statusLabels as $key => $label)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge badge-{{ $statusColors[$key] }}">{{ $label }}</span>
                        <strong>{{ $statusCounts[$key] ?? 0 }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Prioridade e Atenção</h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><i class="fas fa-exclamation-triangle text-warning me-2"></i>Urgentes/Altas em aberto</span>
                    <strong>{{ $urgentesCount }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><i class="fas fa-reply text-danger me-2"></i>Aguardando resposta</span>
                    <strong>{{ $aguardandoResposta->count() }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Orçamentos pendentes</span>
                    <strong>{{ $orcamentosPendentesCount }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Outros Números</h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><i class="fas fa-users text-info me-2"></i>Clientes cadastrados</span>
                    <strong>{{ $totalClientes }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><i class="fas fa-newspaper text-success me-2"></i>Artigos publicados</span>
                    <strong>{{ $artigosPublicados }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-pen text-muted me-2"></i>Artigos em rascunho</span>
                    <strong>{{ $artigosRascunho }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>

@if($aguardandoResposta->count() > 0)
<div class="row" id="aguardando-resposta">
    <div class="col-12">
        <div class="card card-danger card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-reply me-2"></i>Aguardando Sua Resposta</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Título</th>
                            <th>Prioridade</th>
                            <th>Mensagem do Cliente Desde</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($aguardandoResposta as $ticket)
                        <tr>
                            <td>{{ $ticket->id }}</td>
                            <td>{{ $ticket->cliente->nome }}</td>
                            <td>{{ Str::limit($ticket->titulo, 40) }}</td>
                            <td><span class="badge badge-{{ $prioridadeColors[$ticket->prioridade] ?? 'secondary' }}">{{ ucfirst($ticket->prioridade) }}</span></td>
                            <td>{{ $ticket->ultimaMensagem->created_at->diffForHumans() }}</td>
                            <td>
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="btn btn-sm btn-danger"><i class="fas fa-reply me-1"></i>Responder</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Últimos Tickets com Atividade</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Título</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Última Atividade</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentes as $ticket)
                        <tr>
                            <td>{{ $ticket->id }}</td>
                            <td>{{ $ticket->cliente->nome }}</td>
                            <td>{{ Str::limit($ticket->titulo, 40) }}</td>
                            <td>
                                @if($ticket->status === 'orcado' && $ticket->resgate_enviado_at && !$ticket->clienteRespondeuDesde($ticket->resgate_enviado_at))
                                    <span class="badge badge-danger" title="Enviamos uma tentativa de resgate e o cliente ainda não respondeu"><i class="fas fa-life-ring mr-1"></i>Tentativa de Resgate</span>
                                @elseif($ticket->status === 'orcado' && $ticket->orcamento_lembrete_enviado_at && !$ticket->clienteRespondeuDesde($ticket->orcamento_lembrete_enviado_at))
                                    <span class="badge badge-warning" title="Lembrete enviado, aguardando decisão do cliente"><i class="fas fa-clock mr-1"></i>Orçado (Parado)</span>
                                @else
                                    <span class="badge badge-{{ $statusColors[$ticket->status] ?? 'secondary' }}">{{ $statusLabels[$ticket->status] ?? ucfirst($ticket->status) }}</span>
                                @endif
                            </td>
                            <td><span class="badge badge-{{ $prioridadeColors[$ticket->prioridade] ?? 'secondary' }}">{{ ucfirst($ticket->prioridade) }}</span></td>
                            <td>{{ (optional($ticket->ultimaMensagem)->created_at ?? $ticket->created_at)->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Nenhum ticket ainda.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-right">
                <a href="{{ route('admin.tickets.index') }}" class="btn btn-sm btn-outline-primary">Ver todos os tickets <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>
</div>

@endsection
