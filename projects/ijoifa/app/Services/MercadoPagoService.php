<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoService
{
    public static function configured(): bool
    {
        return (bool) config('services.mercadopago.access_token');
    }

    private function client(): void
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
    }

    /**
     * Creates a Checkout Pro preference for the given payment and stores
     * the preference id + checkout URL back onto it. Returns null (instead
     * of letting the SDK exception bubble up as an uncaught 500) if the
     * Mercado Pago API call fails - invalid/revoked token, network issue,
     * or an invalid item (e.g. unit_price of 0) all throw from the SDK.
     */
    public function createPreference(Payment $payment, string $title, ?int $maxInstallments = null): ?string
    {
        $this->client();

        $preferenceClient = new PreferenceClient;

        $data = [
            'items' => [[
                'title' => $title,
                'quantity' => 1,
                'unit_price' => round((float) $payment->amount, 2),
                'currency_id' => 'BRL',
            ]],
            'payer' => [
                'email' => $payment->user->email,
            ],
            'external_reference' => (string) $payment->id,
            'notification_url' => config('services.mercadopago.webhook_url'),
            'back_urls' => [
                'success' => route('dashboard'),
                'failure' => route('dashboard'),
                'pending' => route('dashboard'),
            ],
        ];

        if ($maxInstallments !== null) {
            $data['payment_methods'] = [
                'installments' => $maxInstallments,
                'default_installments' => $maxInstallments,
            ];
        }

        try {
            $preference = $preferenceClient->create($data);
        } catch (\Throwable $e) {
            Log::error('Mercado Pago preference creation failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $payment->update([
            'mp_preference_id' => $preference->id,
            'checkout_url' => $preference->init_point,
        ]);

        return $preference->init_point;
    }

    public function fetchPayment(string $mpPaymentId): object
    {
        $this->client();

        return (new PaymentClient)->get($mpPaymentId);
    }
}
