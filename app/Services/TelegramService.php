<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Sends an HTML formatted message to a Telegram Chat ID.
     *
     * @param string $chatId
     * @param string $message
     * @return bool
     */
    public function sendMessage(string $chatId, string $message): bool
    {
        $token = config('services.telegram.bot_token');

        if (!$token) {
            Log::warning('Telegram Service: Bot token is not configured in config/services.php.');
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('Telegram Service Error response: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('Telegram Service Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generates the Telegram bot invite link for a specific student.
     *
     * @param mixed $student
     * @return string
     */
    public function getInviteLink($student): string
    {
        $botUsername = config('services.telegram.bot_username');
        if (!$botUsername) {
            return '#';
        }
        $cleanUsername = ltrim($botUsername, '@');

        $studentModel = is_numeric($student) ? \App\Models\Student::find($student) : $student;
        if (!$studentModel) {
            return '#';
        }

        if (empty($studentModel->telegram_link_token)) {
            $studentModel->telegram_link_token = \Illuminate\Support\Str::random(32);
            $studentModel->save();
        }

        return "https://t.me/{$cleanUsername}?start={$studentModel->telegram_link_token}";
    }
}
