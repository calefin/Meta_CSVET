<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class SendDailyConfirmationReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-daily-confirmation-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia lembretes diários de validação de e-mail para estudantes que ainda não confirmaram o cadastro.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $students = Student::with('user')
            ->where('status', 'awaiting_email_validation')
            ->get();

        $notifiedCount = 0;

        foreach ($students as $student) {
            $user = $student->user;
            if (!$user || !$user->email) {
                continue;
            }

            // Check if email was sent in the last 23 hours
            $metadata = $user->metadata ?? [];
            $lastSentStr = $metadata['last_confirmation_email_sent_at'] ?? null;
            if ($lastSentStr) {
                try {
                    $lastSent = Carbon::parse($lastSentStr);
                    if ($lastSent->diffInHours(now()) < 23) {
                        $this->info("Pular: {$user->name} ({$user->email}) - e-mail enviado recentemente ({$lastSent->diffForHumans()})");
                        continue;
                    }
                } catch (\Exception $e) {
                    // Se houver erro ao converter a data, prossegue
                }
            }

            // Generate activation token if missing
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
                    "Este é um lembrete para a validação do seu endereço de e-mail na plataforma profatilio.com.br. Para prosseguir e validar seu e-mail, por favor clique no botão abaixo:\n\n" .
                    "📢 **Importante: Receba Alertas Rápidos no Telegram**\n" .
                    "A plataforma agora conta com integração oficial com o Telegram! Ao vincular sua conta ao bot @{$botUsername}, você receberá notificações em tempo real direto no seu celular sempre que o professor revisar ou assinar seus documentos acadêmicos.\n\n" .
                    "Você poderá realizar a vinculação assim que sua conta for ativada pelo professor, acessando seu painel de controle.";

                Mail::to($user->email)->queue(
                    new \App\Mail\AppNotificationMail(
                        title: 'Valide seu E-mail (Lembrete Diário) 🎓',
                        messageContent: $messageBody,
                        actionUrl: $activationUrl,
                        actionText: 'Validar E-mail',
                        subject: 'Lembrete: Ativação de Conta - Plataforma Prof. Atilio'
                    )
                );

                // Update metadata
                $metadata['last_confirmation_email_sent_at'] = now()->toIso8601String();
                $user->metadata = $metadata;
                $user->save();

                $notifiedCount++;
                $this->info("Notificado: {$user->name} ({$user->email})");
            } catch (\Throwable $e) {
                $this->error("Erro ao enviar e-mail para {$user->name}: " . $e->getMessage());
            }
        }

        $this->info("Envio de lembretes diários concluído. Alunos notificados: {$notifiedCount}");

        return self::SUCCESS;
    }
}
