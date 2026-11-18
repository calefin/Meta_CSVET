<?php

namespace App\Services;

use App\Mail\AppNotificationMail;
use App\Models\NecropsyRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisos ao Prof. Atilio sobre pedidos de necropsia.
 *
 * Envio síncrono (não depende do cron da fila) e cada canal com seu próprio
 * try/catch: falha no e-mail não impede o Telegram, e vice-versa.
 */
class NecropsyNotificationService
{
    public const ADMIN_EMAIL = 'contato@profatilio.com.br';

    public function __construct(protected TelegramService $telegram)
    {
    }

    /** Passo 1: alguém calculou um orçamento. É aqui que o professor precisa retornar rápido. */
    public function notifyNewQuote(NecropsyRequest $r, bool $direct = false): void
    {
        $price = $this->money($r->final_price);
        $whatsapp = $r->whatsappUrl();

        $intro = $direct
            ? "Uma pessoa que já combinou a necropsia com você preencheu os dados pelo link direto. Ela segue agora para o termo de autorização.\n\n"
            : "Um novo pedido de orçamento de necropsia chegou pelo site. Retorne o quanto antes!\n\n";

        $body = $intro
            . "👤 **Contato**\n"
            . "- Nome: {$r->requester_name}\n"
            . "- WhatsApp/Telefone: {$r->requester_phone}\n"
            . "- E-mail: " . ($r->requester_email ?: 'não informado') . "\n\n"
            . "🐾 **Animal**\n"
            . "- Espécie: {$r->species}" . ($r->breed ? " ({$r->breed})" : '') . "\n"
            . "- Peso: {$r->weight} kg\n"
            . "- Eutanásia: " . ($r->is_euthanasia ? 'sim' : 'não') . "\n"
            . "- Plano de cremação: " . ($r->has_cremation_plan ? "sim ({$r->cremation_company})" : 'não') . "\n"
            . "- Solicitado por veterinário: " . ($r->requested_by_vet ? "sim (CRMV {$r->vet_crmv}/{$r->vet_state}" . ($r->vet_establishment ? " — {$r->vet_establishment}" : '') . ')' : 'não') . "\n\n"
            . "💰 **Orçamento calculado:** {$price}\n\n"
            . ($whatsapp ? "Responder pelo WhatsApp: {$whatsapp}\n\n" : '')
            . "O interessado ainda não assinou a autorização (passo 2).";

        $this->sendAdminEmail(
            $direct
                ? "Necropsia combinada: dados preenchidos ({$r->species}, {$r->weight} kg)"
                : "Novo pedido de orçamento de necropsia: {$r->species}, {$r->weight} kg",
            $body,
            $r,
            $direct
                ? "Necropsia combinada — {$r->requester_name} preencheu os dados"
                : "🚨 Orçamento de Necropsia — {$r->requester_name} — retornar"
        );

        $header = $direct
            ? "<b>🤝 Necropsia COMBINADA — dados preenchidos</b>\n<i>Pelo link direto; segue para o termo</i>\n\n"
            : "<b>🚨 Novo pedido de orçamento de NECROPSIA</b>\n<i>Retornar o quanto antes</i>\n\n";

        $msg = $header
            . "👤 <b>Nome:</b> " . e($r->requester_name) . "\n"
            . "📞 <b>WhatsApp:</b> " . e($r->requester_phone) . "\n"
            . ($r->requester_email ? "✉️ <b>E-mail:</b> " . e($r->requester_email) . "\n" : '')
            . "\n🐾 <b>Animal:</b> " . e($r->species) . ", {$r->weight} kg\n"
            . ($r->is_euthanasia ? "💉 Eutanásia\n" : '')
            . ($r->requested_by_vet ? "🩺 Veterinário: CRMV " . e("{$r->vet_crmv}/{$r->vet_state}") . "\n" : '')
            . "💰 <b>Orçamento:</b> {$price}\n\n"
            . ($whatsapp ? "💬 <a href=\"" . e($whatsapp) . "\">Responder no WhatsApp</a>\n" : '')
            . "👉 <a href=\"" . e($this->adminUrl($r)) . "\">Abrir no painel</a>";

        $this->sendTelegram($msg);
    }

    /** Passo 2: termo de autorização assinado. */
    public function notifyAuthorized(NecropsyRequest $r): void
    {
        $price = $this->money($r->final_price);

        $body = "Uma Autorização para Exame Necroscópico foi preenchida e assinada digitalmente pelo site!\n\n"
            . "🐾 **Dados do Animal**\n"
            . "- Nome: {$r->animal_name}\n"
            . "- Espécie: {$r->species}\n"
            . "- Peso: {$r->weight} kg\n\n"
            . "👤 **Dados do Requisitante**\n"
            . "- Nome: {$r->requester_name}\n"
            . "- E-mail: {$r->requester_email}\n"
            . "- Telefone: {$r->requester_phone}\n\n"
            . "💰 **Valor:** {$price}\n\n"
            . "⚖️ **Auditoria Criptográfica**\n"
            . "- Hash Forense: {$r->forensic_hash}\n"
            . "- IP de Assinatura: {$r->forensic_ip}\n"
            . "- Data/Hora: {$r->forensic_timestamp}\n\n"
            . ($r->signed_pdf_path ? 'O termo em PDF foi gerado.' : '⚠️ O PDF do termo NÃO foi gerado — confira o log.');

        $this->sendAdminEmail(
            'Nova Autorização de Necropsia Recebida',
            $body,
            $r,
            "Nova Autorização de Necropsia — {$r->animal_name} ({$r->requester_name})"
        );

        $whatsapp = $r->whatsappUrl();
        $msg = "<b>✅ Necropsia AUTORIZADA e assinada!</b>\n\n"
            . "🐾 <b>Animal:</b> " . e($r->animal_name) . " (" . e($r->species) . ")\n"
            . "⚖️ <b>Peso:</b> {$r->weight} kg\n"
            . "👤 <b>Responsável:</b> " . e($r->requester_name) . "\n"
            . "📞 <b>Telefone:</b> " . e($r->requester_phone) . "\n"
            . "💰 <b>Valor:</b> {$price}\n\n"
            . "📝 <b>Hash:</b> <code>" . substr((string) $r->forensic_hash, 0, 16) . "...</code>\n"
            . ($whatsapp ? "💬 <a href=\"" . e($whatsapp) . "\">WhatsApp do responsável</a>\n" : '')
            . "👉 <a href=\"" . e($this->adminUrl($r)) . "\">Abrir no painel</a>";

        $this->sendTelegram($msg);
    }

    protected function sendAdminEmail(string $title, string $body, NecropsyRequest $r, string $subject): void
    {
        try {
            $mail = new AppNotificationMail(
                title: $title,
                messageContent: $body,
                actionUrl: $this->adminUrl($r),
                actionText: 'Abrir pedido no painel',
                subject: $subject,
            );

            if ($r->requester_email) {
                $mail->replyTo($r->requester_email, (string) $r->requester_name);
            }

            Mail::to(self::ADMIN_EMAIL)->send($mail);
        } catch (\Throwable $e) {
            Log::error("Necropsia #{$r->id}: falha ao enviar e-mail ao professor: " . $e->getMessage());
        }
    }

    protected function sendTelegram(string $msg): void
    {
        try {
            $admins = User::where('role', 'admin')
                ->whereNotNull('telegram_chat_id')
                ->where('telegram_chat_id', '!=', '')
                ->get();

            if ($admins->isEmpty()) {
                Log::warning('Necropsia: nenhum admin com Telegram vinculado para receber o aviso.');
            }

            foreach ($admins as $admin) {
                $this->telegram->sendMessage((string) $admin->telegram_chat_id, $msg);
            }
        } catch (\Throwable $e) {
            Log::error('Necropsia: falha ao enviar aviso no Telegram: ' . $e->getMessage());
        }
    }

    protected function adminUrl(NecropsyRequest $r): string
    {
        return url("/admin/necropsy-requests/{$r->id}/edit");
    }

    protected function money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}
