<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NecropsyRequest extends Model
{
    protected $fillable = [
        'token',
        'animal_name',
        'species',
        'breed',
        'age',
        'sex',
        'pelage',
        'weight',
        'has_cremation_plan',
        'cremation_company',
        'is_euthanasia',
        'requested_by_vet',
        'vet_crmv',
        'vet_state',
        'vet_establishment',
        'requester_name',
        'requester_email',
        'requester_phone',
        'requester_cpf',
        'requester_rg',
        'requester_cnpj',
        'requester_address',
        'requester_neighborhood',
        'requester_complement',
        'requester_city',
        'requester_state',
        'requester_zip',
        'has_prosthesis',
        'prosthesis_details',
        'had_surgery',
        'surgery_details',
        'witness1_name',
        'witness1_cpf',
        'witness2_name',
        'witness2_cpf',
        'price',
        'discount',
        'final_price',
        'status',
        'contacted_at',
        'signed_at',
        'signature_image',
        'signature_name',
        'forensic_hash',
        'forensic_ip',
        'forensic_timestamp',
        'forensic_user_agent',
        'signed_pdf_path',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'final_price' => 'decimal:2',
        'has_cremation_plan' => 'boolean',
        'is_euthanasia' => 'boolean',
        'requested_by_vet' => 'boolean',
        'has_prosthesis' => 'boolean',
        'had_surgery' => 'boolean',
        'signed_at' => 'datetime',
        'contacted_at' => 'datetime',
    ];

    /** Orçamentos que ainda esperam retorno do professor. */
    public function scopeAwaitingReply($query)
    {
        return $query->where('status', 'quote_generated')
            ->whereNull('contacted_at')
            ->whereNotNull('requester_phone')
            ->where('requester_phone', '!=', '');
    }

    /** Telefone só com dígitos, com DDI 55 quando vier só DDD + número. */
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->requester_phone);

        if (strlen($digits) < 10) {
            return null;
        }

        if (strlen($digits) <= 11 && ! str_starts_with($digits, '55')) {
            $digits = '55' . $digits;
        }

        return $digits;
    }

    /** Link wa.me com a mensagem já escrita (padrão: retorno ao pedido de orçamento). */
    public function whatsappUrl(?string $text = null): ?string
    {
        $number = $this->whatsappNumber();
        if (! $number) {
            return null;
        }

        $text ??= $this->greeting()
            . "Recebi seu pedido de necropsia ({$this->animalLabel()}, {$this->weight} kg). "
            . "O valor do exame fica em {$this->formattedPrice()}, já com relatório macroscópico, histopatológico e fotodocumentação. "
            . 'Posso te passar as orientações para o envio do animal?';

        return "https://wa.me/{$number}?text=" . rawurlencode($text);
    }

    /** Link pessoal do termo de autorização (passo 2) deste pedido. */
    public function authorizationUrl(): string
    {
        return route('necropsy.autorizar', ['token' => $this->token]);
    }

    /** Mensagem pronta para colar no WhatsApp com o link do termo deste pedido. */
    public function authorizationMessage(): string
    {
        return $this->greeting()
            . "Conforme combinamos, segue o link para preencher e assinar a autorização da necropsia ({$this->animalLabel()}, {$this->weight} kg — {$this->formattedPrice()}):\n\n"
            . $this->authorizationUrl() . "\n\n"
            . 'Leva poucos minutos e a assinatura é feita no próprio celular. Qualquer dúvida, é só me chamar aqui.';
    }

    /** Mensagem pronta com o link fixo, para quem combinou e ainda não tem pedido no sistema. */
    public static function directLinkMessage(): string
    {
        return "Olá! Aqui é o Prof. Atilio. Conforme combinamos, preencha os dados do animal e assine a autorização da necropsia por este link:\n\n"
            . route('necropsy.direto') . "\n\n"
            . 'Leva poucos minutos e a assinatura é feita no próprio celular. Qualquer dúvida, é só me chamar aqui.';
    }

    protected function greeting(): string
    {
        $firstName = trim(explode(' ', trim((string) $this->requester_name))[0] ?? '');

        return 'Olá' . ($firstName ? ", {$firstName}" : '') . '! Aqui é o Prof. Atilio. ';
    }

    protected function animalLabel(): string
    {
        return $this->animal_name ?: mb_strtolower((string) $this->species);
    }

    public function formattedPrice(): string
    {
        return 'R$ ' . number_format((float) $this->final_price, 2, ',', '.');
    }

    /**
     * Calculate price and discount for a given weight and vet request status.
     * 
     * @param float $weight
     * @param bool $requestedByVet
     * @return array Containing 'price', 'discount', and 'final_price'
     */
    public static function calculatePricing(float $weight, bool $requestedByVet): array
    {
        // Pricing brackets based on weight:
        // - Até 10kg: R$ 400
        // - Entre 10kg e 25kg: R$ 600
        // - Entre 25kg e 45kg: R$ 800
        // - Acima de 45kg: R$ 1000
        if ($weight <= 10.00) {
            $basePrice = 400.00;
        } elseif ($weight <= 25.00) {
            $basePrice = 600.00;
        } elseif ($weight <= 45.00) {
            $basePrice = 800.00;
        } else {
            $basePrice = 1000.00;
        }

        // Apply a 10% discount if requested by a veterinarian
        $discount = 0.00;
        if ($requestedByVet) {
            $discount = $basePrice * 0.10;
        }

        $finalPrice = $basePrice - $discount;

        return [
            'price' => $basePrice,
            'discount' => $discount,
            'final_price' => $finalPrice,
        ];
    }
}
