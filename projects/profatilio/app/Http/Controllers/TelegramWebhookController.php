<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
                    // Extract parameters: "/start <student_id>" or "/start admin_<user_id>"
                    $parts = explode(' ', $text);
                    $param = isset($parts[1]) ? trim($parts[1]) : null;

                    if ($param) {
                        // Participante de revisão de artigo/livro (link da página do processo).
                        if (str_starts_with($param, 'rev_')) {
                            $reviewer = \App\Models\ArticleReviewer::where('telegram_link_token', substr($param, 4))->first();
                            if ($reviewer) {
                                // Mesmo e-mail em outros processos também passa a receber.
                                \App\Models\ArticleReviewer::whereKey($reviewer->id)
                                    ->when($reviewer->email, fn ($q) => $q->orWhere('email', $reviewer->email))
                                    ->update(['telegram_chat_id' => (string) $chatId]);
                                $reviewer->articleReview?->logEvent("{$reviewer->name} vinculou o Telegram");

                                $this->telegramService->sendMessage((string) $chatId,
                                    "✅ <b>Telegram vinculado!</b>\n\nOlá, <b>" . e($reviewer->name) . "</b>. Você vai receber aqui os avisos do processo de revisão de <b>"
                                    . e(\Illuminate\Support\Str::limit($reviewer->articleReview?->title ?? '', 150)) . "</b>: quando chegar a sua vez, com quem está o arquivo e o andamento.\n\n— Prof. Atilio");

                                return response()->json(['status' => 'success', 'message' => 'Reviewer linked']);
                            }
                        } elseif (str_starts_with($param, 'admin_')) {
                            $token = substr($param, 6);
                            if (!empty($token)) {
                                $user = \App\Models\User::where('telegram_link_token', $token)->first();
                                if ($user && $user->role === 'admin') {
                                    $user->update(['telegram_chat_id' => $chatId]);
                                    $welcomeMsg = "Olá, <b>Prof. {$user->name}</b>!\n\nSeu Telegram foi vinculado com sucesso à sua conta de <b>Administrador/Orientador</b> da plataforma profatilio.com.br.\n\nVocê receberá alertas em tempo real sobre novos processos e submissões enviadas pelos seus orientandos! 🎓";
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
                                $welcomeMsg = "🎓 <b>Bem-vindo(a) ao Assistente Acadêmico do Prof. Atilio!</b>\n\n"
                                    . "Olá, <b>{$studentName}</b>!\n"
                                    . "Seu Telegram foi vinculado com sucesso à plataforma <a href=\"" . url('/') . "\">profatilio.com.br</a>.\n\n"
                                    . "Você passará a receber notificações em tempo real sobre:\n"
                                    . "• 📝 Revisões e correções nos seus documentos de TCC ou IC.\n"
                                    . "• ✍️ Assinatura e liberação de termos/relatórios.\n"
                                    . "• ⏰ Alertas regressivos de prazos trimestrais de entrega de relatórios.\n\n"
                                    . "🛠️ <b>Comandos Básicos Disponíveis:</b>\n"
                                    . "👉 <code>/status</code> - Verifica o status da sua conta e confirmação de alertas trimestrais.\n"
                                    . "👉 <code>/suporte [sua dúvida]</code> - Envia uma mensagem ou dúvida rápida diretamente para o Prof. Atilio.\n\n"
                                    . "Desejamos uma excelente jornada acadêmica! 🚀";
                                
                                $this->telegramService->sendMessage((string)$chatId, $welcomeMsg);
                                return response()->json(['status' => 'success', 'message' => 'Student linked and notified']);
                            }
                        }
                    }

                    // Mensagem de Ajuda Padrão se não houver parâmetro de ID
                    $helpMsg = "🎓 <b>Assistente Virtual do Prof. Atilio Calefi</b>\n\n"
                        . "Olá! Este bot permite que <b>alunos orientandos</b> e o <b>público geral</b> entrem em contato com o professor de forma segura.\n\n"
                        . "👇 <b>Como usar:</b>\n\n"
                        . "🎓 <b>Para Alunos Orientandos (TCC/IC):</b>\n"
                        . "Acesse seu painel em <a href=\"" . url('/portal') . "\">profatilio.com.br</a>, clique em <b>Perfil</b> no menu, vá até a aba <b>Telegram</b> e clique em <b>Vincular Conta</b>.\n\n"
                        . "📬 <b>Para Público Geral / Dúvidas / Contatos Externos:</b>\n"
                        . "Envie qualquer mensagem de texto ou utilize o comando <code>/suporte [sua mensagem]</code> neste chat! Sua mensagem será encaminhada de forma segura ao painel do professor, e ele responderá diretamente por aqui, sem expor dados pessoais.";
                    $this->telegramService->sendMessage((string)$chatId, $helpMsg);
                    return response()->json(['status' => 'ok']);

                }

                // Command: /status
                if (str_starts_with($text, '/status')) {
                    $student = Student::where('telegram_chat_id', $chatId)->first();
                    $user = \App\Models\User::where('telegram_chat_id', $chatId)->first();

                    if ($student) {
                        $studentName = $student->user ? $student->user->name : 'Estudante';
                        $statusMsg = "✅ <b>Status da Integração</b>\n\nEste chat está vinculado à conta de: <b>{$studentName}</b> (RGM: {$student->registration}).\n\nVocê está pronto para receber notificações automáticas! 🎓\n\n💡 <i>Dica: Digite <code>/suporte [sua mensagem]</code> para mandar uma dúvida rápida diretamente para o Prof. Atilio!</i>";
                    } elseif ($user && $user->role === 'admin') {
                        $statusMsg = "✅ <b>Status da Integração</b>\n\nEste chat está vinculado ao Administrador: <b>Prof. {$user->name}</b>.\n\nVocê receberá notificações em tempo real sobre submissões de seus alunos! 🎓";
                    } else {
                        $statusMsg = "⚠️ <b>Status da Integração</b>\n\nEste chat do Telegram não está vinculado a nenhuma conta de orientando.\n\n💡 <i>Se você for um contato externo, pode simplesmente enviar mensagens de texto normais ou usar <code>/suporte [mensagem]</code> para falar com o professor.</i>";
                    }
                    $this->telegramService->sendMessage((string)$chatId, $statusMsg);
                    return response()->json(['status' => 'ok']);
                }

                // Command: /responder (Apenas para o Administrador/Professor)
                $user = \App\Models\User::where('telegram_chat_id', $chatId)->first();
                if ($user && $user->role === 'admin' && str_starts_with($text, '/responder')) {
                    $parts = explode(' ', $text, 3);
                    $targetChatId = isset($parts[1]) ? trim($parts[1]) : null;
                    $replyContent = isset($parts[2]) ? trim($parts[2]) : null;

                    if ($targetChatId && $replyContent) {
                        $success = $this->telegramService->sendMessage($targetChatId, "<b>Mensagem do Prof. Atilio:</b>\n\n{$replyContent}");
                        if ($success) {
                            // Marcar mensagens não lidas desse destinatário como lidas
                            \App\Models\TelegramMessage::where('chat_id', $targetChatId)
                                ->where('is_read', false)
                                ->update(['is_read' => true]);

                            $this->telegramService->sendMessage((string)$chatId, "✅ Resposta enviada com sucesso para o chat ID <code>{$targetChatId}</code>!");
                        } else {
                            $this->telegramService->sendMessage((string)$chatId, "❌ Falha ao enviar resposta para o chat ID <code>{$targetChatId}</code>.");
                        }
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
                        $this->telegramService->sendMessage((string)$chatId, "💡 <b>Uso:</b> Digite <code>/suporte [sua mensagem]</code> para enviar sua dúvida diretamente ao Prof. Atilio.");
                        return response()->json(['status' => 'ok']);
                    }

                    if ($student) {
                        // Gravar a mensagem de suporte no histórico do banco
                        \App\Models\TelegramMessage::create([
                            'student_id' => $student->id,
                            'chat_id' => (string) $chatId,
                            'message_text' => "[SUPORTE] " . $messageText,
                        ]);

                        // Tentar enviar cópia instantânea para o Telegram do Professor
                        $professor = \App\Models\User::where('role', 'admin')->whereNotNull('telegram_chat_id')->first();
                        if ($professor) {
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

                        $this->telegramService->sendMessage((string)$chatId, "✅ <b>Dúvida Enviada!</b>\n\nSua mensagem foi entregue com sucesso ao Prof. Atilio. Você receberá uma resposta em breve!");
                    } elseif ($user && $user->role === 'admin') {
                        // Resposta específica para o Administrador testando o deploy
                        $this->telegramService->sendMessage((string)$chatId, "🧪 <b>[TESTE ADMIN] Comando Recebido!</b>\n\nSua mensagem de teste: <i>\"{$messageText}\"</i> foi processada com sucesso no webhook do Telegram!");
                    } else {
                        // Usuário externo
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

                        $professor = \App\Models\User::where('role', 'admin')->whereNotNull('telegram_chat_id')->first();
                        if ($professor) {
                            $professorMsg = "📬 <b>Mensagem de Contato Externo Recebida! (Suporte)</b>\n\n"
                                . "<b>Remetente:</b> {$senderDisplay}\n"
                                . "<b>Chat ID:</b> <code>{$chatId}</code>\n"
                                . "<b>Mensagem:</b>\n<i>{$messageText}</i>\n\n"
                                . "👉 <b>Para responder diretamente, envie:</b>\n"
                                . "<code>/responder {$chatId} [sua resposta]</code>\n\n"
                                . "💡 <i>Você também pode responder pelo painel Filament no Mural do Telegram.</i>";
                            try {
                                $this->telegramService->sendMessage((string)$professor->telegram_chat_id, $professorMsg);
                            } catch (\Exception $ex) {
                                Log::error("Erro ao notificar professor sobre contato externo via suporte: " . $ex->getMessage());
                            }
                        }

                        $this->telegramService->sendMessage((string)$chatId, "✅ <b>Dúvida Enviada!</b>\n\nSua mensagem foi entregue com sucesso ao Prof. Atilio. Você receberá uma resposta diretamente neste chat em breve!");
                    }
                    return response()->json(['status' => 'ok']);
                }

                // Fallback para qualquer outra mensagem de texto enviada ao Bot
                $student = Student::where('telegram_chat_id', $chatId)->first();

                if ($user && $user->role === 'admin') {
                    // Evitar loops ou salvamentos de mensagens enviadas pelo próprio administrador
                    $this->telegramService->sendMessage((string)$chatId, "Olá, Prof. Atilio! Para responder a um contato externo, use o comando:\n<code>/responder [chat_id] [sua resposta]</code>");
                    return response()->json(['status' => 'ok']);
                }

                if ($student) {
                    \App\Models\TelegramMessage::create([
                        'student_id' => $student->id,
                        'chat_id' => (string) $chatId,
                        'message_text' => $text,
                    ]);

                    $studentName = $student->user ? $student->user->name : 'Estudante';
                    $fallbackMsg = "Olá, <b>{$studentName}</b>!\n\nRecebi sua mensagem e ela foi registrada no painel do Prof. Atilio! 📬\n\n💡 <i>Dica: Envie <code>/status</code> para ver os detalhes da sua conta vinculada.</i>";
                    $this->telegramService->sendMessage((string)$chatId, $fallbackMsg);
                } else {
                    // Contato Externo
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

                    // Notificar o Professor em tempo real
                    $professor = \App\Models\User::where('role', 'admin')->whereNotNull('telegram_chat_id')->first();
                    if ($professor) {
                        $professorMsg = "📬 <b>Mensagem de Contato Externo Recebida!</b>\n\n"
                            . "<b>Remetente:</b> {$senderDisplay}\n"
                            . "<b>Chat ID:</b> <code>{$chatId}</code>\n"
                            . "<b>Mensagem:</b>\n<i>{$text}</i>\n\n"
                            . "👉 <b>Para responder diretamente, envie:</b>\n"
                            . "<code>/responder {$chatId} [sua resposta]</code>\n\n"
                            . "💡 <i>Você também pode responder pelo painel Filament no Mural do Telegram.</i>";
                        try {
                            $this->telegramService->sendMessage((string)$professor->telegram_chat_id, $professorMsg);
                        } catch (\Exception $ex) {
                            Log::error("Erro ao notificar professor sobre contato externo: " . $ex->getMessage());
                        }
                    }

                    $fallbackMsg = "Olá! Sou o assistente do Prof. Atilio. Recebi sua mensagem com sucesso e ela foi encaminhada ao painel dele. Se for necessário, o professor responderá você diretamente por este chat! 📬";
                    $this->telegramService->sendMessage((string)$chatId, $fallbackMsg);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
