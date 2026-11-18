<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\Mensagem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function submit(Request $request)
    {
        // 1. Honeypot anti-spam check
        if ($request->filled('url_spam_check')) {
            // Silent drop for bots
            return redirect()->back()->with('success', 'Sua mensagem foi enviada com sucesso!');
        }

        // 2. Minimum fill-time check: bots tend to submit near-instantly after loading the page
        $startedAt = session('contact_form_started_at');
        if ($startedAt && (now()->timestamp - $startedAt) < 3) {
            // Silent drop for bots, mirroring the honeypot response
            return redirect()->back()->with('success', 'Sua mensagem foi enviada com sucesso!');
        }

        // 3. Math security challenge check (dynamic, generated per page load and stored in session)
        $expectedSum = session('contact_challenge_sum');
        if ($expectedSum === null || (int) trim($request->desafio_humano) !== (int) $expectedSum) {
            $redirectUrl = url()->previous();
            if (!str_contains($redirectUrl, '#contato')) {
                $redirectUrl .= '#contato';
            }
            return redirect()->to($redirectUrl)
                ->withInput()
                ->withErrors(['desafio_humano' => 'O resultado da verificação de segurança está incorreto.']);
        }
        session()->forget(['contact_challenge_sum', 'contact_form_started_at']);

        // 4. Validation
        $request->validate([
            'nome' => 'required|string|max:255',
            'telefone' => ['required', 'string', 'max:20', 'regex:/^\(?\d{2}\)?\s?9?\d{4}-?\d{4}$/'],
            'email' => 'required|email|max:255',
            'assunto' => 'required|string|in:Assistência Técnica Judicial,Análise de Viabilidade Técnica,Parecer Técnico em Prontuário,Necropsia Forense ou Exumação,Discussão de Casos de Intoxicação,Não sei o que preciso / Orientação',
            'mensagem' => 'required|string',
        ], [
            'telefone.regex' => 'Informe um telefone válido, com DDD. Ex: (11) 91234-5678.',
        ]);

        // 5. Create or update Cliente
        $cliente = Cliente::firstOrCreate(
            ['email' => $request->email],
            [
                'nome' => $request->nome,
                'telefone' => $request->telefone,
            ]
        );

        // Update name or phone if different
        if ($cliente->nome !== $request->nome || $cliente->telefone !== $request->telefone) {
            $cliente->update([
                'nome' => $request->nome,
                'telefone' => $request->telefone,
            ]);
        }

        // 6. Determine priority based on urgency of subject
        $prioridade = 'media';
        if ($request->assunto === 'Necropsia Forense ou Exumação') {
            $prioridade = 'urgente'; // Decomposition/forensic chain of custody is highly time-sensitive
        } elseif (in_array($request->assunto, ['Discussão de Casos de Intoxicação', 'Assistência Técnica Judicial', 'Parecer Técnico em Prontuário'])) {
            $prioridade = 'alta';
        }

        // 7. Create the Ticket (keeping the client's name out of the title for privacy)
        $ticket = Ticket::create([
            'cliente_id' => $cliente->id,
            'titulo' => $request->assunto,
            'descricao' => $request->mensagem,
            'status' => 'novo',
            'prioridade' => $prioridade,
            'token' => \Illuminate\Support\Str::random(32),
        ]);

        // 8. Create the Ticket Message
        Mensagem::create([
            'ticket_id' => $ticket->id,
            'user_id' => null, // null indicates sent by client
            'conteudo' => $request->mensagem,
            'enviado_por_admin' => false,
        ]);

        // 9. Prepare and send email notification
        $emails_notificacao = array_filter([
            'patoforense.adriana@gmail.com',
            'contato@medvetlegal.com.br',
            'ascalefi@gmail.com',
            'calefi@csvet.com.br',
        ]);
        
        $subject = "Novo Contato MedVetLegal: {$request->assunto} - {$request->nome}";

        $mensagemWhatsapp = "Olá, {$request->nome}! Aqui é a equipe da MedVetLegal. Recebemos sua solicitação (Ticket #{$ticket->id}) e já retornamos pelo sistema/e-mail — poderia verificar sua caixa de entrada, por favor? Ficamos à disposição por aqui também.";
        $whatsappUrl = $cliente->whatsappUrl($mensagemWhatsapp);
        $botaoWhatsapp = $whatsappUrl
            ? "<a href='{$whatsappUrl}' style='background-color: #25D366; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold; margin-right: 10px;' target='_blank'>Responder no WhatsApp</a>"
            : '';

        $body = "
        <html>
        <head>
          <title>Novo Contato via Site</title>
        </head>
        <body style='font-family: Arial, sans-serif; color: #333;'>
          <h2 style='color: #0e2a47;'>Novo contato recebido pelo site MedVetLegal (Ticket #{$ticket->id})</h2>
          <p><strong>Nome do Cliente:</strong> " . e($request->nome) . "</p>
          <p><strong>E-mail:</strong> " . e($request->email) . "</p>
          <p><strong>Telefone:</strong> " . e($request->telefone) . "</p>
          <p><strong>Área de Interesse:</strong> " . e($request->assunto) . "</p>
          <p><strong>Prioridade Definida:</strong> " . strtoupper($prioridade) . "</p>
          <hr style='border: 0; border-top: 1px solid #eee;'>
          <p><strong>Mensagem / Relato do Caso:</strong><br>" . nl2br(e($request->mensagem)) . "</p>
          <hr style='border: 0; border-top: 1px solid #eee;'>
          <p>
            <a href='" . url("/admin/tickets/{$ticket->id}") . "' style='background-color: #0e2a47; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold; margin-right: 10px;'>
              Visualizar no Painel Administrativo
            </a>
            <a href='" . url("/tickets/acompanhar/{$ticket->token}") . "' style='background-color: #c7a006; color: #0e2a47; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold; margin-right: 10px;'>
              Ver Link do Cliente
            </a>
            {$botaoWhatsapp}
          </p>
          <p><small>Enviado em: " . date("d/m/Y H:i") . "</small></p>
        </body>
        </html>
        ";

        foreach ($emails_notificacao as $destinatario) {
            try {
                Mail::html($body, function ($message) use ($destinatario, $subject, $request) {
                    $fromAddress = config('mail.from.address');
                    if (empty($fromAddress) || $fromAddress === 'hello@example.com') {
                        $fromAddress = 'contato@medvetlegal.com.br';
                    }
                    $fromName = config('mail.from.name');
                    if (empty($fromName) || $fromName === 'Laravel') {
                        $fromName = 'MedVetLegal';
                    }
                    
                    $message->to($destinatario)
                            ->from($fromAddress, $fromName)
                            ->subject($subject)
                            ->replyTo($request->email);
                });
            } catch (\Exception $e) {
                logger()->warning("Falha ao enviar notificação de contato para {$destinatario}: " . $e->getMessage());
            }
        }

        // 10. Send confirmation email to the client
        $subject_cliente = "MedVetLegal: Recebemos sua solicitação de análise (Ticket #{$ticket->id})";
        
        $body_cliente = "
        <html>
        <head>
          <title>Solicitação Recebida - MedVetLegal</title>
        </head>
        <body style='font-family: Arial, sans-serif; color: #333;'>
          <h2 style='color: #0e2a47;'>Olá, " . e($request->nome) . ". Recebemos seu contato!</h2>
          <p>Agradecemos o envio do seu caso. As informações foram recebidas com sucesso e já estão em análise preliminar.</p>

          <div style='background-color: #f9f9f9; border-left: 4px solid #c7a006; padding: 15px; margin: 20px 0;'>
            <p style='margin: 0 0 10px 0;'><strong>Detalhes da Solicitação:</strong></p>
            <p style='margin: 0 0 5px 0;'><strong>Ticket:</strong> #{$ticket->id}</p>
            <p style='margin: 0 0 5px 0;'><strong>Área de Interesse:</strong> " . e($request->assunto) . "</p>
            <p style='margin: 0;'><strong>Status:</strong> Análise de Viabilidade Técnica</p>
          </div>

          <h3 style='color: #0e2a47;'>Como acompanhar seu caso?</h3>
          <p>Para sua segurança e privacidade, criamos um portal de acompanhamento exclusivo para você. Nele, você poderá trocar mensagens com nossa equipe, enviar exames ou prontuários em formato PDF e aprovar a proposta de honorários.</p>
          
          <p style='margin: 25px 0;'>
            <a href='" . url("/tickets/acompanhar/{$ticket->token}") . "' style='background-color: #0e2a47; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold; font-size: 16px;'>
              Acessar Meu Portal de Acompanhamento (Sem Senha)
            </a>
          </p>
          
          <p style='color: #666; font-size: 13px;'>* Atenção: Guarde este link com segurança. Ele permite acesso direto ao acompanhamento do seu caso sem necessidade de criar senha de login.</p>
          
          <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
          <p>Atenciosamente,</p>
          <p><strong>Corpo Técnico da MedVetLegal</strong><br>
          Dra. Adriana de Siqueira<br>
          Dr. Atilio Sersun Calefi</p>
        </body>
        </html>
        ";

        try {
            Mail::html($body_cliente, function ($message) use ($request, $subject_cliente) {
                $fromAddress = config('mail.from.address');
                if (empty($fromAddress) || $fromAddress === 'hello@example.com') {
                    $fromAddress = 'contato@medvetlegal.com.br';
                }
                $fromName = config('mail.from.name');
                if (empty($fromName) || $fromName === 'Laravel') {
                    $fromName = 'MedVetLegal';
                }

                $message->to($request->email)
                        ->from($fromAddress, $fromName)
                        ->subject($subject_cliente);
            });
        } catch (\Exception $e) {
            logger()->error('Falha ao enviar e-mail de confirmação ao cliente: ' . $e->getMessage());
        }

        $redirectUrl = url()->previous();
        if (!str_contains($redirectUrl, '#contato')) {
            $redirectUrl .= '#contato';
        }

        return redirect()->to($redirectUrl)->with('success', 'Sua solicitação de análise de caso foi recebida com sucesso! A Dra. Adriana de Siqueira analisará as informações e entrará em contato em breve.');
    }
}
