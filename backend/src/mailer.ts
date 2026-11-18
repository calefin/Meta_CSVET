/**
 * SMTP sender (D-65). Settings come only from the server environment (.env of the VPS, never the repository):
 *   SMTP_HOST, SMTP_PORT (465 = TLS from the start), SMTP_USER, SMTP_PASS,
 *   MAIL_FROM   (default "BagParser <SMTP_USER>": the From must be the account that sends, so SPF/DKIM align),
 *   SUPPORT_EMAIL (Reply-To and ticket notices).
 * MAIL_LOG=1 without SMTP_HOST prints the messages to the log instead (local development and tests only).
 */
import nodemailer, { type Transporter } from 'nodemailer';
import { lookup } from 'node:dns/promises';
import net from 'node:net';

export interface MailCheckStep {
  step: string;
  ok: boolean;
  detail: string;
}
import type { Mail } from './emails.js';

export interface Mailer {
  enabled: boolean;
  /** what is configured, for the admin panel (never the password) */
  info: { host: string | null; from: string | null; support: string | null; mode: 'smtp' | 'log' | 'off' };
  support: string;
  send: (to: string, mail: Mail, opts?: { replyTo?: string }) => Promise<void>;
  /** D-78: what works and what does not, step by step (config, DNS, port, SMTP login) */
  check: () => Promise<MailCheckStep[]>;
}

export function createMailer(env: NodeJS.ProcessEnv, log: { info: (o: object, m: string) => void; warn: (o: object, m: string) => void }): Mailer {
  const host = env.SMTP_HOST?.trim() || null;
  const user = env.SMTP_USER?.trim() || null;
  const support = env.SUPPORT_EMAIL?.trim() || user || 'support@csvet.app';
  const from = env.MAIL_FROM?.trim() || (user ? `"BagParser" <${user}>` : null);
  let transport: Transporter | null = null;
  let mode: Mailer['info']['mode'] = 'off';

  if (host && user && env.SMTP_PASS) {
    const port = Number(env.SMTP_PORT ?? 465);
    transport = nodemailer.createTransport({
      // D-79: EHLO name. Inside a container nodemailer falls back to "[127.0.0.1]" and the HostGator accepted (250) but never delivered
      name: 'bagparser.com',
      host,
      port,
      secure: port === 465,
      requireTLS: port !== 465,
      auth: { user, pass: env.SMTP_PASS },
      pool: true,
      maxConnections: 2,
      connectionTimeout: 15_000,
      greetingTimeout: 15_000,
      socketTimeout: 30_000,
      tls: { minVersion: 'TLSv1.2', servername: host },
    });
    mode = 'smtp';
  } else if (env.MAIL_LOG === '1') {
    transport = nodemailer.createTransport({ jsonTransport: true });
    mode = 'log';
  }

  async function check(): Promise<MailCheckStep[]> {
    const port = Number(env.SMTP_PORT ?? 465);
    const steps: MailCheckStep[] = [];
    const pass = env.SMTP_PASS ?? '';
    steps.push({
      step: 'Configuração',
      ok: !!(host && user && pass),
      detail: `SMTP_HOST=${host ?? '(vazio)'} · SMTP_PORT=${port} · SMTP_USER=${user ?? '(vazio)'} · SMTP_PASS com ${pass.length} caracteres${/^\s|\s$/.test(pass) ? ' (tem espaço no começo/fim!)' : ''} · From=${from ?? '(vazio)'}`,
    });
    if (!host) return steps;
    try {
      const a = await lookup(host);
      steps.push({ step: 'DNS', ok: true, detail: `${host} → ${a.address}` });
    } catch (err) {
      steps.push({ step: 'DNS', ok: false, detail: `${host} não resolve: ${(err as NodeJS.ErrnoException).code ?? (err as Error).message}` });
      return steps;
    }
    const tcp = await new Promise<MailCheckStep>((resolve) => {
      const sock = net.connect({ host, port, timeout: 8000 });
      sock.once('connect', () => {
        sock.destroy();
        resolve({ step: 'Porta', ok: true, detail: `conexão com ${host}:${port} aberta` });
      });
      sock.once('timeout', () => {
        sock.destroy();
        resolve({ step: 'Porta', ok: false, detail: `sem resposta em ${host}:${port} (8 s): a VPS pode estar bloqueando SMTP de saída, ou a porta está errada (465 = SSL, 587 = STARTTLS)` });
      });
      sock.once('error', (err: NodeJS.ErrnoException) => resolve({ step: 'Porta', ok: false, detail: `${host}:${port}: ${err.code ?? err.message}` }));
    });
    steps.push(tcp);
    if (!tcp.ok || !transport || mode !== 'smtp') return steps;
    try {
      await transport.verify();
      steps.push({ step: 'Login SMTP', ok: true, detail: `servidor aceitou o usuário ${user}` });
    } catch (err) {
      const e = err as { code?: string; responseCode?: number; response?: string; message: string };
      const hint = e.code === 'EAUTH' ? ' → usuário ou senha recusados (confira SMTP_USER e SMTP_PASS)' : e.code === 'ESOCKET' || e.code === 'ETLS' ? ' → problema de TLS: com a porta 587 use STARTTLS; com 465, SSL' : '';
      steps.push({ step: 'Login SMTP', ok: false, detail: `${e.code ?? ''} ${e.responseCode ?? ''} ${String(e.response ?? e.message).slice(0, 200)}${hint}`.trim() });
    }
    return steps;
  }

  return {
    check,
    enabled: !!transport,
    info: { host, from, support, mode },
    support,
    async send(to, mail, opts = {}) {
      if (!transport) throw new Error('mail_disabled');
      const res = await transport
        .sendMail({
        from: from ?? `"BagParser" <noreply@localhost>`,
        to,
        replyTo: opts.replyTo ?? support,
        subject: mail.subject,
        text: mail.text,
        html: mail.html,
        // Transactional message: no auto-replies (vacation etc.) back to the robot
        headers: { 'Auto-Submitted': 'auto-generated', 'X-Auto-Response-Suppress': 'All' },
      })
        .catch((err: { code?: string; response?: string; message: string }) => {
          log.warn({ to: to.replace(/^(.).*(@.*)$/, '$1***$2'), subject: mail.subject, code: err.code, response: String(err.response ?? err.message).slice(0, 200) }, 'mail failed');
          throw err;
        });
      if (mode === 'log') log.info({ to, subject: mail.subject, message: JSON.parse(String((res as { message?: string }).message ?? '{}')).text }, 'mail (MAIL_LOG)');
      else log.info({ to: to.replace(/^(.).*(@.*)$/, '$1***$2'), subject: mail.subject }, 'mail sent');
    },
  };
}
