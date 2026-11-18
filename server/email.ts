import { Resend } from 'resend';
import nodemailer from 'nodemailer';

const realResend = new Resend(process.env.RESEND_API_KEY ?? 're_placeholder');

// Configure SMTP transport if variables are present
const smtpConfig = {
  // Nome EHLO: dentro do container o nodemailer se apresenta como "[127.0.0.1]" e a HostGator aceita (250), mas não entrega
  name: 'vetrank.com.br',
  host: process.env.SMTP_HOST,
  port: Number(process.env.SMTP_PORT || 587),
  secure: process.env.SMTP_SECURE === 'true', // true for 465, false for other ports
  auth: {
    user: process.env.SMTP_USER,
    pass: process.env.SMTP_PASS,
  },
};

const hasSmtp = !!(smtpConfig.host && smtpConfig.auth?.user && smtpConfig.auth?.pass);

// O servidor SMTP da HostGator recusa conexões acima de ~5 simultâneas por IP (ECONNREFUSED).
// Antes, cada envio abria a própria conexão e os lotes de 20 em paralelo faziam ~190 de ~196 falharem.
// Agora: pool com poucas conexões reaproveitadas, e um pool separado e com ritmo limitado para envios
// em massa (questão do dia, digest, newsletter, campanhas) — assim e-mails transacionais (senha,
// recibo, tickets) não ficam presos atrás da fila em massa.
const transporter = hasSmtp
  ? nodemailer.createTransport({ ...smtpConfig, pool: true, maxConnections: 1, maxMessages: 50 })
  : null;
const BULK_PER_MINUTE = Math.max(1, Number(process.env.SMTP_BULK_PER_MINUTE || 8)); // ~480/h
const bulkTransporter = hasSmtp
  ? nodemailer.createTransport({
      ...smtpConfig,
      pool: true,
      maxConnections: 2,
      maxMessages: 50,
      rateDelta: 60_000,
      rateLimit: BULK_PER_MINUTE,
    })
  : null;

// Wrapper to replace resend.emails.send dynamically without rewriting all call sites
const resend = {
  emails: {
    send: async (options: { from: string; to: string | string[]; subject: string; html: string; bulk?: boolean }) => {
      const toStr = Array.isArray(options.to) ? options.to.join(', ') : options.to;
      const smtp = options.bulk ? bulkTransporter : transporter;
      if (smtp) {
        try {
          const info = await smtp.sendMail({
            from: options.from,
            to: toStr,
            subject: options.subject,
            html: options.html,
          });
          return { data: { id: info.messageId }, error: null };
        } catch (err: any) {
          console.error(`[SMTP ERROR] To: ${toStr} | Subject: ${options.subject} | ${err?.code ?? ''} ${err?.responseCode ?? ''} ${err?.message ?? err}`);
          throw err;
        }
      } else if (process.env.RESEND_API_KEY && process.env.RESEND_API_KEY !== 're_placeholder') {
        // Resend does NOT throw on failure — it returns { error }. Without this check every
        // caller's .catch() is dead code and failures (unverified domain, rate limit…) vanish silently.
        const { bulk: _bulk, ...resendOptions } = options;
        const result = await realResend.emails.send(resendOptions);
        if (result.error) {
          console.error(`[RESEND ERROR] To: ${toStr} | Subject: ${options.subject} |`, result.error);
          throw new Error(result.error.message || 'Resend send failed');
        }
        return result;
      } else {
        console.log(`[EMAIL DEV MOCK] From: ${options.from} | To: ${toStr} | Subject: ${options.subject}`);
        return { data: { id: 'mock_id' }, error: null };
      }
    }
  }
};

function esc(v: unknown): string {
  return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } as Record<string, string>)[c]);
}

const FROM = process.env.EMAIL_FROM || 'VetRank <noreply@vetrank.com.br>';
const SUPPORT = 'suporte@vetrank.com.br';
const SITE_URL = 'https://www.vetrank.com.br';

// ─── Base HTML Template ────────────────────────────────────────────────────
function baseTemplate(content: string, preheader = '') {
  return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>VetRank</title>
</head>
<body style="margin:0;padding:0;background:#0d1a14;font-family:'Inter',Arial,sans-serif;">
  ${preheader ? `<div style="display:none;max-height:0;overflow:hidden;">${preheader}</div>` : ''}
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0d1a14;padding:32px 16px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
        <!-- Header -->
        <tr>
          <td style="background:#0f2318;border-radius:16px 16px 0 0;padding:28px 40px;border-bottom:1px solid #1e3a2a;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td>
                  <span style="font-size:24px;font-weight:700;color:#22c55e;letter-spacing:-0.5px;">⚡ VetRank</span>
                </td>
                <td align="right">
                  <span style="font-size:12px;color:#4ade80;background:#14532d;padding:4px 10px;border-radius:20px;">Medicina Veterinária</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="background:#111f17;padding:40px;border-radius:0 0 16px 16px;">
            ${content}
            <!-- Footer -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:40px;padding-top:24px;border-top:1px solid #1e3a2a;">
              <tr>
                <td align="center">
                  <p style="color:#4b7a5e;font-size:12px;margin:0 0 8px;">
                    VetRank — Plataforma Gamificada de Estudos para Veterinária
                  </p>
                  <p style="color:#4b7a5e;font-size:12px;margin:0 0 8px;">
                    Suporte: <a href="mailto:${SUPPORT}" style="color:#22c55e;">${SUPPORT}</a>
                  </p>
                  <p style="color:#4b7a5e;font-size:11px;margin:0;">
                    <a href="${SITE_URL}/privacy" style="color:#4b7a5e;">Política de Privacidade</a> · 
                    <a href="${SITE_URL}/terms" style="color:#4b7a5e;">Termos de Uso</a>
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>`;
}

function btn(text: string, href: string) {
  return `<a href="${href}" style="display:inline-block;background:#16a34a;color:#fff;font-weight:600;font-size:15px;padding:14px 32px;border-radius:10px;text-decoration:none;margin:24px 0;">${text}</a>`;
}

// ─── Email Templates ───────────────────────────────────────────────────────

export function trialExpiringTemplate(name: string, daysLeft: number) {
  const urgency = daysLeft <= 1 ? '🚨 Último dia!' : `⏰ ${daysLeft} dias restantes`;
  const content = `
    <h1 style="color:#f0fdf4;font-size:26px;font-weight:700;margin:0 0 8px;">${urgency}</h1>
    <p style="color:#86efac;font-size:16px;margin:0 0 24px;">Seu trial gratuito do VetRank está prestes a expirar.</p>
    <p style="color:#d1fae5;font-size:15px;line-height:1.7;margin:0 0 16px;">
      Olá, <strong style="color:#4ade80;">${name}</strong>! 👋
    </p>
    <p style="color:#d1fae5;font-size:15px;line-height:1.7;margin:0 0 16px;">
      Seu período de trial gratuito de 30 dias termina em <strong style="color:#fbbf24;">${daysLeft === 1 ? 'hoje' : `${daysLeft} dias`}</strong>. 
      Não perca acesso ao banco completo de <strong>5.661 questões</strong>, simulados ilimitados e ranking.
    </p>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f2318;border-radius:12px;padding:20px;margin:24px 0;">
      <tr>
        <td>
          <p style="color:#4ade80;font-size:14px;font-weight:600;margin:0 0 12px;">✅ O que você perde sem o Premium:</p>
          <p style="color:#d1fae5;font-size:14px;margin:4px 0;">• Banco completo de 5.661 questões ENADE</p>
          <p style="color:#d1fae5;font-size:14px;margin:4px 0;">• Simulados ilimitados com timer</p>
          <p style="color:#d1fae5;font-size:14px;margin:4px 0;">• Ranking semanal, mensal e geral</p>
          <p style="color:#d1fae5;font-size:14px;margin:4px 0;">• Badges e gamificação completa</p>
        </td>
      </tr>
    </table>
    <p style="color:#d1fae5;font-size:15px;margin:0 0 8px;">
      Assine o plano anual por apenas <strong style="color:#4ade80;">R$299/ano</strong> (equivale a R$24,90/mês) e economize 37% em relação ao mensal.
    </p>
    ${btn('Assinar Agora — R$299/ano', `${SITE_URL}/pricing`)}
    <p style="color:#4b7a5e;font-size:13px;margin:8px 0 0;">
      Dúvidas? Responda este email ou entre em contato: <a href="mailto:${SUPPORT}" style="color:#22c55e;">${SUPPORT}</a>
    </p>
  `;
  return baseTemplate(content, `Seu trial VetRank expira em ${daysLeft === 1 ? 'hoje' : `${daysLeft} dias`} — assine para continuar`);
}

export function premiumExpiringTemplate(name: string, daysLeft: number) {
  const content = `
    <h1 style="color:#f0fdf4;font-size:26px;font-weight:700;margin:0 0 8px;">⏰ Renovação em ${daysLeft} ${daysLeft === 1 ? 'dia' : 'dias'}</h1>
    <p style="color:#86efac;font-size:16px;margin:0 0 24px;">Sua assinatura Premium VetRank está próxima do vencimento.</p>
    <p style="color:#d1fae5;font-size:15px;line-height:1.7;margin:0 0 16px;">
      Olá, <strong style="color:#4ade80;">${name}</strong>! 👋
    </p>
    <p style="color:#d1fae5;font-size:15px;line-height:1.7;margin:0 0 16px;">
      Sua assinatura Premium vence em <strong style="color:#fbbf24;">${daysLeft === 1 ? 'amanhã' : `${daysLeft} dias`}</strong>. 
      Renove agora para manter acesso ininterrupto a todos os recursos.
    </p>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f2318;border-radius:12px;padding:20px;margin:24px 0;">
      <tr>
        <td>
          <p style="color:#4ade80;font-size:14px;font-weight:600;margin:0 0 8px;">🏆 Seu progresso até agora:</p>
          <p style="color:#d1fae5;font-size:14px;margin:4px 0;">Continue de onde parou — seu XP, badges e histórico são mantidos.</p>
        </td>
      </tr>
    </table>
    ${btn('Renovar Assinatura', `${SITE_URL}/pricing`)}
    <p style="color:#4b7a5e;font-size:13px;margin:8px 0 0;">
      Precisa de ajuda? <a href="mailto:${SUPPORT}" style="color:#22c55e;">${SUPPORT}</a>
    </p>
  `;
  return baseTemplate(content, `Sua assinatura VetRank vence em ${daysLeft} ${daysLeft === 1 ? 'dia' : 'dias'}`);
}

export function leadCampaignTemplate(subject: string, body: string) {
  const content = `
    <h1 style="color:#f0fdf4;font-size:26px;font-weight:700;margin:0 0 24px;">${subject}</h1>
    <div style="color:#d1fae5;font-size:15px;line-height:1.8;">
      ${body.split('\n').map(p => {
        const trimmed = p.trim();
        if (!trimmed) return '';
        const htmlParagraph = trimmed.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" style="color:#22c55e;text-decoration:underline;" target="_blank">$1</a>');
        return `<p style="margin:0 0 16px;">${htmlParagraph}</p>`;
      }).join('')}
    </div>
    ${btn('Conhecer o VetRank', SITE_URL)}
    <p style="color:#4b7a5e;font-size:13px;margin:16px 0 0;">
      Você recebeu este email pois demonstrou interesse no VetRank.<br/>
      Dúvidas: <a href="mailto:${SUPPORT}" style="color:#22c55e;">${SUPPORT}</a>
    </p>
  `;
  return baseTemplate(content, subject);
}

export async function sendLeadCampaignEmail(to: string, subject: string, body: string) {
  const html = leadCampaignTemplate(subject, body);
  return resend.emails.send({ from: FROM, to, subject, html, bulk: true });
}

export function newsletterTemplate(subject: string, body: string) {
  const content = `
    <h1 style="color:#f0fdf4;font-size:26px;font-weight:700;margin:0 0 24px;">${subject}</h1>
    <div style="color:#d1fae5;font-size:15px;line-height:1.8;">
      ${body.split('\n').map(p => {
        const trimmed = p.trim();
        if (!trimmed) return '';
        const htmlParagraph = trimmed.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" style="color:#22c55e;text-decoration:underline;" target="_blank">$1</a>');
        return `<p style="margin:0 0 16px;">${htmlParagraph}</p>`;
      }).join('')}
    </div>
    ${btn('Acessar VetRank', SITE_URL)}
    <p style="color:#4b7a5e;font-size:13px;margin:16px 0 0;">
      Você está recebendo este email por ser usuário do VetRank.<br/>
      Dúvidas: <a href="mailto:${SUPPORT}" style="color:#22c55e;">${SUPPORT}</a>
    </p>
  `;
  return baseTemplate(content, subject);
}

// ─── Send Functions ────────────────────────────────────────────────────────

export async function sendTrialExpiringEmail(to: string, name: string, daysLeft: number) {
  const subject = daysLeft <= 1
    ? '🚨 Último dia do seu trial VetRank — assine agora!'
    : `⏰ Seu trial VetRank expira em ${daysLeft} dias`;
  const html = trialExpiringTemplate(name || 'Veterinário(a)', daysLeft);
  return resend.emails.send({ from: FROM, to, subject, html });
}

export async function sendPremiumExpiringEmail(to: string, name: string, daysLeft: number) {
  const subject = daysLeft <= 1
    ? '⏰ Sua assinatura VetRank vence amanhã — renove agora'
    : `⏰ Sua assinatura VetRank vence em ${daysLeft} dias`;
  const html = premiumExpiringTemplate(name || 'Veterinário(a)', daysLeft);
  return resend.emails.send({ from: FROM, to, subject, html });
}

export function replaceNamePlaceholders(text: string, name: string | null): string {
  const finalName = name?.trim() || "Veterinário(a)";
  return text
    .replace(/\[[Nn]ome\]/g, finalName)
    .replace(/\[NOME\]/g, finalName)
    .replace(/\{\{[Nn]ome\}\}/g, finalName)
    .replace(/\{\{NOME\}\}/g, finalName)
    .replace(/\[[Nn]ame\]/g, finalName)
    .replace(/\[NAME\]/g, finalName)
    .replace(/\{\{[Nn]ame\}\}/g, finalName)
    .replace(/\{\{NAME\}\}/g, finalName);
}

export async function sendNewsletterEmail(to: string, subject: string, body: string, bodyHtml?: string, name?: string | null) {
  const nameToUse = name || null;
  const processedSubject = replaceNamePlaceholders(subject, nameToUse);
  const processedBody = replaceNamePlaceholders(body, nameToUse);
  const processedHtml = bodyHtml 
    ? replaceNamePlaceholders(bodyHtml, nameToUse) 
    : newsletterTemplate(processedSubject, processedBody);
  return resend.emails.send({ from: FROM, to, subject: processedSubject, html: processedHtml, bulk: true });
}

export async function sendPasswordResetEmail(to: string, name: string, token: string) {
  const resetUrl = `${SITE_URL}/?reset_token=${token}`;
  const html = baseTemplate(`
    <h2 style="color:#f0fdf4;font-size:22px;font-weight:700;margin:0 0 12px;">Redefinir sua senha</h2>
    <p style="color:#86efac;font-size:15px;margin:0 0 8px;">Olá, ${name}!</p>
    <p style="color:#a3a3a3;font-size:14px;margin:0 0 24px;line-height:1.6;">
      Recebemos uma solicitação para redefinir a senha da sua conta VetRank.<br/>
      Clique no botão abaixo para criar uma nova senha. O link expira em <strong style="color:#f0fdf4;">1 hora</strong>.
    </p>
    ${btn('Redefinir Senha', resetUrl)}
    <p style="color:#6b7280;font-size:12px;margin:24px 0 0;">
      Se você não solicitou a redefinição de senha, ignore este e-mail. Sua senha permanecerá a mesma.
    </p>
  `, 'Redefinição de senha VetRank');
  return resend.emails.send({
    from: FROM,
    to,
    subject: '🔐 Redefinir senha — VetRank',
    html,
  });
}

// ─── Payment Receipt ──────────────────────────────────────────────────────────

export interface ReceiptEmailOpts {
  to: string;
  name: string;
  receiptNumber: string;
  planLabel: string;
  amount: number;
  paymentMethod?: string;
  paidAt: Date;
  premiumEndsAt?: Date;
}

const RECEIPT_CNPJ = process.env.CERT_CNPJ ?? "32.645.724/0001-89";
const RECEIPT_COMPANY = "CSVET Editora, Cursos e Treinamentos";

export async function sendPaymentReceiptEmail(opts: ReceiptEmailOpts) {
  const html = baseTemplate(`
    <h1 style="color:#f0fdf4;font-size:24px;font-weight:700;margin:0 0 8px;">✅ Pagamento confirmado</h1>
    <p style="color:#86efac;font-size:15px;margin:0 0 24px;">Seu acesso Premium foi ativado com sucesso.</p>
    <p style="color:#d1fae5;font-size:15px;line-height:1.7;margin:0 0 20px;">
      Olá, <strong style="color:#4ade80;">${opts.name}</strong>! 👋
    </p>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f2318;border:1px solid #1e3a2a;border-radius:12px;margin:0 0 24px;">
      <tr><td style="padding:24px;">
        <p style="color:#4ade80;font-size:11px;font-weight:700;letter-spacing:1px;margin:0 0 16px;text-transform:uppercase;">Recibo de Pagamento</p>
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="color:#8d9e97;font-size:12px;padding:5px 0;border-bottom:1px solid #1a3026;">Nº do Recibo</td>
            <td align="right" style="color:#f0fdf4;font-size:12px;font-weight:600;padding:5px 0;border-bottom:1px solid #1a3026;">${opts.receiptNumber}</td>
          </tr>
          <tr>
            <td style="color:#8d9e97;font-size:12px;padding:5px 0;border-bottom:1px solid #1a3026;">Plano</td>
            <td align="right" style="color:#f0fdf4;font-size:12px;padding:5px 0;border-bottom:1px solid #1a3026;">${opts.planLabel}</td>
          </tr>
          <tr>
            <td style="color:#8d9e97;font-size:12px;padding:5px 0;border-bottom:1px solid #1a3026;">Valor</td>
            <td align="right" style="color:#4ade80;font-size:15px;font-weight:700;padding:5px 0;border-bottom:1px solid #1a3026;">R$ ${opts.amount.toFixed(2)}</td>
          </tr>
          ${opts.paymentMethod ? `
          <tr>
            <td style="color:#8d9e97;font-size:12px;padding:5px 0;border-bottom:1px solid #1a3026;">Pagamento</td>
            <td align="right" style="color:#f0fdf4;font-size:12px;padding:5px 0;border-bottom:1px solid #1a3026;">${opts.paymentMethod}</td>
          </tr>` : ""}
          <tr>
            <td style="color:#8d9e97;font-size:12px;padding:5px 0;border-bottom:1px solid #1a3026;">Data</td>
            <td align="right" style="color:#f0fdf4;font-size:12px;padding:5px 0;border-bottom:1px solid #1a3026;">${opts.paidAt.toLocaleDateString("pt-BR", { day: "2-digit", month: "long", year: "numeric" })}</td>
          </tr>
          ${opts.premiumEndsAt ? `<tr>
            <td style="color:#8d9e97;font-size:12px;padding:5px 0;">Acesso Premium até</td>
            <td align="right" style="color:#f0fdf4;font-size:12px;font-weight:600;padding:5px 0;">${opts.premiumEndsAt.toLocaleDateString("pt-BR", { day: "2-digit", month: "long", year: "numeric" })}</td>
          </tr>` : ""}
        </table>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #1e3a2a;margin-top:16px;padding-top:14px;">
          <tr><td>
            <p style="color:#6b8f7a;font-size:11px;margin:0 0 2px;font-weight:600;">${RECEIPT_COMPANY}</p>
            <p style="color:#6b8f7a;font-size:11px;margin:0;">CNPJ: ${RECEIPT_CNPJ}</p>
          </td></tr>
        </table>
      </td></tr>
    </table>
    <p style="color:#d1fae5;font-size:14px;margin:0 0 4px;">Seus recursos premium estão disponíveis agora mesmo:</p>
    ${btn("Acessar VetRank Premium", `${SITE_URL}/dashboard`)}
    <p style="color:#4b7a5e;font-size:13px;margin:16px 0 0;">
      Dúvidas sobre sua assinatura? <a href="mailto:${SUPPORT}" style="color:#22c55e;">${SUPPORT}</a>
    </p>
  `, "Pagamento confirmado — VetRank Premium");

  return resend.emails.send({
    from: FROM,
    to: opts.to,
    subject: `✅ Recibo ${opts.receiptNumber} — VetRank Premium`,
    html,
  });
}

// ─── Suporte / Tickets ────────────────────────────────────────────────────────

interface TicketCreatedOpts {
  ticketId: number; subject: string; category: string;
  message: string; userName: string;
  userEmail?: string; adminEmail: string | string[];
}

export async function sendTicketCreated(rawOpts: TicketCreatedOpts) {
  const opts = { ...rawOpts, subject: esc(rawOpts.subject), message: esc(rawOpts.message), userName: esc(rawOpts.userName), category: esc(rawOpts.category) };
  const ticketUrl = `${SITE_URL}/profile?ticket=${opts.ticketId}`;
  const adminUrl  = `${SITE_URL}/admin?tab=suporte&ticket=${opts.ticketId}`;

  let userSend: Promise<unknown> = Promise.resolve();
  // Email de confirmação para o usuário
  if (opts.userEmail) {
    const userHtml = baseTemplate(`
      <h1 style="color:#f0fdf4;font-size:24px;font-weight:700;margin:0 0 8px;">Ticket #${opts.ticketId} recebido ✅</h1>
      <p style="color:#86efac;font-size:15px;margin:0 0 24px;">Recebemos sua solicitação e responderemos em breve.</p>
      <p style="color:#d1fae5;font-size:15px;line-height:1.7;margin:0 0 12px;">Olá, <strong style="color:#4ade80;">${opts.userName}</strong>!</p>
      <div style="background:#0f2318;border:1px solid #1e3a2a;border-radius:10px;padding:20px;margin:20px 0;">
        <p style="color:#4ade80;font-size:12px;margin:0 0 6px;font-weight:600;">ASSUNTO</p>
        <p style="color:#f0fdf4;font-size:15px;margin:0 0 16px;">${opts.subject}</p>
        <p style="color:#4ade80;font-size:12px;margin:0 0 6px;font-weight:600;">CATEGORIA</p>
        <p style="color:#f0fdf4;font-size:15px;margin:0 0 16px;">${opts.category}</p>
        <p style="color:#4ade80;font-size:12px;margin:0 0 6px;font-weight:600;">SUA MENSAGEM</p>
        <p style="color:#d1fae5;font-size:14px;margin:0;white-space:pre-wrap;">${opts.message}</p>
      </div>
      ${btn('Ver meu ticket', ticketUrl)}
      <p style="color:#6b7280;font-size:12px;margin:16px 0 0;">
        Nossa equipe responderá em até 24–48h úteis. Você receberá um e-mail quando houver atualização.
      </p>
    `, `Ticket #${opts.ticketId} recebido`);

    // Não usa await aqui: falha no e-mail do usuário não pode impedir o aviso ao admin.
    userSend = resend.emails.send({
      from: FROM,
      to: opts.userEmail,
      subject: `✅ Ticket #${opts.ticketId} recebido — ${rawOpts.subject}`,
      html: userHtml,
    });
  }

  // Notificação para o admin
  const adminHtml = baseTemplate(`
    <h1 style="color:#f0fdf4;font-size:22px;font-weight:700;margin:0 0 8px;">🎫 Novo Ticket #${opts.ticketId}</h1>
    <p style="color:#86efac;font-size:15px;margin:0 0 20px;">Um novo ticket de suporte foi aberto.</p>
    <div style="background:#0f2318;border:1px solid #1e3a2a;border-radius:10px;padding:20px;margin:20px 0;">
      <p style="color:#4ade80;font-size:12px;margin:0 0 4px;font-weight:600;">USUÁRIO</p>
      <p style="color:#f0fdf4;font-size:15px;margin:0 0 14px;">${opts.userName}${opts.userEmail ? ` &lt;${opts.userEmail}&gt;` : ""}</p>
      <p style="color:#4ade80;font-size:12px;margin:0 0 4px;font-weight:600;">ASSUNTO</p>
      <p style="color:#f0fdf4;font-size:15px;margin:0 0 14px;">${opts.subject}</p>
      <p style="color:#4ade80;font-size:12px;margin:0 0 4px;font-weight:600;">CATEGORIA</p>
      <p style="color:#f0fdf4;font-size:15px;margin:0 0 14px;">${opts.category}</p>
      <p style="color:#4ade80;font-size:12px;margin:0 0 4px;font-weight:600;">MENSAGEM</p>
      <p style="color:#d1fae5;font-size:14px;margin:0;white-space:pre-wrap;">${opts.message}</p>
    </div>
    ${btn('Abrir no Admin', adminUrl)}
  `, `Novo ticket: ${opts.subject}`);

  const [adminRes, userRes] = await Promise.allSettled([
    resend.emails.send({
      from: FROM,
      to: opts.adminEmail,
      subject: `🎫 Novo Ticket #${opts.ticketId} — ${rawOpts.subject}`,
      html: adminHtml,
    }),
    userSend,
  ]);
  if (userRes.status === "rejected") console.error(`[Ticket #${opts.ticketId}] user confirmation failed:`, (userRes.reason as any)?.message);
  if (adminRes.status === "rejected") throw adminRes.reason;
  return adminRes.value;
}

interface TicketUpdatedOpts {
  ticketId: number; subject: string; status: string;
  message?: string; userName: string; userEmail: string; adminEmail: string | string[];
}

export async function sendTicketUpdated(rawOpts: TicketUpdatedOpts) {
  const opts = { ...rawOpts, subject: esc(rawOpts.subject), message: rawOpts.message ? esc(rawOpts.message) : undefined, userName: esc(rawOpts.userName) };
  const ticketUrl = `${SITE_URL}/profile?ticket=${opts.ticketId}`;
  const html = baseTemplate(`
    <h1 style="color:#f0fdf4;font-size:24px;font-weight:700;margin:0 0 8px;">Atualização no Ticket #${opts.ticketId}</h1>
    <p style="color:#86efac;font-size:15px;margin:0 0 20px;">Seu ticket foi atualizado pela equipe VetRank.</p>
    <p style="color:#d1fae5;font-size:15px;margin:0 0 12px;">Olá, <strong style="color:#4ade80;">${opts.userName}</strong>!</p>
    <div style="background:#0f2318;border:1px solid #1e3a2a;border-radius:10px;padding:20px;margin:20px 0;">
      <p style="color:#4ade80;font-size:12px;margin:0 0 4px;font-weight:600;">TICKET</p>
      <p style="color:#f0fdf4;font-size:15px;margin:0 0 14px;">#${opts.ticketId} — ${opts.subject}</p>
      <p style="color:#4ade80;font-size:12px;margin:0 0 4px;font-weight:600;">STATUS</p>
      <p style="color:#f0fdf4;font-size:15px;margin:0 0 ${opts.message ? "14px" : "0"};">${opts.status}</p>
      ${opts.message ? `
        <p style="color:#4ade80;font-size:12px;margin:0 0 4px;font-weight:600;">MENSAGEM DA EQUIPE</p>
        <p style="color:#d1fae5;font-size:14px;margin:0;white-space:pre-wrap;">${opts.message}</p>
      ` : ""}
    </div>
    ${btn('Ver ticket completo', ticketUrl)}
    <p style="color:#6b7280;font-size:12px;margin:16px 0 0;">
      Se tiver dúvidas, responda diretamente no ticket ou entre em contato pelo e-mail <a href="mailto:${SUPPORT}" style="color:#22c55e;">${SUPPORT}</a>.
    </p>
  `, `Ticket #${opts.ticketId} atualizado`);

  // Usuário e cópia do admin são independentes: uma falha não cancela a outra.
  const [userRes, adminRes] = await Promise.allSettled([
    resend.emails.send({
      from: FROM,
      to: rawOpts.userEmail,
      subject: `📬 Atualização no Ticket #${opts.ticketId} — ${rawOpts.subject}`,
      html,
    }),
    resend.emails.send({
      from: FROM,
      to: opts.adminEmail,
      subject: `📬 [Cópia] Ticket #${opts.ticketId} atualizado — ${rawOpts.userName}`,
      html,
    }),
  ]);
  if (adminRes.status === "rejected") console.error(`[Ticket #${opts.ticketId}] admin copy failed:`, (adminRes.reason as any)?.message);
  if (userRes.status === "rejected") throw userRes.reason;
  return userRes.value;
}

interface TicketReplyOpts {
  ticketId: number; subject: string;
  message: string; userName: string; adminEmail: string | string[];
}

export async function sendTicketReply(rawOpts: TicketReplyOpts) {
  const opts = { ...rawOpts, subject: esc(rawOpts.subject), message: esc(rawOpts.message), userName: esc(rawOpts.userName) };
  const adminUrl = `${SITE_URL}/admin?tab=suporte&ticket=${opts.ticketId}`;
  const html = baseTemplate(`
    <h1 style="color:#f0fdf4;font-size:22px;font-weight:700;margin:0 0 8px;">💬 Resposta no Ticket #${opts.ticketId}</h1>
    <p style="color:#86efac;font-size:15px;margin:0 0 20px;">O usuário respondeu ao ticket.</p>
    <div style="background:#0f2318;border:1px solid #1e3a2a;border-radius:10px;padding:20px;margin:20px 0;">
      <p style="color:#4ade80;font-size:12px;margin:0 0 4px;font-weight:600;">USUÁRIO</p>
      <p style="color:#f0fdf4;font-size:15px;margin:0 0 14px;">${opts.userName}</p>
      <p style="color:#4ade80;font-size:12px;margin:0 0 4px;font-weight:600;">TICKET</p>
      <p style="color:#f0fdf4;font-size:15px;margin:0 0 14px;">#${opts.ticketId} — ${opts.subject}</p>
      <p style="color:#4ade80;font-size:12px;margin:0 0 4px;font-weight:600;">MENSAGEM</p>
      <p style="color:#d1fae5;font-size:14px;margin:0;white-space:pre-wrap;">${opts.message}</p>
    </div>
    ${btn('Responder no Admin', adminUrl)}
  `, `Resposta no ticket #${opts.ticketId}`);

  return resend.emails.send({
    from: FROM,
    to: opts.adminEmail,
    subject: `💬 Resposta no Ticket #${opts.ticketId} — ${rawOpts.userName}`,
    html,
  });
}

// ─── Email Verification ───────────────────────────────────────────────────────

// ─── Questão do Dia ──────────────────────────────────────────────────────────

interface DailyQuestionOpts {
  name: string;
  questionText: string;
  options: Array<{ id?: string; letter?: string; textPt?: string; text?: string }>;
  correctOption: string;
  subjectName?: string | null;
  explanationPt?: string | null;
}

export function dailyQuestionTemplate(opts: DailyQuestionOpts): string {
  const { name, questionText, options, correctOption, subjectName, explanationPt } = opts;
  const letters = ["A", "B", "C", "D", "E"];

  const optionsHtml = options.slice(0, 5).map((opt, i) => {
    const letter = opt.id ?? opt.letter ?? letters[i] ?? String(i + 1);
    const text = opt.textPt ?? opt.text ?? "";
    const isCorrect = letter.toUpperCase() === correctOption.toUpperCase();
    const bg = isCorrect ? "#0d3320" : "#0f1f17";
    const border = isCorrect ? "1px solid #16a34a" : "1px solid #1e3a2a";
    const letterColor = isCorrect ? "#4ade80" : "#4b7a5e";
    const textColor = isCorrect ? "#f0fdf4" : "#d1fae5";
    const mark = isCorrect ? " ✓" : "";
    return `
      <tr>
        <td style="background:${bg};padding:12px 16px;border-bottom:${border};">
          <span style="color:${letterColor};font-weight:700;font-size:14px;margin-right:10px;">${letter})${mark}</span>
          <span style="color:${textColor};font-size:14px;line-height:1.5;">${text}</span>
        </td>
      </tr>`;
  }).join("");

  const correctOpt = options.find(o => (o.id ?? o.letter ?? "").toUpperCase() === correctOption.toUpperCase());
  const correctText = correctOpt?.textPt ?? correctOpt?.text ?? "";

  const content = `
    <!-- Hero -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#0a2e1a 0%,#0f3d24 100%);border-radius:12px;margin:0 0 28px;padding:0;">
      <tr>
        <td style="padding:28px 32px;">
          <p style="color:#4ade80;font-size:11px;font-weight:700;letter-spacing:2px;margin:0 0 10px;text-transform:uppercase;">📚 Questão do Dia</p>
          <h1 style="color:#f0fdf4;font-size:22px;font-weight:800;margin:0 0 8px;line-height:1.3;">Teste seus conhecimentos hoje</h1>
          <p style="color:#86efac;font-size:14px;margin:0;">Olá, <strong>${name}</strong>! Mais de 50.000 questões de veterinária te aguardam.</p>
        </td>
      </tr>
    </table>

    ${subjectName ? `<p style="color:#4b7a5e;font-size:11px;font-weight:700;letter-spacing:1.5px;margin:0 0 14px;text-transform:uppercase;">📌 ${subjectName}</p>` : ""}

    <!-- Question box -->
    <div style="background:#0f2318;border:1px solid #1e3a2a;border-radius:12px;padding:24px;margin:0 0 20px;">
      <p style="color:#f0fdf4;font-size:16px;line-height:1.75;margin:0 0 20px;font-weight:500;">${questionText}</p>
      <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #1e3a2a;border-radius:8px;overflow:hidden;border-collapse:collapse;">
        ${optionsHtml}
      </table>
    </div>

    <!-- Answer reveal -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#052e16;border:1px solid #16a34a;border-radius:12px;margin:0 0 24px;">
      <tr>
        <td style="padding:20px 24px;">
          <p style="color:#4ade80;font-size:11px;font-weight:700;letter-spacing:2px;margin:0 0 8px;text-transform:uppercase;">✅ Resposta Correta</p>
          <p style="color:#f0fdf4;font-size:16px;font-weight:700;margin:0 0 ${explanationPt ? "12px" : "0"};">
            Alternativa <span style="background:#16a34a;color:#fff;padding:2px 10px;border-radius:6px;margin:0 4px;">${correctOption}</span>
            ${correctText ? `— <span style="font-weight:400;color:#d1fae5;">${correctText}</span>` : ""}
          </p>
          ${explanationPt ? `
          <p style="color:#86efac;font-size:13px;line-height:1.7;margin:0;border-top:1px solid #166534;padding-top:12px;">${explanationPt}</p>` : ""}
        </td>
      </tr>
    </table>

    <p style="color:#d1fae5;font-size:14px;margin:0 0 6px;">Quer praticar mais questões como esta?</p>
    ${btn("Praticar agora no VetRank →", `${SITE_URL}/practice`)}

    <p style="color:#4b7a5e;font-size:12px;margin:20px 0 0;line-height:1.6;">
      Banco com +50.000 questões • Simulados cronometrados • Ranking semanal 🏆<br/>
      <a href="${SITE_URL}/bank" style="color:#22c55e;">Explorar banco de questões</a>
    </p>
  `;
  return baseTemplate(content, "Questão do dia — veterinária • VetRank");
}

export async function sendDailyQuestionEmail(
  to: string,
  name: string,
  question: {
    textPt: string;
    options: Array<{ id?: string; letter?: string; textPt?: string; text?: string }>;
    correctOption: string;
    subjectName?: string | null;
    explanationPt?: string | null;
  }
) {
  const html = dailyQuestionTemplate({
    name: name?.split(" ")[0] || "Veterinário(a)",
    questionText: question.textPt,
    options: question.options,
    correctOption: question.correctOption,
    subjectName: question.subjectName,
    explanationPt: question.explanationPt,
  });
  return resend.emails.send({
    from: FROM,
    to,
    subject: "📚 Questão do Dia — VetRank",
    html,
    bulk: true,
  });
}

// ─── Novas Questões (digest semanal) ────────────────────────────────────────

interface NewQuestionsDigestOpts {
  name: string;
  disciplines: Array<{ name: string; count: number }>;
  total: number;
}

export type DigestConcurso = { orgao: string; uf?: string | null; vagas?: string | null; salario?: string | null; fim?: string | null; url: string };

export function newQuestionsDigestTemplate(opts: NewQuestionsDigestOpts & { concursos?: DigestConcurso[] }): string {
  const { name, disciplines, total } = opts;
  const concursos = opts.concursos ?? [];
  const concursosHtml = concursos.length ? `
    <p style="color:#4ade80;font-size:11px;font-weight:700;letter-spacing:2px;margin:8px 0 10px;text-transform:uppercase;">📢 Concursos abertos para veterinário</p>
    <div style="border:1px solid #1e3a2a;border-radius:12px;margin:0 0 16px;overflow:hidden;">
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        ${concursos.slice(0, 8).map(c => `
        <tr><td style="background:#0f2318;padding:12px 16px;border-bottom:1px solid #1e3a2a;">
          <a href="${c.url}" style="color:#f0fdf4;font-size:14px;font-weight:700;text-decoration:none;">${esc(c.orgao)}${c.uf ? ` (${esc(c.uf)})` : ""}</a>
          <div style="color:#86efac;font-size:12px;margin-top:3px;">${[c.vagas, c.salario ? `até ${c.salario}` : null, c.fim ? `inscrições até ${c.fim}` : null].filter(Boolean).map(x => esc(String(x))).join(" · ")}</div>
        </td></tr>`).join("")}
      </table>
    </div>
    ${btn("Ver todos os concursos →", `${SITE_URL}/concursos`)}
    <div style="height:20px"></div>` : "";

  const itemsHtml = disciplines.map((d) => `
    <tr>
      <td style="background:#0f2318;padding:12px 16px;border-bottom:1px solid #1e3a2a;">
        <span style="color:#d1fae5;font-size:14px;">${d.name}</span>
      </td>
      <td style="background:#0f2318;padding:12px 16px;border-bottom:1px solid #1e3a2a;text-align:right;">
        <span style="color:#4ade80;font-weight:700;font-size:14px;">+${d.count}</span>
      </td>
    </tr>`).join("");

  const content = `
    <!-- Hero -->
    <table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#0a2e1a 0%,#0f3d24 100%);border-radius:12px;margin:0 0 28px;padding:0;">
      <tr>
        <td style="padding:28px 32px;">
          <p style="color:#4ade80;font-size:11px;font-weight:700;letter-spacing:2px;margin:0 0 10px;text-transform:uppercase;">🆕 Novidades da Semana</p>
          <h1 style="color:#f0fdf4;font-size:22px;font-weight:800;margin:0 0 8px;line-height:1.3;">${total > 0 ? `${total} ${total === 1 ? "nova questão" : "novas questões"} no banco!` : `${concursos.length} concurso${concursos.length !== 1 ? "s" : ""} aberto${concursos.length !== 1 ? "s" : ""} para veterinário`}</h1>
          <p style="color:#86efac;font-size:14px;margin:0;">Olá, <strong>${name}</strong>! O VetRank está sempre crescendo para te ajudar a passar.</p>
        </td>
      </tr>
    </table>

    ${concursosHtml}

    ${total > 0 ? `<!-- Breakdown by discipline -->
    <div style="border:1px solid #1e3a2a;border-radius:12px;margin:0 0 24px;overflow:hidden;">
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        ${itemsHtml}
      </table>
    </div>

    <p style="color:#d1fae5;font-size:14px;margin:0 0 6px;">Aproveite e teste seus conhecimentos nas novas questões.</p>
    ${btn("Explorar banco de questões →", `${SITE_URL}/bank`)}` : `${btn("Treinar com simulados →", `${SITE_URL}/exam`)}`}

    <p style="color:#4b7a5e;font-size:12px;margin:20px 0 0;line-height:1.6;">
      Banco com +50.000 questões • Simulados cronometrados • Ranking semanal 🏆
    </p>
  `;
  return baseTemplate(content, total > 0 ? `${total} ${total === 1 ? "nova questão" : "novas questões"} essa semana — VetRank` : "Concursos abertos para veterinário — VetRank");
}

export async function sendNewQuestionsDigestEmail(
  to: string,
  name: string,
  data: { disciplines: Array<{ name: string; count: number }>; total: number; concursos?: DigestConcurso[] }
) {
  const html = newQuestionsDigestTemplate({
    name: name?.split(" ")[0] || "Veterinário(a)",
    disciplines: data.disciplines,
    total: data.total,
    concursos: data.concursos,
  });
  const nc = data.concursos?.length ?? 0;
  const subject = data.total > 0
    ? `🆕 ${data.total} ${data.total === 1 ? "nova questão" : "novas questões"}${nc ? ` + ${nc} concurso${nc !== 1 ? "s" : ""} aberto${nc !== 1 ? "s" : ""}` : ""} — VetRank`
    : `📢 ${nc} concurso${nc !== 1 ? "s" : ""} aberto${nc !== 1 ? "s" : ""} para veterinário esta semana — VetRank`;
  return resend.emails.send({ from: FROM, to, subject, html, bulk: true });
}

/** Alerta interno para a equipe (pagamentos, auditoria, etc). `bodyHtml` já vem montado/escapado. */
export async function sendAdminAlertEmail(to: string | string[], subject: string, bodyHtml: string) {
  const html = baseTemplate(`
    <h1 style="color:#f0fdf4;font-size:20px;font-weight:700;margin:0 0 16px;">${subject}</h1>
    <div style="color:#d1fae5;font-size:14px;line-height:1.6;">${bodyHtml}</div>
  `, subject);
  return resend.emails.send({ from: FROM, to, subject: `[VetRank Admin] ${subject}`, html });
}

export async function sendEmailVerificationEmail(to: string, name: string, token: string) {
  const verifyUrl = `${SITE_URL}/api/auth/email/verify?token=${token}`;
  const html = baseTemplate(`
    <h2 style="color:#f0fdf4;font-size:22px;font-weight:700;margin:0 0 12px;">Confirme seu e-mail</h2>
    <p style="color:#86efac;font-size:15px;margin:0 0 8px;">Olá, ${name}!</p>
    <p style="color:#a3a3a3;font-size:14px;margin:0 0 24px;line-height:1.6;">
      Obrigado por criar sua conta no VetRank!<br/>
      Clique no botão abaixo para confirmar seu e-mail e ativar sua conta.<br/>
      O link expira em <strong style="color:#f0fdf4;">48 horas</strong>.
    </p>
    ${btn('Confirmar meu e-mail', verifyUrl)}
    <p style="color:#6b7280;font-size:12px;margin:24px 0 0;">
      Se você não criou uma conta no VetRank, pode ignorar este e-mail com segurança.
    </p>
  `, 'Confirme seu e-mail — VetRank');
  return resend.emails.send({
    from: FROM,
    to,
    subject: '✅ Confirme seu e-mail — VetRank',
    html,
  });
}
