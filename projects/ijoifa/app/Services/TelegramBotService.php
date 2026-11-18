<?php

namespace App\Services;

use App\Models\TelegramCourseSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    public static function configured(): bool
    {
        return (bool) config('services.telegram.bot_token');
    }

    public static function sendMessage(string $chatId, string $text, string $parseMode = 'HTML'): bool
    {
        $token = config('services.telegram.bot_token');
        if (! $token || ! $chatId) {
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram Bot sendMessage error: ' . $e->getMessage());
            return false;
        }
    }

    public static function createChatInviteLink(string $chatId, int $expireSeconds = 86400, int $memberLimit = 1): ?string
    {
        $token = config('services.telegram.bot_token');
        if (! $token || ! $chatId) {
            return null;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/createChatInviteLink", [
                'chat_id' => $chatId,
                'expire_date' => time() + $expireSeconds,
                'member_limit' => $memberLimit,
            ]);

            if ($response->successful() && isset($response->json()['result']['invite_link'])) {
                return $response->json()['result']['invite_link'];
            }
        } catch (\Throwable $e) {
            Log::error('Telegram Bot createChatInviteLink error: ' . $e->getMessage());
        }

        return null;
    }

    public static function banChatMember(string $chatId, string|int $userId): bool
    {
        $token = config('services.telegram.bot_token');
        if (! $token || ! $chatId) {
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/banChatMember", [
                'chat_id' => $chatId,
                'user_id' => $userId,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram Bot banChatMember error: ' . $e->getMessage());
            return false;
        }
    }

    public static function unbanChatMember(string $chatId, string|int $userId): bool
    {
        $token = config('services.telegram.bot_token');
        if (! $token || ! $chatId) {
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/unbanChatMember", [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'only_if_banned' => true,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Telegram Bot unbanChatMember error: ' . $e->getMessage());
            return false;
        }
    }

    public static function notifyAdmin(string $text, string $parseMode = 'HTML'): bool
    {
        $adminChatId = config('services.telegram.admin_chat_id');
        if (! $adminChatId) {
            return false;
        }

        return self::sendMessage($adminChatId, $text, $parseMode);
    }

    public static function sendSubscriptionConfirmedNotification(TelegramCourseSubscription $sub, float $amount): bool
    {
        $expiryStr = $sub->expires_at ? $sub->expires_at->format('d/m/Y') : 'N/A';
        $amountStr = number_format($amount, 2, ',', '.');

        $text = "🎉 <b>Mensalidade de Curso Confirmada!</b>\n\n"
            . "👤 <b>Aluno:</b> {$sub->full_name}\n"
            . "✈️ <b>Telegram:</b> {$sub->telegram_username}\n"
            . "📧 <b>E-mail:</b> {$sub->email}\n"
            . "💰 <b>Valor:</b> R$ {$amountStr}\n"
            . "📅 <b>Novo Vencimento:</b> {$expiryStr}\n\n"
            . "<i>O acesso ao grupo foi renovado no sistema.</i>";

        return self::notifyAdmin($text);
    }

    public static function generateCourseStatusReport(): string
    {
        $subs = TelegramCourseSubscription::orderBy('full_name')->get();

        $activeList = [];
        $overdueList = [];
        $pendingList = [];

        foreach ($subs as $sub) {
            $line = "- {$sub->full_name} ({$sub->telegram_username})";
            if ($sub->isActive()) {
                $line .= " - Vencimento: " . ($sub->expires_at ? $sub->expires_at->format('d/m/Y') : 'N/A');
                $activeList[] = $line;
            } elseif ($sub->isOverdue()) {
                $line .= " - Vencido em: " . ($sub->expires_at ? $sub->expires_at->format('d/m/Y') : 'Pendente');
                $overdueList[] = $line;
            } elseif ($sub->status !== 'cancelled') {
                $pendingList[] = $line . " - Aguardando 1º pagamento";
            }
        }

        $monthYear = now()->translatedFormat('F/Y');

        $report = "📋 <b>RELATÓRIO MENSAL - CURSO TELEGRAM ({$monthYear})</b>\n\n";

        $report .= "✅ <b>ALUNOS ATIVOS (ADIMPLENTES) [" . count($activeList) . "]:</b>\n";
        $report .= count($activeList) > 0 ? implode("\n", $activeList) : "<i>Nenhum aluno ativo no momento.</i>";

        if (count($overdueList) > 0) {
            $report .= "\n\n⚠️ <b>PENDENTES / ATRASADOS [" . count($overdueList) . "]:</b>\n";
            $report .= implode("\n", $overdueList);
        }

        if (count($pendingList) > 0) {
            $report .= "\n\n⏳ <b>AGUARDANDO PRIMEIRA MENSALIDADE [" . count($pendingList) . "]:</b>\n";
            $report .= implode("\n", $pendingList);
        }

        return $report;
    }
}
