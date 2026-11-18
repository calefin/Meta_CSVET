<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class LembrarOrcamentosPendentes extends Command
{
    protected $signature = 'tickets:lembrete-orcamento';

    protected $description = 'Evolui orcamentos parados: lembrete, tentativa de resgate e encerramento automatico por inatividade';

    private const DIAS_LEMBRETE = 3;
    private const DIAS_RESGATE = 4;
    private const DIAS_ENCERRAMENTO = 5;

    private const EMAILS_EQUIPE = [
        'patoforense.adriana@gmail.com',
        'contato@medvetlegal.com.br',
        'calefi@csvet.com.br',
        'ascalefi@gmail.com',
    ];

    public function handle(): int
    {
        $this->enviarLembretes();
        $this->enviarTentativasDeResgate();
        $this->encerrarPorInatividade();

        return self::SUCCESS;
    }

    private function enviarLembretes(): void
    {
        $tickets = Ticket::where('status', 'orcado')
            ->whereNull('archived_at')
            ->whereNull('orcamento_lembrete_enviado_at')
            ->whereNotNull('valor_orcado')
            ->where('updated_at', '<=', now()->subDays(self::DIAS_LEMBRETE))
            ->with(['cliente', 'ultimaMensagem'])
            ->get();

        foreach ($tickets as $ticket) {
            $this->notificarClienteLembrete($ticket);
            $this->notificarEquipe(
                "Orçamento parado há " . self::DIAS_LEMBRETE . "+ dias: Ticket #{$ticket->id} - " . e($ticket->cliente->nome),
                "O cliente <strong>" . e($ticket->cliente->nome) . "</strong> ainda não decidiu sobre o orçamento do Ticket <strong>#{$ticket->id} (" . e($ticket->titulo) . ")</strong>, proposto há mais de " . self::DIAS_LEMBRETE . " dias.<br><br>Um lembrete automático já foi enviado ao cliente. Considere um contato pessoal (telefone/WhatsApp) para não perder o caso.",
                $ticket
            );

            $ticket->update(['orcamento_lembrete_enviado_at' => now()]);

            $this->info("Lembrete enviado para o ticket #{$ticket->id}");
        }
    }

    private function enviarTentativasDeResgate(): void
    {
        $tickets = Ticket::where('status', 'orcado')
            ->whereNull('archived_at')
            ->whereNotNull('orcamento_lembrete_enviado_at')
            ->whereNull('resgate_enviado_at')
            ->whereNotNull('valor_orcado')
            ->where('orcamento_lembrete_enviado_at', '<=', now()->subDays(self::DIAS_RESGATE))
            ->with(['cliente', 'ultimaMensagem'])
            ->get()
            ->filter(fn (Ticket $ticket) => !$ticket->clienteRespondeuDesde($ticket->orcamento_lembrete_enviado_at));

        foreach ($tickets as $ticket) {
            $this->notificarClienteResgate($ticket);
            $this->notificarEquipe(
                "Tentativa de resgate enviada: Ticket #{$ticket->id} - " . e($ticket->cliente->nome),
                "O cliente <strong>" . e($ticket->cliente->nome) . "</strong> continua sem responder sobre o orçamento do Ticket <strong>#{$ticket->id} (" . e($ticket->titulo) . ")</strong>. Enviamos uma tentativa de resgate por e-mail.<br><br>Se não houver retorno, o ticket será encerrado automaticamente em " . self::DIAS_ENCERRAMENTO . " dias. Considere um contato pessoal antes disso.",
                $ticket
            );

            $ticket->update(['resgate_enviado_at' => now()]);

            $this->info("Tentativa de resgate enviada para o ticket #{$ticket->id}");
        }
    }

    private function encerrarPorInatividade(): void
    {
        $tickets = Ticket::where('status', 'orcado')
            ->whereNull('archived_at')
            ->whereNotNull('resgate_enviado_at')
            ->where('resgate_enviado_at', '<=', now()->subDays(self::DIAS_ENCERRAMENTO))
            ->with(['cliente', 'ultimaMensagem'])
            ->get()
            ->filter(fn (Ticket $ticket) => !$ticket->clienteRespondeuDesde($ticket->resgate_enviado_at));

        foreach ($tickets as $ticket) {
            $ticket->update(['status' => 'fechado']);

            $this->notificarClienteEncerramento($ticket);
            $this->notificarEquipe(
                "Ticket encerrado automaticamente por inatividade: #{$ticket->id} - " . e($ticket->cliente->nome),
                "O Ticket <strong>#{$ticket->id} (" . e($ticket->titulo) . ")</strong> foi encerrado automaticamente porque o cliente não respondeu mesmo após a tentativa de resgate.<br><br>O caso pode ser reaberto normalmente pelo painel se o cliente retornar.",
                $ticket
            );

            $this->info("Ticket #{$ticket->id} encerrado automaticamente por inatividade");
        }
    }

    private function fromAddress(): array
    {
        $fromAddress = config('mail.from.address') === 'hello@example.com' ? 'no-reply@medvetlegal.com.br' : config('mail.from.address');
        $fromName = config('mail.from.name') === 'Laravel' ? 'MedVetLegal' : config('mail.from.name');

        return [$fromAddress, $fromName];
    }

    private function notificarClienteLembrete(Ticket $ticket): void
    {
        $portalUrl = url("/tickets/acompanhar/{$ticket->token}");
        $valor = number_format($ticket->valor_orcado, 2, ',', '.');
        $subject = "Lembrete: Orçamento Pendente de Decisão - Ticket #{$ticket->id}";

        $body = "
        <html>
        <head><title>Lembrete de Orçamento - MedVetLegal</title></head>
        <body style='font-family: Arial, sans-serif; color: #333;'>
          <h2 style='color: #0e2a47;'>Olá, " . e($ticket->cliente->nome) . ". Seu orçamento ainda está aguardando decisão.</h2>
          <p>Notamos que a proposta de honorários para o Ticket <strong>#{$ticket->id} (" . e($ticket->titulo) . ")</strong> ainda não foi aceita ou recusada.</p>
          <div style='background-color: #f9f9f9; border-left: 4px solid #c7a006; padding: 15px; margin: 20px 0;'>
            <p style='margin: 0;'><strong>Valor Proposto:</strong> R$ {$valor}</p>
          </div>
          <p>Fique à vontade para tirar dúvidas antes de decidir — é só responder pelo portal.</p>
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

        $this->enviarParaCliente($ticket, $subject, $body, 'lembrete');
    }

    private function notificarClienteResgate(Ticket $ticket): void
    {
        $portalUrl = url("/tickets/acompanhar/{$ticket->token}");
        $valor = number_format($ticket->valor_orcado, 2, ',', '.');
        $subject = "Ainda podemos ajudar? Ticket #{$ticket->id} aguardando retorno";

        $body = "
        <html>
        <head><title>Ainda podemos ajudar? - MedVetLegal</title></head>
        <body style='font-family: Arial, sans-serif; color: #333;'>
          <h2 style='color: #0e2a47;'>Olá, " . e($ticket->cliente->nome) . ". Ainda podemos ajudar com o seu caso?</h2>
          <p>Já tentamos contato sobre a proposta de honorários do Ticket <strong>#{$ticket->id} (" . e($ticket->titulo) . ")</strong> e ainda não obtivemos retorno.</p>
          <div style='background-color: #f9f9f9; border-left: 4px solid #c7a006; padding: 15px; margin: 20px 0;'>
            <p style='margin: 0;'><strong>Valor Proposto:</strong> R$ {$valor}</p>
          </div>
          <p>Se você ainda tem interesse ou possui alguma dúvida sobre o orçamento, é só responder por este portal. Caso não tenhamos retorno, este atendimento será encerrado automaticamente em alguns dias.</p>
          <p style='margin: 25px 0;'>
            <a href='{$portalUrl}' style='background-color: #0e2a47; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>
              Responder e Continuar o Atendimento
            </a>
          </p>
          <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
          <p>Atenciosamente,</p>
          <p><strong>Corpo Técnico da MedVetLegal</strong></p>
        </body>
        </html>
        ";

        $this->enviarParaCliente($ticket, $subject, $body, 'resgate');
    }

    private function notificarClienteEncerramento(Ticket $ticket): void
    {
        $portalUrl = url("/tickets/acompanhar/{$ticket->token}");
        $subject = "Caso Encerrado por Inatividade: Ticket #{$ticket->id}";

        $body = "
        <html>
        <head><title>Caso Encerrado - MedVetLegal</title></head>
        <body style='font-family: Arial, sans-serif; color: #333;'>
          <h2 style='color: #0e2a47;'>Olá, " . e($ticket->cliente->nome) . ". Seu caso foi encerrado por falta de retorno.</h2>
          <p>Como não obtivemos resposta sobre a proposta de honorários do Ticket <strong>#{$ticket->id} (" . e($ticket->titulo) . ")</strong>, encerramos este atendimento.</p>
          <p>Todo o histórico de mensagens e documentos trocados permanece disponível no seu portal de acompanhamento.</p>
          <p style='margin: 25px 0;'>
            <a href='{$portalUrl}' style='background-color: #0e2a47; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>
              Ver Histórico Completo do Caso
            </a>
          </p>
          <p>Caso ainda tenha interesse, é só responder por este mesmo portal que reabrimos o caso normalmente.</p>
          <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
          <p>Atenciosamente,</p>
          <p><strong>Corpo Técnico da MedVetLegal</strong></p>
        </body>
        </html>
        ";

        $this->enviarParaCliente($ticket, $subject, $body, 'encerramento automático');
    }

    private function enviarParaCliente(Ticket $ticket, string $subject, string $body, string $etapa): void
    {
        try {
            [$fromAddress, $fromName] = $this->fromAddress();

            Mail::html($body, function ($message) use ($ticket, $subject, $fromAddress, $fromName) {
                $message->to($ticket->cliente->email)
                        ->from($fromAddress, $fromName)
                        ->subject($subject);
            });
        } catch (\Exception $e) {
            logger()->warning("Falha ao enviar e-mail de {$etapa} ao cliente para o ticket #{$ticket->id}: " . $e->getMessage());
        }
    }

    private function notificarEquipe(string $subject, string $mensagem, Ticket $ticket): void
    {
        $body = "
        <html>
        <head><title>{$subject}</title></head>
        <body style='font-family: Arial, sans-serif; color: #333;'>
          <h2 style='color: #0e2a47;'>{$subject}</h2>
          <p>{$mensagem}</p>
          <p>
            <a href='" . url("/admin/tickets/{$ticket->id}") . "' style='background-color: #0e2a47; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>
              Visualizar no Painel Administrativo
            </a>
          </p>
        </body>
        </html>
        ";

        foreach (self::EMAILS_EQUIPE as $destinatario) {
            try {
                [$fromAddress, $fromName] = $this->fromAddress();

                Mail::html($body, function ($message) use ($destinatario, $subject, $fromAddress, $fromName) {
                    $message->to($destinatario)
                            ->from($fromAddress, $fromName)
                            ->subject($subject);
                });
            } catch (\Exception $e) {
                logger()->warning("Falha ao enviar alerta interno para {$destinatario}: " . $e->getMessage());
            }
        }
    }
}
