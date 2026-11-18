<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Owner;
use App\Models\OwnerSettings;
use App\Models\Student;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Resolve o professor (Owner) vinculado a este chat_id via OwnerSettings.
     * Bot é único e compartilhado entre todos os professores — cada um só
     * vê/responde os próprios orientandos (checado em cada comando abaixo),
     * nunca os de outro professor.
     */
    private function findOwnerByChatId($chatId): ?Owner
    {
        $settings = OwnerSettings::where('telegram_chat_id', (string) $chatId)->first();
        return $settings?->owner;
    }

    /**
     * Handle incoming Telegram webhook updates.
     */
    public function handle(Request $request)
    {
        $token = $request->query('token');

        // 1. Verify token to protect route
        $expectedToken = config('services.telegram.bot_token');
        if (empty($expectedToken) || $token !== $expectedToken) {
            Log::warning('Telegram Webhook: Invalid webhook token received.');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

        // 2. Parse message payload
        if (isset($payload['message'])) {
            $message = $payload['message'];
            $chatId = $message['chat']['id'] ?? null;
            $text = trim($message['text'] ?? '');

            if ($chatId) {
                // Command: /start
                if (str_starts_with($text, '/start')) {
                    // Extract parameters: "/start <student_id>" or "/start admin_<link_token>"
                    $parts = explode(' ', $text);
                    $param = isset($parts[1]) ? trim($parts[1]) : null;

                    if ($param) {
                        if (str_starts_with($param, 'admin_')) {
                            $linkToken = substr($param, 6);
                            if (!empty($linkToken)) {
                                $settings = OwnerSettings::where('telegram_link_token', $linkToken)->first();
                                if ($settings) {
                                    $settings->update(['telegram_chat_id' => (string) $chatId]);
                                    $ownerName = $settings->owner?->name ?? 'Professor';
                                    $welcomeMsg = "Olá, <b>Prof. {$ownerName}</b>!\n\nSeu Telegram foi vinculado com sucesso à sua conta de <b>Administrador/Orientador</b> na plataforma.\n\nVocê receberá alertas em tempo real sobre novos processos e submissões enviadas pelos seus orientandos! 🎓";
                                    $this->telegramService->sendMessage((string)$chatId, $welcomeMsg);
                                    return response()->json(['status' => 'success', 'message' => 'Admin linked']);
                                }
                            }
                        } else {
                            $student = Student::where('telegram_link_token', $param)->first();

                            if ($student) {
                                // Link Telegram account to student profile
                                $student->update(['telegram_chat_id' => $chatId]);

                                // Mensagem de Boas-vindas Premium detalhada com os comandos
                                $studentName = $student->user ? $student->user->name : 'Estudante';
                                $welcomeMsg = "🎓 <b>Bem-vindo(a) ao Assistente Acadêmico!</b>\n\n"
                                    . "Olá, <b>{$studentName}</b>!\n"
                                    . "Seu Telegram foi vinculado com sucesso à plataforma.\n\n"
                                    . "Você passará a receber notificações em tempo real sobre:\n"
                                    . "• 📝 Revisões e correções nos seus documentos de TCC ou IC.\n"
                                    . "• ✍️ Assinatura e liberação de termos/relatórios.\n"
                                    . "• ⏰ Alertas regressivos de prazos trimestrais de entrega de relatórios.\n\n"
                                    . "🛠️ <b>Comandos Básicos Disponíveis:</b>\n"
                                    . "👉 <code>/status</code> - Verifica o status da sua conta e confirmação de alertas trimestrais.\n"
                                    . "👉 <code>/suporte [sua dúvida]</code> - Envia uma mensagem ou dúvida rápida diretamente para o seu orientador.\n\n"
                                    . "Desejamos uma excelente jornada acadêmica! 🚀";

                                $this->telegramService->sendMessage((string)$chatId, $welcomeMsg);
                                return response()->json(['status' => 'success', 'message' => 'Student linked and notified']);
                            }
                        }
                    }

                    // Mensagem de Ajuda Padrão se não houver parâmetro de ID
                    $helpMsg = "🎓 <b>Assistente Virtual Acadêmico</b>\n\n"
                        . "Olá! Este bot permite que <b>alunos orientandos</b> e o <b>público geral</b> entrem em contato com o professor de forma segura.\n\n"
                        . "👇 <b>Como usar:</b>\n\n"
                        . "🎓 <b>Para Alunos Orientandos (TCC/IC):</b>\n"
                        . "Acesse seu painel, clique em <b>Perfil</b> no menu, vá até a aba <b>Telegram</b> e clique em <b>Vincular Conta</b>.\n\n"
                        . "📬 <b>Para Público Geral / Dúvidas / Contatos Externos:</b>\n"
                        . "Envie qualquer mensagem de texto ou utilize o comando <code>/suporte [sua mensagem]</code> neste chat! Sua mensagem será encaminhada de forma segura ao painel do professor, e ele responderá diretamente por aqui, sem expor dados pessoais.";
                    $this->telegramService->sendMessage((string)$chatId, $helpMsg);
                    return response()->json(['status' => 'ok']);

                }

                // Command: /status
                if (str_starts_with($text, '/status')) {
                    $student = Student::where('telegram_chat_id', $chatId)->first();
                    $owner = $this->findOwnerByChatId($chatId);

                    if ($student) {
                        $studentName = $student->user ? $student->user->name : 'Estudante';
                        $statusMsg = "✅ <b>Status da Integração</b>\n\nEste chat está vinculado à conta de: <b>{$studentName}</b> (RGM: {$student->registration}).\n\nVocê está pronto para receber notificações automáticas! 🎓\n\n💡 <i>Dica: Digite <code>/suporte [sua mensagem]</code> para mandar uma dúvida rápida diretamente para o seu orientador!</i>";
                    } elseif ($owner) {
                        $statusMsg = "✅ <b>Status da Integração</b>\n\nEste chat está vinculado ao Administrador: <b>Prof. {$owner->name}</b>.\n\nVocê receberá notificações em tempo real sobre submissões de seus alunos! 🎓";
                    } else {
                        $statusMsg = "⚠️ <b>Status da Integração</b>\n\nEste chat do Telegram não está vinculado a nenhuma conta de orientando.\n\n💡 <i>Se você for um contato externo, pode simplesmente enviar mensagens de texto normais ou usar <code>/suporte [mensagem]</code> para falar com o professor.</i>";
                    }
                    $this->telegramService->sendMessage((string)$chatId, $statusMsg);
                    return response()->json(['status' => 'ok']);
                }

                $owner = $this->findOwnerByChatId($chatId);

                // Command: /responder (Apenas para o Administrador/Professor)
                if ($owner && str_starts_with($text, '/responder')) {
                    $parts = explode(' ', $text, 3);
                    $targetChatId = isset($parts[1]) ? trim($parts[1]) : null;
                    $replyContent = isset($parts[2]) ? trim($parts[2]) : null;

                    // Nunca deixar um professor responder pra um chat_id que não é de um orientando
                    // dele (ou um contato externo que ele mesmo recebeu) — impede vazamento entre tenants.
                    $belongsToThisOwner = $targetChatId && (
                        Student::where('telegram_chat_id', $targetChatId)->where('owner_user_id', $owner->id)->exists()
                        || \App\Models\TelegramMessage::where('chat_id', $targetChatId)->where('owner_user_id', $owner->id)->exists()
                    );

                    if ($targetChatId && $replyContent && $belongsToThisOwner) {
                        $success = $this->telegramService->sendMessage($targetChatId, "<b>Mensagem do Prof. {$owner->name}:</b>\n\n{$replyContent}");
                        if ($success) {
                            // Marcar mensagens não lidas desse destinatário como lidas
                            \App\Models\TelegramMessage::where('chat_id', $targetChatId)
                                ->where('owner_user_id', $owner->id)
                                ->where('is_read', false)
                                ->update(['is_read' => true]);

                            $this->telegramService->sendMessage((string)$chatId, "✅ Resposta enviada com sucesso para o chat ID <code>{$targetChatId}</code>!");
                        } else {
                            $this->telegramService->sendMessage((string)$chatId, "❌ Falha ao enviar resposta para o chat ID <code>{$targetChatId}</code>.");
                        }
                    } elseif ($targetChatId && $replyContent && !$belongsToThisOwner) {
                        $this->telegramService->sendMessage((string)$chatId, "❌ Esse chat ID não pertence a um dos seus contatos.");
                    } else {
                        $this->telegramService->sendMessage((string)$chatId, "⚠️ Uso correto: <code>/responder [chat_id] [sua resposta]</code>");
                    }
                    return response()->json(['status' => 'ok']);
                }

                // Command: /suporte <mensagem>
                if (str_starts_with($text, '/suporte')) {
                    $student = Student::where('telegram_chat_id', $chatId)->first();

                    $messageText = trim(substr($text, 8)); // Remove "/suporte"

                    if (empty($messageText)) {
                        $this->telegramService->sendMessage((string)$chatId, "💡 <b>Uso:</b> Digite <code>/suporte [sua mensagem]</code> para enviar sua dúvida diretamente ao seu orientador.");
                        return response()->json(['status' => 'ok']);
                    }

                    if ($student) {
                        // Gravar a mensagem de suporte no histórico do banco
                        \App\Models\TelegramMessage::create([
                            'owner_user_id' => $student->owner_user_id,
                            'student_id' => $student->id,
                            'chat_id' => (string) $chatId,
                            'message_text' => "[SUPORTE] " . $messageText,
                        ]);

                        // Tentar enviar cópia instantânea para o Telegram do Professor
                        $professor = Owner::find($student->owner_user_id);
                        if ($professor && !empty($professor->telegram_chat_id)) {
                            $studentName = $student->user ? $student->user->name : 'Estudante';
                            $professorMsg = "📬 <b>Mensagem de Suporte Recebida</b>\n\n"
                                . "<b>Aluno:</b> {$studentName} (RGM: {$student->registration})\n"
                                . "<b>Chat ID:</b> <code>{$chatId}</code>\n"
                                . "<b>Dúvida/Mensagem:</b>\n<i>{$messageText}</i>\n\n"
                                . "👉 <b>Para responder diretamente, envie:</b>\n"
                                . "<code>/responder {$chatId} [sua resposta]</code>\n\n"
                                . "💡 <i>Você também pode responder pelo painel Filament no Mural do Telegram.</i>";
                            try {
                                $this->telegramService->sendMessage((string)$professor->telegram_chat_id, $professorMsg);
                            } catch (\Exception $ex) {
                                Log::error("Erro ao enviar mensagem de suporte ao Telegram do professor: " . $ex->getMessage());
                            }
                        }

                        $this->telegramService->sendMessage((string)$chatId, "✅ <b>Dúvida Enviada!</b>\n\nSua mensagem foi entregue com sucesso ao seu orientador. Você receberá uma resposta em breve!");
                    } elseif ($owner) {
                        // Resposta específica para o Administrador testando o deploy
                        $this->telegramService->sendMessage((string)$chatId, "🧪 <b>[TESTE ADMIN] Comando Recebido!</b>\n\nSua mensagem de teste: <i>\"{$messageText}\"</i> foi processada com sucesso no webhook do Telegram!");
                    } else {
                        // Usuário externo — sem professor dono conhecido (não veio de nenhum
                        // link/turma específica), fica só registrado, sem notificação automática.
                        $from = $message['from'] ?? [];
                        $senderFirstName = $from['first_name'] ?? '';
                        $senderLastName = $from['last_name'] ?? '';
                        $senderUsername = $from['username'] ?? '';
                        $senderFullName = trim($senderFirstName . ' ' . $senderLastName);
                        $senderDisplay = $senderFullName ?: 'Sem Nome';
                        if ($senderUsername) {
                            $senderDisplay .= " (@{$senderUsername})";
                        }

                        \App\Models\TelegramMessage::create([
                            'student_id' => null,
                            'chat_id' => (string) $chatId,
                            'message_text' => "[EXTERNO - SUPORTE] Remetente: {$senderDisplay}\n\nMensagem:\n{$messageText}",
                        ]);

                        $this->telegramService->sendMessage((string)$chatId, "✅ <b>Dúvida Enviada!</b>\n\nSua mensagem foi registrada. Um professor entrará em contato quando associar este chat a uma turma/orientando.");
                    }
                    return response()->json(['status' => 'ok']);
                }

                // Fallback para qualquer outra mensagem de texto enviada ao Bot
                $student = Student::where('telegram_chat_id', $chatId)->first();

                if ($owner) {
                    // Evitar loops ou salvamentos de mensagens enviadas pelo próprio administrador
                    $this->telegramService->sendMessage((string)$chatId, "Olá, Prof. {$owner->name}! Para responder a um contato externo, use o comando:\n<code>/responder [chat_id] [sua resposta]</code>");
                    return response()->json(['status' => 'ok']);
                }

                if ($student) {
                    \App\Models\TelegramMessage::create([
                        'owner_user_id' => $student->owner_user_id,
                        'student_id' => $student->id,
                        'chat_id' => (string) $chatId,
                        'message_text' => $text,
                    ]);

                    $studentName = $student->user ? $student->user->name : 'Estudante';
                    $fallbackMsg = "Olá, <b>{$studentName}</b>!\n\nRecebi sua mensagem e ela foi registrada no painel do seu orientador! 📬\n\n💡 <i>Dica: Envie <code>/status</code> para ver os detalhes da sua conta vinculada.</i>";
                    $this->telegramService->sendMessage((string)$chatId, $fallbackMsg);
                } else {
                    // Contato Externo — sem professor dono conhecido ainda.
                    $from = $message['from'] ?? [];
                    $senderFirstName = $from['first_name'] ?? '';
                    $senderLastName = $from['last_name'] ?? '';
                    $senderUsername = $from['username'] ?? '';
                    $senderFullName = trim($senderFirstName . ' ' . $senderLastName);
                    $senderDisplay = $senderFullName ?: 'Sem Nome';
                    if ($senderUsername) {
                        $senderDisplay .= " (@{$senderUsername})";
                    }

                    \App\Models\TelegramMessage::create([
                        'student_id' => null,
                        'chat_id' => (string) $chatId,
                        'message_text' => "[EXTERNO] Remetente: {$senderDisplay}\n\nMensagem:\n{$text}",
                    ]);

                    $fallbackMsg = "Olá! Sou o assistente acadêmico. Recebi sua mensagem com sucesso, mas este chat ainda não está associado a um professor específico. Se você foi convidado por um link, use-o para vincular sua conta.";
                    $this->telegramService->sendMessage((string)$chatId, $fallbackMsg);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
