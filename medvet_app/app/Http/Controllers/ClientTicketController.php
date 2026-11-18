<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Mensagem;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ClientTicketController extends Controller
{
    /**
     * Display the ticket tracking page for the client.
     */
    public function show($token)
    {
        $ticket = Ticket::where('token', $token)
            ->with(['cliente', 'mensagens' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->firstOrFail();

        return view('tickets.show_public', compact('ticket'));
    }

    /**
     * Store a reply message from the client.
     */
    public function mensagem(Request $request, $token)
    {
        $ticket = Ticket::where('token', $token)->firstOrFail();

        $request->validate([
            'conteudo' => 'required|string',
            'anexo_file' => 'nullable|file|mimes:pdf|max:5120', // PDF up to 5MB
        ]);

        // Handle attachment
        $anexoPath = null;
        if ($request->hasFile('anexo_file')) {
            $anexoPath = $request->file('anexo_file')->store('attachments', 'public');
        }

        $conteudoLimpo = HtmlSanitizer::clean($request->conteudo);

        if (trim(strip_tags($conteudoLimpo)) === '' && !$anexoPath) {
            return redirect()->back()->withErrors(['conteudo' => 'Digite uma mensagem antes de enviar.'])->withInput();
        }

        // Create message
        $mensagem = Mensagem::create([
            'ticket_id' => $ticket->id,
            'user_id' => null, // null represents client
            'conteudo' => $conteudoLimpo,
            'is_html' => true,
            'anexo' => $anexoPath ? 'storage/' . $anexoPath : null,
            'enviado_por_admin' => false,
        ]);

        // If status was closed/resolved, reopen as client replied
        if (in_array($ticket->status, ['fechado', 'recusado'])) {
            $ticket->update(['status' => 'analise']);
        }

        // Send email alert to admin/perito
        $emails_notificacao = [
            'patoforense.adriana@gmail.com',
            'contato@medvetlegal.com.br',
            'calefi@csvet.com.br',
            'ascalefi@gmail.com',
        ];

        $subject = "Cliente Respondeu: Ticket #{$ticket->id} - {$ticket->cliente->nome}";
        
        $body = "
        <html>
        <head>
          <title>Nova Mensagem do Cliente</title>
        </head>
        <body style='font-family: Arial, sans-serif; color: #333;'>
          <h2 style='color: #0e2a47;'>O cliente " . e($ticket->cliente->nome) . " enviou uma nova resposta para o Ticket #{$ticket->id}</h2>
          <p><strong>Ticket:</strong> " . e($ticket->titulo) . "</p>
          <p><strong>Status Atual:</strong> " . strtoupper($ticket->status) . "</p>
          <hr style='border: 0; border-top: 1px solid #eee;'>
          <p><strong>Mensagem do Cliente:</strong><br>" . $conteudoLimpo . "</p>
          " . ($anexoPath ? "<p><strong>Anexo Enviado:</strong> <a href='" . asset('storage/' . $anexoPath) . "' target='_blank'>Clique aqui para baixar o PDF</a></p>" : "") . "
          <hr style='border: 0; border-top: 1px solid #eee;'>
          <p>
            <a href='" . url("/admin/tickets/{$ticket->id}") . "' style='background-color: #0e2a47; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>
              Visualizar no Painel Administrativo
            </a>
          </p>
        </body>
        </html>
        ";

        foreach ($emails_notificacao as $destinatario) {
            try {
                Mail::html($body, function ($message) use ($destinatario, $subject, $ticket) {
                    $fromAddress = config('mail.from.address') === 'hello@example.com' ? 'no-reply@medvetlegal.com.br' : config('mail.from.address');
                    $fromName = config('mail.from.name') === 'Laravel' ? 'MedVetLegal' : config('mail.from.name');

                    $message->to($destinatario)
                            ->from($fromAddress, $fromName)
                            ->subject($subject)
                            ->replyTo($ticket->cliente->email);
                });
            } catch (\Exception $e) {
                logger()->warning("Falha ao enviar alerta de resposta de cliente para {$destinatario}: " . $e->getMessage());
            }
        }

        return redirect()->back()->with('success', 'Sua resposta foi enviada com sucesso ao corpo técnico da MedVetLegal.');
    }

    /**
     * Update ticket status based on client's decision on the quote.
     */
    public function updateStatus(Request $request, $token)
    {
        $ticket = Ticket::where('token', $token)->firstOrFail();

        $request->validate([
            'status' => 'required|in:aceito,recusado',
        ]);

        if ($ticket->status !== 'orcado') {
            return redirect()->back()->withErrors(['status' => 'Não há orçamento pendente de decisão para este ticket.']);
        }

        $status = $request->status;

        $ticket->update([
            'status' => $status,
        ]);

        // Log a system action message
        $acaoTexto = $status === 'aceito' 
            ? "O cliente ACEITOU o orçamento proposto no valor de R$ " . number_format($ticket->valor_orcado, 2, ',', '.') 
            : "O cliente RECUSOU o orçamento proposto no valor de R$ " . number_format($ticket->valor_orcado, 2, ',', '.');

        Mensagem::create([
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'conteudo' => $acaoTexto,
            'enviado_por_admin' => false,
        ]);

        // Send email alert to peritos
        $emails_notificacao = [
            'patoforense.adriana@gmail.com',
            'contato@medvetlegal.com.br',
            'calefi@csvet.com.br',
            'ascalefi@gmail.com',
        ];

        $statusLabel = $status === 'aceito' ? 'ACEITOU' : 'RECUSOU';
        $subject = "Orçamento {$statusLabel}: Ticket #{$ticket->id} - {$ticket->cliente->nome}";
        
        $body = "
        <html>
        <head>
          <title>Atualização de Orçamento pelo Cliente</title>
        </head>
        <body style='font-family: Arial, sans-serif; color: #333;'>
          <h2 style='color: #0e2a47;'>Atualização de Orçamento</h2>
          <p>O cliente <strong>" . e($ticket->cliente->nome) . "</strong> alterou a situação do orçamento para o caso <strong>#{$ticket->id} (" . e($ticket->titulo) . ")</strong>.</p>
          <p><strong>Ação do Cliente:</strong> <span style='font-weight: bold; color: " . ($status === 'aceito' ? '#28a745' : '#dc3545') . ";'>" . strtoupper($statusLabel) . "</span></p>
          <p><strong>Valor Proposto:</strong> R$ " . number_format($ticket->valor_orcado, 2, ',', '.') . "</p>
          <hr style='border: 0; border-top: 1px solid #eee;'>
          <p>
            <a href='" . url("/admin/tickets/{$ticket->id}") . "' style='background-color: #0e2a47; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>
              Visualizar no Painel Administrativo
            </a>
          </p>
        </body>
        </html>
        ";

        foreach ($emails_notificacao as $destinatario) {
            try {
                Mail::html($body, function ($message) use ($destinatario, $subject) {
                    $fromAddress = config('mail.from.address') === 'hello@example.com' ? 'no-reply@medvetlegal.com.br' : config('mail.from.address');
                    $fromName = config('mail.from.name') === 'Laravel' ? 'MedVetLegal' : config('mail.from.name');

                    $message->to($destinatario)
                            ->from($fromAddress, $fromName)
                            ->subject($subject);
                });
            } catch (\Exception $e) {
                logger()->warning("Falha ao enviar alerta de status de orçamento para {$destinatario}: " . $e->getMessage());
            }
        }

        $mensagemSucesso = $status === 'aceito' 
            ? 'Orçamento aceito com sucesso! Iniciamos os procedimentos internos e entraremos em contato para as próximas etapas.'
            : 'Proposta recusada. Caso deseje renegociar ou tirar dúvidas, envie uma mensagem no formulário abaixo.';

        return redirect()->back()->with('success', $mensagemSucesso);
    }
}
