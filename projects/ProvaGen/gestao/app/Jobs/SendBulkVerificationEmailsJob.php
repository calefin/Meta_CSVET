<?php

namespace App\Jobs;

use App\Models\Owner;
use App\Models\Student;
use App\Support\OwnerContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendBulkVerificationEmailsJob implements ShouldQueue
{
    use Queueable;

    protected bool $forceResend;
    protected ?string $customMessage;
    protected int $adminUserId;

    /**
     * Create a new job instance.
     */
    public function __construct(bool $forceResend, ?string $customMessage, int $adminUserId)
    {
        $this->forceResend = $forceResend;
        $this->customMessage = $customMessage;
        $this->adminUserId = $adminUserId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Job roda num worker de fila (sem request HTTP) — sem isto, o
        // Global Scope de Student não sabe de qual professor é a lista, e
        // este envio em massa vazaria pra alunos de outros professores.
        OwnerContext::set($this->adminUserId);

        $professorName = Owner::find($this->adminUserId)?->name ?? 'o professor';

        $students = Student::with('user')
            ->where('status', 'awaiting_email_validation')
            ->get();

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($students as $student) {
            $user = $student->user;
            if (!$user || !$user->email) {
                continue;
            }

            // Trava anti-spam: verifica se o e-mail foi enviado nas últimas 24 horas
            if (!$this->forceResend) {
                $metadata = $user->metadata ?? [];
                $lastSentStr = $metadata['last_confirmation_email_sent_at'] ?? null;
                if ($lastSentStr) {
                    try {
                        $lastSent = Carbon::parse($lastSentStr);
                        if ($lastSent->diffInHours(now()) < 24) {
                            $skippedCount++;
                            continue;
                        }
                    } catch (\Exception $e) {
                        // Prossegue se houver erro ao converter a data
                    }
                }
            }

            // Gera o token de ativação caso não exista
            if (empty($student->activation_token)) {
                $student->activation_token = bin2hex(random_bytes(16));
                $student->save();
            }

            try {
                $activationUrl = str_starts_with(config('app.url'), 'https') 
                    ? secure_url("/activate?token={$student->activation_token}") 
                    : url("/activate?token={$student->activation_token}");
                $botUsername = ltrim(config('services.telegram.bot_username') ?? '', '@');
                
                $messageBody = "Olá, {$user->name}!\n\n" .
                    "Este é um lembrete para a validação do seu endereço de e-mail na plataforma. Para prosseguir e validar seu e-mail, por favor clique no botão abaixo:\n\n";

                // Se houver uma mensagem personalizada do professor, insere aqui
                if (!empty($this->customMessage)) {
                    $messageBody .= "💬 **Mensagem do Professor:**\n" .
                        "\"{$this->customMessage}\"\n\n";
                }

                $messageBody .= "📢 **Importante: Receba Alertas Rápidos no Telegram**\n" .
                    "A plataforma agora conta com integração oficial com o Telegram! Ao vincular sua conta ao bot @{$botUsername}, você receberá notificações em tempo real direto no seu celular sempre que o professor revisar ou assinar seus documentos acadêmicos.\n\n" .
                    "Você poderá realizar a vinculação assim que sua conta for ativada pelo professor, acessando seu painel de controle.";

                Mail::to($user->email)->send(
                    new \App\Mail\AppNotificationMail(
                        title: 'Valide seu E-mail (Lembrete) 🎓',
                        messageContent: $messageBody,
                        actionUrl: $activationUrl,
                        actionText: 'Validar E-mail',
                        subject: 'Lembrete: Ativação de Conta - ' . $professorName
                    )
                );

                // Atualiza os metadados do envio
                $metadata = $user->metadata ?? [];
                $metadata['last_confirmation_email_sent_at'] = now()->toIso8601String();
                $user->metadata = $metadata;
                $user->save();

                $sentCount++;
            } catch (\Throwable $e) {
                Log::error("Erro ao enviar e-mail de lembrete em massa para {$user->email} no Job: " . $e->getMessage());
            }
        }

        // Notifica o administrador que iniciou a ação
        Log::info("Processamento de e-mails em massa concluído pelo Job. Iniciado por Admin ID {$this->adminUserId}. Enviados: {$sentCount}, Ignorados (anti-spam): {$skippedCount}");
    }
}
