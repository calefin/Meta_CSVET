<?php

namespace Tests\Feature;

use App\Support\Recaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecaptchaTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_is_skipped_when_secret_key_is_not_configured(): void
    {
        config(['services.recaptcha.secret_key' => null]);

        $this->assertTrue(Recaptcha::verify(null, 'register'));
        $this->assertTrue(Recaptcha::verify('whatever', 'register'));
    }

    public function test_verification_fails_without_a_token_when_secret_is_configured(): void
    {
        config(['services.recaptcha.secret_key' => 'fake-secret']);

        $this->assertFalse(Recaptcha::verify(null, 'register'));
    }

    public function test_verification_passes_with_a_high_score_matching_action(): void
    {
        config(['services.recaptcha.secret_key' => 'fake-secret']);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'action' => 'register',
                'score' => 0.9,
            ]),
        ]);

        $this->assertTrue(Recaptcha::verify('valid-token', 'register'));
    }

    public function test_verification_fails_with_a_low_score(): void
    {
        config(['services.recaptcha.secret_key' => 'fake-secret']);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'action' => 'register',
                'score' => 0.1,
            ]),
        ]);

        $this->assertFalse(Recaptcha::verify('bot-token', 'register'));
    }

    public function test_verification_fails_when_action_does_not_match(): void
    {
        config(['services.recaptcha.secret_key' => 'fake-secret']);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'action' => 'login',
                'score' => 0.9,
            ]),
        ]);

        $this->assertFalse(Recaptcha::verify('token', 'register'));
    }
}
