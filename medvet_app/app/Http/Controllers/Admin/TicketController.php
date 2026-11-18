<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Mensagem;
use App\Models\Ticket;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'ativos');

        $query = Ticket::with('cliente', 'ultimaMensagem')->withMax('mensagens', 'created_at');

        match ($filter) {
            'lixeira' => $query->onlyTrashed(),
            'arquivados' => $query->whereNotNull('archived_at'),
            default => $query->whereNull('archived_at'),
        };

        $tickets = $query->orderByDesc('mensagens_max_created_at')
            ->paginate(20)
            ->withQueryString();

        $pendentesNotificacao = Ticket::where('status', '!=', 'fechado')
            ->whereNull('archived_at')
            ->with('ultimaMensagem')
            ->get()
            ->filter(fn (Ticket $t) => $t->ultimaMensagem && $t->ultimaMensagem->enviado_por_admin)
            ->count();

        return view('admin.tickets.index', compact('tickets', 'filter', 'pendentesNotificacao'));
    }

    public function create()
    {
        return view('admin.tickets.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'cpf_cnpj' => 'nullable|string|max:20',
            'titulo' => 'required|string|max:255',
            'descricao' => 'required|string',
            'prioridade' => 'required|in:baixa,media,alta,urgente',
        ]);

        // Criar ou encontrar cliente
        $cliente = Cliente::firstOrCreate(
            ['email' => $validated['email']],
            [
                'nome' => $validated['nome'],
                'telefone' => $validated['telefone'] ?? null,
                'cpf_cnpj' => $validated['cpf_cnpj'] ?? null,
            ]
        );

        // Criar ticket
        $ticket = Ticket::create([
            'cliente_id' => $cliente->id,
            'titulo' => $validated['titulo'],
            'descricao' => $validated['descricao'],
            'prioridade' => $validated['prioridade'],
            'status' => 'novo',
        ]);

        // Criar primeira mensagem com a descrição
        Mensagem::create([
            'ticket_id' => $ticket->id,
            'conteudo' => $validated['descricao'],
            'enviado_por_admin' => false,
        ]);

        return redirect()->route('admin.tickets.show', $ticket)
            ->with('success', 'Ticket #' . $ticket->id . ' criado com sucesso!');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load('cliente', 'mensagens.user');
        return view('admin.tickets.show', compact('ticket'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'status' => 'required|in:novo,analise,orcado,aceito,recusado,fechado',
            'prioridade' => 'required|in:baixa,media,alta,urgente',
            'valor_orcado' => 'nullable|numeric|min:0',
        ]);

        if ($validated['status'] !== $ticket->status) {
            $validated['orcamento_lembrete_enviado_at'] = null;
            $validated['resgate_enviado_at'] = null;
        }

        $ticket->update($validated);

        if ($ticket->wasChanged('status') || $ticket->wasChanged('valor_orcado')) {
            $this->notificarClienteAtualizacao($ticket);
        }

        return back()->with('success', 'Ticket atualizado!');
    }

    public function mensagem(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'conteudo' => 'required|string',
            'anexo' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $conteudoLimpo = HtmlSanitizer::clean($validated['conteudo']);

        if (trim(strip_tags($conteudoLimpo)) === '' && !$request->hasFile('anexo')) {
            return back()->withErrors(['conteudo' => 'Digite uma mensagem antes de enviar.'])->withInput();
        }

        $mensagem = new Mensagem([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'conteudo' => $conteudoLimpo,
            'is_html' => true,
            'enviado_por_admin' => true,
        ]);

        if ($request->hasFile('anexo')) {
            $path = $request->file('anexo')->store('anexos', 'public');
            $mensagem->anexo = $path;
        }

        $mensagem->save();

        if ($ticket->status === 'novo') {
            $ticket->update(['status' => 'analise']);
        } elseif ($ticket->status === 'orcado' && ($ticket->orcamento_lembrete_enviado_at || $ticket->resgate_enviado_at)) {
            // Admin voltou a se engajar: reinicia a contagem de lembrete/resgate.
            $ticket->update(['orcamento_lembrete_enviado_at' => null, 'resgate_enviado_at' => null]);
        }

        $this->notificarClienteResposta($ticket, $mensagem);

        return back()->with('success', 'Resposta enviada!');
    }

    public function reenviarNotificacao(Ticket $ticket)
    {
        $ultima = $ticket->ultimaMensagem;

        if (!$ultima || !$ultima->enviado_por_admin) {
            return back()->with('error', 'Este ticket não possui uma resposta da equipe para reenviar.');
        }

        $this->notificarClienteResposta($ticket, $ultima);

        return back()->with('success', "Notificação reenviada para {$ticket->cliente->email}!");
    }

    public function reenviarNotificacoesPendentes()
    {
        $tickets = Ticket::where('status', '!=', 'fechado')
            ->whereNull('archived_at')
            ->with(['cliente', 'ultimaMensagem'])
            ->get()
            ->filter(fn (Ticket $t) => $t->ultimaMensagem && $t->ultimaMensagem->enviado_por_admin);

        foreach ($tickets as $ticket) {
            $this->notificarClienteResposta($ticket, $ticket->ultimaMensagem);
        }

        $total = $tickets->count();

        return back()->with('success', $total > 0
            ? "Notificação reenviada para {$total} ticket(s) aguardando retorno do cliente."
            : 'Nenhum ticket com resposta pendente de notificação no momento.');
    }

    public function close(Ticket $ticket)
    {
        $ticket->update(['status' => 'fechado']);

        return back()->with('success', 'Ticket encerrado!');
    }

    public function reopen(Ticket $ticket)
    {
        $ticket->update(['status' => 'analise']);

        return back()->with('success', 'Ticket reaberto!');
    }

    public function archive(Ticket $ticket)
    {
        $ticket->update(['archived_at' => now()]);

        return back()->with('success', 'Ticket arquivado!');
    }

    public function unarchive(Ticket $ticket)
    {
        $ticket->update(['archived_at' => null]);

        return back()->with('success', 'Ticket desarquivado!');
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket movido para a lixeira.');
    }

    public function restore(Ticket $ticket)
    {
        $ticket->restore();

        return back()->with('success', 'Ticket restaurado!');
    }

    public function forceDelete(Ticket $ticket)
    {
        $ticket->forceDelete();

        return redirect()->route('admin.tickets.index', ['filter' => 'lixeira'])
            ->with('success', 'Ticket excluído definitivamente.');
    }

    private function fromAddress(): array
    {
        $fromAddress = config('mail.from.address') === 'hello@example.com' ? 'no-reply@medvetlegal.com.br' : config('mail.from.address');
        $fromName = config('mail.from.name') === 'Laravel' ? 'MedVetLegal' : config('mail.from.name');

        return [$fromAddress, $fromName];
    }

    private function notificarClienteResposta(Ticket $ticket, Mensagem $mensagem): void
    {
        $portalUrl = url("/tickets/acompanhar/{$ticket->token}");
        $subject = "Nova resposta da MedVetLegal: Ticket #{$ticket->id} - " . e($ticket->titulo);

        $anexoHtml = $mensagem->anexo
            ? "<p><strong>Anexo enviado pela equipe:</strong> <a href='" . asset('storage/' . $mensagem->anexo) . "' target='_blank'>Clique aqui para baixar o PDF</a></p>"
            : '';

        $body = "
        <html>
        <head><title>Nova Resposta - MedVetLegal</title></head>
        <body style='font-family: Arial, sans-serif; color: #333;'>
          <h2 style='color: #0e2a47;'>Olá, " . e($ticket->cliente->nome) . ". Você recebeu uma nova resposta!</h2>
          <p>Nossa equipe respondeu ao seu Ticket <strong>#{$ticket->id} (" . e($ticket->titulo) . ")</strong>.</p>
          <div style='background-color: #f9f9f9; border-left: 4px solid #c7a006; padding: 15px; margin: 20px 0;'>
            {$mensagem->conteudo}
          </div>
          {$anexoHtml}
          <p style='margin: 25px 0;'>
            <a href='{$portalUrl}' style='background-color: #0e2a47; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>
              Acessar Meu Portal de Acompanhamento
            </a>
          </p>
          <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
          <p>Atenciosamente,</p>
          <p><strong>Corpo Técnico da MedVetLegal</strong></p>
        </body>
        </html>
        ";

        try {
            [$fromAddress, $fromName] = $this->fromAddress();

            Mail::html($body, function ($message) use ($ticket, $subject, $fromAddress, $fromName) {
                $message->to($ticket->cliente->email)
                        ->from($fromAddress, $fromName)
                        ->subject($subject);
            });
        } catch (\Exception $e) {
            logger()->warning("Falha ao enviar notificação de resposta para o ticket #{$ticket->id}: " . $e->getMessage());
        }
    }

    private function notificarClienteAtualizacao(Ticket $ticket): void
    {
        $statusLabels = [
            'novo' => 'Novo',
            'analise' => 'Em Análise',
            'orcado' => 'Orçado',
            'aceito' => 'Aceito',
            'recusado' => 'Recusado',
            'fechado' => 'Fechado',
        ];

        $portalUrl = url("/tickets/acompanhar/{$ticket->token}");

        if ($ticket->status === 'fechado') {
            $subject = "Caso Encerrado: Ticket #{$ticket->id} - " . e($ticket->titulo);
            $body = "
            <html>
            <head><title>Caso Encerrado - MedVetLegal</title></head>
            <body style='font-family: Arial, sans-serif; color: #333;'>
              <h2 style='color: #0e2a47;'>Olá, " . e($ticket->cliente->nome) . ". Seu caso foi encerrado.</h2>
              <p>O atendimento referente ao Ticket <strong>#{$ticket->id} (" . e($ticket->titulo) . ")</strong> foi concluído pela nossa equipe técnica.</p>
              <p>Todo o histórico de mensagens e documentos trocados permanece disponível no seu portal de acompanhamento.</p>
              <p style='margin: 25px 0;'>
                <a href='{$portalUrl}' style='background-color: #0e2a47; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>
                  Ver Histórico Completo do Caso
                </a>
              </p>
              <p>Caso surja alguma nova necessidade ou dúvida sobre este atendimento, é só responder por esse mesmo portal que reabrimos o caso.</p>
              <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
              <p>Agradecemos a confiança em nosso trabalho.</p>
              <p>Atenciosamente,</p>
              <p><strong>Corpo Técnico da MedVetLegal</strong><br>
              Dra. Adriana de Siqueira<br>
              Dr. Atilio Sersun Calefi</p>
            </body>
            </html>
            ";
        } elseif ($ticket->status === 'orcado' && $ticket->valor_orcado) {
            $subject = "Orçamento Disponível: Ticket #{$ticket->id} - " . e($ticket->titulo);
            $valor = number_format($ticket->valor_orcado, 2, ',', '.');
            $body = "
            <html>
            <head><title>Orçamento Disponível - MedVetLegal</title></head>
            <body style='font-family: Arial, sans-serif; color: #333;'>
              <h2 style='color: #0e2a47;'>Olá, " . e($ticket->cliente->nome) . ". Seu orçamento está pronto!</h2>
              <p>Preparamos a proposta de honorários para o seu Ticket <strong>#{$ticket->id} (" . e($ticket->titulo) . ")</strong>.</p>
              <div style='background-color: #f9f9f9; border-left: 4px solid #c7a006; padding: 15px; margin: 20px 0;'>
                <p style='margin: 0;'><strong>Valor Proposto:</strong> R$ {$valor}</p>
              </div>
              <p>Acesse o portal para visualizar os detalhes e aceitar ou recusar a proposta.</p>
              <p style='margin: 25px 0;'>
                <a href='{$portalUrl}' style='background-color: #0e2a47; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>
                  Ver Proposta e Decidir
                </a>
              </p>
              <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
              <p>Atenciosamente,</p>
              <p><strong>Corpo Técnico da MedVetLegal</strong></p>
            </body>
            </html>
            ";
        } else {
            $subject = "Atualização no seu Ticket #{$ticket->id} - " . e($ticket->titulo);
            $statusLabel = $statusLabels[$ticket->status] ?? ucfirst($ticket->status);
            $body = "
            <html>
            <head><title>Atualização de Ticket - MedVetLegal</title></head>
            <body style='font-family: Arial, sans-serif; color: #333;'>
              <h2 style='color: #0e2a47;'>Olá, " . e($ticket->cliente->nome) . ". Seu ticket foi atualizado!</h2>
              <p>O status do seu Ticket <strong>#{$ticket->id} (" . e($ticket->titulo) . ")</strong> agora é: <strong>{$statusLabel}</strong>.</p>
              <p style='margin: 25px 0;'>
                <a href='{$portalUrl}' style='background-color: #0e2a47; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>
                  Acessar Meu Portal de Acompanhamento
                </a>
              </p>
              <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
              <p>Atenciosamente,</p>
              <p><strong>Corpo Técnico da MedVetLegal</strong></p>
            </body>
            </html>
            ";
        }

        try {
            [$fromAddress, $fromName] = $this->fromAddress();

            Mail::html($body, function ($message) use ($ticket, $subject, $fromAddress, $fromName) {
                $message->to($ticket->cliente->email)
                        ->from($fromAddress, $fromName)
                        ->subject($subject);
            });
        } catch (\Exception $e) {
            logger()->warning("Falha ao enviar notificação de atualização para o ticket #{$ticket->id}: " . $e->getMessage());
        }
    }
}
