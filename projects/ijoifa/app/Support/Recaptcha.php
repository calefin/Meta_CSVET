<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around Google reCAPTCHA v3 (score-based, invisible).
 * Verification is skipped entirely when RECAPTCHA_SECRET_KEY isn't
 * configured, so local/dev environments work without credentials.
 */
class Recaptcha
{
    private const MIN_SCORE = 0.5;

    public static function enabled(): bool
    {
        return (bool) config('services.recaptcha.site_key');
    }

    public static function verify(?string $token, string $expectedAction): bool
    {
        $secret = config('services.recaptcha.secret_key');

        if (! $secret) {
            return true;
        }

        if (! $token) {
            Log::warning('recaptcha verification failed: no token received from client', [
                'expected_action' => $expectedAction,
                'ip' => request()->ip(),
            ]);

            return false;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $token,
        ])->json();

        $passed = ($response['success'] ?? false)
            && ($response['action'] ?? null) === $expectedAction
            && ($response['score'] ?? 0) >= self::MIN_SCORE;

        if (! $passed) {
            Log::warning('recaptcha verification failed', [
                'expected_action' => $expectedAction,
                'google_success' => $response['success'] ?? null,
                'google_action' => $response['action'] ?? null,
                'google_score' => $response['score'] ?? null,
                'google_error_codes' => $response['error-codes'] ?? null,
                'ip' => request()->ip(),
            ]);
        }

        return $passed;
    }
}
