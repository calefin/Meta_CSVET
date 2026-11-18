<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Student;
use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class TelegramAdminWidget extends Widget
{
    protected static string $view = 'filament.widgets.telegram-admin-widget';

    protected static ?int $sort = -1; // Exibe no topo do Dashboard do admin

    protected int | string | array $columnSpan = 'full';

    /**
     * Define a visibilidade do widget na dashboard (somente para administrador).
     */
    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    /**
     * Busca dados do Bot via API do Telegram com cache de 10 minutos para evitar lentidão.
     */
    public function getBotData(): array
    {
        $token = config('services.telegram.bot_token');
        if (empty($token)) {
            return [
                'status' => 'offline',
                'name' => 'Não Configurado',
                'username' => '',
            ];
        }

        return Cache::remember('telegram_bot_info', 600, function () use ($token) {
            try {
                $response = Http::timeout(3)->get("https://api.telegram.org/bot{$token}/getMe");
                if ($response->successful()) {
                    $result = $response->json()['result'] ?? [];
                    return [
                        'status' => 'online',
                        'name' => $result['first_name'] ?? 'Bot de Orientação',
                        'username' => $result['username'] ?? '',
                    ];
                }
            } catch (\Exception $e) {
                // Falha silenciosa
            }

            return [
                'status' => 'error',
                'name' => 'Erro de Conexão',
                'username' => '',
            ];
        });
    }

    /**
     * Busca estatísticas de integração.
     */
    public function getStats(): array
    {
        $totalStudents = Student::count();
        $linkedStudents = Student::whereNotNull('telegram_chat_id')->where('telegram_chat_id', '!=', '')->count();
        $unreadMessages = TelegramMessage::where('is_read', false)->count();

        return [
            'total_students' => $totalStudents,
            'linked_students' => $linkedStudents,
            'unread_messages' => $unreadMessages,
        ];
    }

    /**
     * Desvincula o Telegram do administrador logado.
     */
    public function unlinkAdminTelegram()
    {
        if (auth()->check()) {
            $user = auth()->user();
            $user->telegram_chat_id = null;
            $user->save();

            \Filament\Notifications\Notification::make()
                ->title('Telegram Desvinculado')
                ->body('Seu Telegram foi desvinculado com sucesso de sua conta de administrador.')
                ->success()
                ->send();
        }
    }

    /**
     * Envia o resumo diário de solicitações manualmente para o Telegram do administrador.
     */
    public function sendDailySummary()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('app:send-daily-summary');

            \Filament\Notifications\Notification::make()
                ->title('Resumo Enviado!')
                ->body('O resumo diário das solicitações foi compilado e enviado para o seu Telegram.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao enviar resumo manualmente: ' . $e->getMessage());

            \Filament\Notifications\Notification::make()
                ->title('Erro ao Enviar Resumo')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
