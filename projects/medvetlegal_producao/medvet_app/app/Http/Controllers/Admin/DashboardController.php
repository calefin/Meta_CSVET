<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Post;
use App\Models\Ticket;

class DashboardController extends Controller
{
    public function index()
    {
        $tickets = Ticket::whereNull('archived_at')
            ->with(['cliente', 'ultimaMensagem'])
            ->get();

        $abertos = $tickets->where('status', '!=', 'fechado');

        $aguardandoResposta = $abertos->filter(function (Ticket $ticket) {
            $ultima = $ticket->ultimaMensagem;
            return $ultima && !$ultima->enviado_por_admin;
        })->sortBy(fn (Ticket $t) => $t->ultimaMensagem->created_at);

        $orcamentosPendentes = $abertos->where('status', 'orcado');

        $valorAceitoMes = $tickets
            ->where('status', 'aceito')
            ->filter(fn (Ticket $t) => $t->updated_at->isCurrentMonth())
            ->sum('valor_orcado');

        $statusCounts = $tickets->countBy('status');

        $recentes = $tickets
            ->sortByDesc(fn (Ticket $t) => optional($t->ultimaMensagem)->created_at ?? $t->created_at)
            ->take(8);

        return view('admin.dashboard', [
            'totalAbertos' => $abertos->count(),
            'aguardandoResposta' => $aguardandoResposta,
            'orcamentosPendentesCount' => $orcamentosPendentes->count(),
            'orcamentosPendentesValor' => $orcamentosPendentes->sum('valor_orcado'),
            'valorAceitoMes' => $valorAceitoMes,
            'urgentesCount' => $abertos->whereIn('prioridade', ['urgente', 'alta'])->count(),
            'statusCounts' => $statusCounts,
            'recentes' => $recentes,
            'totalClientes' => Cliente::count(),
            'artigosPublicados' => Post::published()->count(),
            'artigosRascunho' => Post::whereNull('published_at')->count(),
        ]);
    }
}
