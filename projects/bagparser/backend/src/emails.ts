/**
 * Transactional e-mails (D-65): password reset, password changed, test message and the support-ticket notice.
 * Built for the inbox, not the spam folder: HTML + plain-text alternative, table layout with inline styles (Gmail,
 * Outlook and phones render it the same), no remote images or tracking, one clear link to our own domain, short
 * neutral wording, the reason for the message and a contact in the footer. Pure functions (tested).
 */

export type MailLang = 'pt' | 'en' | 'es' | 'pl';
export interface Mail {
  subject: string;
  html: string;
  text: string;
}

export function mailLang(v: unknown): MailLang {
  const s = String(v ?? '').toLowerCase();
  if (s.startsWith('pt')) return 'pt';
  if (s.startsWith('es')) return 'es';
  if (s.startsWith('pl')) return 'pl';
  return 'en';
}

const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

const BRAND = '#14634a';
const GOLD = '#e8a317';
const INK = '#1f2430';
const MUTED = '#5b6270';
const BG = '#f4f2ec';

interface LayoutParts {
  lang: MailLang;
  preheader: string;
  title: string;
  paragraphs: string[];
  button?: { label: string; url: string };
  note?: string;
  footer: string;
}

/** Shared layout (HTML) */
function layout(p: LayoutParts): string {
  const btn = p.button
    ? `<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:28px 0 8px">
        <tr><td align="center" bgcolor="${BRAND}" style="border-radius:10px">
          <a href="${esc(p.button.url)}" target="_blank" style="display:inline-block;padding:14px 28px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:10px">${esc(p.button.label)}</a>
        </td></tr>
      </table>
      <p style="margin:16px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;color:${MUTED}">${esc(LINK_HINT[p.lang])}<br>
        <a href="${esc(p.button.url)}" target="_blank" style="color:${BRAND};word-break:break-all">${esc(p.button.url)}</a></p>`
    : '';
  return `<!doctype html>
<html lang="${p.lang === 'pt' ? 'pt-BR' : p.lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>${esc(p.title)}</title>
</head>
<body style="margin:0;padding:0;background:${BG}">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:${BG}">${esc(p.preheader)}</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:${BG}">
  <tr><td align="center" style="padding:32px 12px">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px">
      <tr><td style="padding:0 8px 16px;font-family:Arial,Helvetica,sans-serif;font-size:24px;font-weight:bold;color:${INK}">
        <span style="color:${BRAND}">Bag</span>Parser
      </td></tr>
      <tr><td style="background:#ffffff;border-radius:14px;border:1px solid #e2ddd0;border-top:5px solid ${BRAND};padding:32px 28px">
        <h1 style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:22px;line-height:30px;color:${INK}">${esc(p.title)}</h1>
        ${p.paragraphs.map((x) => `<p style="margin:0 0 14px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:24px;color:${INK}">${x}</p>`).join('\n        ')}
        ${btn}
        ${p.note ? `<p style="margin:24px 0 0;padding:12px 14px;background:#fbf6ea;border-left:4px solid ${GOLD};border-radius:6px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;color:${MUTED}">${p.note}</p>` : ''}
      </td></tr>
      <tr><td style="padding:18px 8px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:${MUTED}">
        ${p.footer}
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>`;
}

const LINK_HINT: Record<MailLang, string> = {
  pt: 'Se o botão não funcionar, copie e cole este endereço no navegador:',
  en: 'If the button does not work, copy and paste this address into your browser:',
  es: 'Si el botón no funciona, copia y pega esta dirección en el navegador:',
  pl: 'Jeśli przycisk nie działa, skopiuj i wklej ten adres do przeglądarki:',
};

function footer(lang: MailLang, support: string, reason: string): string {
  const site = '<a href="https://bagparser.com" style="color:#5b6270">bagparser.com</a>';
  const help = {
    pt: `Dúvidas? Responda a este e-mail ou escreva para ${esc(support)}.`,
    en: `Questions? Reply to this e-mail or write to ${esc(support)}.`,
    es: `¿Dudas? Responde a este correo o escribe a ${esc(support)}.`,
    pl: `Pytania? Odpowiedz na tę wiadomość lub napisz na ${esc(support)}.`,
  }[lang];
  const fan = {
    pt: 'BagParser é um fansite de Tibia. Tibia é marca da CipSoft GmbH.',
    en: 'BagParser is a Tibia fansite. Tibia is a trademark of CipSoft GmbH.',
    es: 'BagParser es un fansite de Tibia. Tibia es una marca de CipSoft GmbH.',
    pl: 'BagParser to fansite Tibii. Tibia jest znakiem towarowym CipSoft GmbH.',
  }[lang];
  return `${esc(reason)}<br>${help}<br>${fan} · ${site}`;
}

/** Plain-text alternative (spam filters and some readers prefer it) */
function plain(parts: { title: string; lines: string[]; url?: string; note?: string; reason: string; support: string; lang: MailLang }): string {
  const help = { pt: 'Dúvidas', en: 'Questions', es: 'Dudas', pl: 'Pytania' }[parts.lang];
  return [
    'BagParser',
    '',
    parts.title,
    '',
    ...parts.lines,
    ...(parts.url ? ['', parts.url] : []),
    ...(parts.note ? ['', parts.note] : []),
    '',
    '--',
    parts.reason,
    `${help}: ${parts.support}`,
    'https://bagparser.com',
  ].join('\n');
}

const strip = (s: string) => s.replace(/<[^>]+>/g, '');

// ---------------------------------------------------------------------------
// Password reset
// ---------------------------------------------------------------------------

export function resetEmail(o: { lang: MailLang; name: string; url: string; minutes: number; support: string }): Mail {
  const T = {
    pt: {
      subject: 'Redefinir sua senha do BagParser',
      title: 'Redefinir sua senha',
      hi: `Olá, ${esc(o.name)}!`,
      body: 'Recebemos um pedido para criar uma nova senha para a sua conta. Clique no botão abaixo para escolher a nova senha.',
      button: 'Criar nova senha',
      note: `O link vale por ${o.minutes} minutos e só pode ser usado uma vez. Se você não pediu isso, ignore este e-mail: sua senha continua a mesma.`,
      reason: 'Você recebeu este e-mail porque alguém pediu para redefinir a senha desta conta no BagParser.',
      pre: 'Use o link para escolher uma nova senha.',
    },
    en: {
      subject: 'Reset your BagParser password',
      title: 'Reset your password',
      hi: `Hi, ${esc(o.name)}!`,
      body: 'We received a request to set a new password for your account. Click the button below to choose your new password.',
      button: 'Choose a new password',
      note: `The link is valid for ${o.minutes} minutes and works only once. If you did not ask for this, ignore this e-mail: your password stays the same.`,
      reason: 'You received this e-mail because someone asked to reset the password of this BagParser account.',
      pre: 'Use the link to choose a new password.',
    },
    es: {
      subject: 'Restablece tu contraseña de BagParser',
      title: 'Restablecer tu contraseña',
      hi: `¡Hola, ${esc(o.name)}!`,
      body: 'Recibimos una solicitud para crear una nueva contraseña para tu cuenta. Haz clic en el botón para elegir la nueva contraseña.',
      button: 'Crear nueva contraseña',
      note: `El enlace es válido por ${o.minutes} minutos y solo funciona una vez. Si no lo pediste, ignora este correo: tu contraseña sigue igual.`,
      reason: 'Recibiste este correo porque alguien pidió restablecer la contraseña de esta cuenta de BagParser.',
      pre: 'Usa el enlace para elegir una nueva contraseña.',
    },
    pl: {
      subject: 'Zresetuj hasło do BagParser',
      title: 'Zresetuj hasło',
      hi: `Cześć, ${esc(o.name)}!`,
      body: 'Otrzymaliśmy prośbę o ustawienie nowego hasła do Twojego konta. Kliknij przycisk poniżej, aby wybrać nowe hasło.',
      button: 'Ustaw nowe hasło',
      note: `Link jest ważny przez ${o.minutes} minut i działa tylko raz. Jeśli to nie Ty, zignoruj tę wiadomość: hasło się nie zmieni.`,
      reason: 'Otrzymujesz tę wiadomość, ponieważ ktoś poprosił o reset hasła do tego konta BagParser.',
      pre: 'Użyj linku, aby wybrać nowe hasło.',
    },
  }[o.lang];
  return {
    subject: T.subject,
    html: layout({ lang: o.lang, preheader: T.pre, title: T.title, paragraphs: [T.hi, T.body], button: { label: T.button, url: o.url }, note: T.note, footer: footer(o.lang, o.support, T.reason) }),
    text: plain({ title: T.title, lines: [strip(T.hi), T.body], url: o.url, note: T.note, reason: T.reason, support: o.support, lang: o.lang }),
  };
}

// ---------------------------------------------------------------------------
// Password changed (security notice)
// ---------------------------------------------------------------------------

export function passwordChangedEmail(o: { lang: MailLang; name: string; when: Date; support: string; loginUrl: string }): Mail {
  const when = o.when.toLocaleString({ pt: 'pt-BR', en: 'en-GB', es: 'es-ES', pl: 'pl-PL' }[o.lang], { timeZone: 'America/Sao_Paulo', dateStyle: 'short', timeStyle: 'short' });
  const T = {
    pt: {
      subject: 'Sua senha do BagParser foi alterada',
      title: 'Sua senha foi alterada',
      hi: `Olá, ${esc(o.name)}!`,
      body: `A senha da sua conta foi alterada em ${esc(when)} (horário de Brasília). Por segurança, saímos da conta em todos os aparelhos.`,
      button: 'Entrar no BagParser',
      note: `Não foi você? Use “Esqueci minha senha” na tela de entrada para criar outra senha agora e escreva para ${esc(o.support)}.`,
      reason: 'Você recebeu este aviso porque a senha desta conta do BagParser foi alterada.',
      pre: 'Aviso de segurança da sua conta.',
    },
    en: {
      subject: 'Your BagParser password was changed',
      title: 'Your password was changed',
      hi: `Hi, ${esc(o.name)}!`,
      body: `The password of your account was changed on ${esc(when)} (Brasília time). For your safety, we signed you out on every device.`,
      button: 'Sign in to BagParser',
      note: `Was it not you? Use “Forgot password” on the sign-in page to set a new one now and write to ${esc(o.support)}.`,
      reason: 'You received this notice because the password of this BagParser account was changed.',
      pre: 'Security notice for your account.',
    },
    es: {
      subject: 'Se cambió tu contraseña de BagParser',
      title: 'Se cambió tu contraseña',
      hi: `¡Hola, ${esc(o.name)}!`,
      body: `La contraseña de tu cuenta se cambió el ${esc(when)} (hora de Brasilia). Por seguridad, cerramos la sesión en todos los dispositivos.`,
      button: 'Entrar en BagParser',
      note: `¿No fuiste tú? Usa “Olvidé mi contraseña” en la pantalla de inicio para crear otra ahora y escribe a ${esc(o.support)}.`,
      reason: 'Recibiste este aviso porque se cambió la contraseña de esta cuenta de BagParser.',
      pre: 'Aviso de seguridad de tu cuenta.',
    },
    pl: {
      subject: 'Hasło do BagParser zostało zmienione',
      title: 'Hasło zostało zmienione',
      hi: `Cześć, ${esc(o.name)}!`,
      body: `Hasło do Twojego konta zostało zmienione ${esc(when)} (czas Brasília). Dla bezpieczeństwa wylogowaliśmy Cię na wszystkich urządzeniach.`,
      button: 'Zaloguj się do BagParser',
      note: `To nie Ty? Użyj „Nie pamiętam hasła” na stronie logowania, aby od razu ustawić nowe, i napisz na ${esc(o.support)}.`,
      reason: 'Otrzymujesz to powiadomienie, ponieważ hasło do tego konta BagParser zostało zmienione.',
      pre: 'Powiadomienie o bezpieczeństwie konta.',
    },
  }[o.lang];
  return {
    subject: T.subject,
    html: layout({ lang: o.lang, preheader: T.pre, title: T.title, paragraphs: [T.hi, T.body], button: { label: T.button, url: o.loginUrl }, note: T.note, footer: footer(o.lang, o.support, T.reason) }),
    text: plain({ title: T.title, lines: [strip(T.hi), strip(T.body)], url: o.loginUrl, note: strip(T.note), reason: T.reason, support: o.support, lang: o.lang }),
  };
}

// ---------------------------------------------------------------------------
// Admin: test message and new support ticket (internal, pt-BR)
// ---------------------------------------------------------------------------

export function testEmail(o: { support: string; sentAt: Date }): Mail {
  const title = 'E-mail de teste do BagParser';
  const body = `Se você está lendo isto na caixa de entrada (e não no spam), o envio está funcionando. Enviado em ${o.sentAt.toISOString().replace('T', ' ').slice(0, 16)} UTC.`;
  return {
    subject: title,
    html: layout({ lang: 'pt', preheader: 'Teste de envio do painel do admin.', title, paragraphs: [esc(body)], footer: footer('pt', o.support, 'Enviado pelo botão “Enviar e-mail de teste” do painel do admin.') }),
    text: plain({ title, lines: [body], reason: 'Enviado pelo botão “Enviar e-mail de teste” do painel do admin.', support: o.support, lang: 'pt' }),
  };
}

export function ticketNoticeEmail(o: { id: number; name: string; email: string; category: string; subject: string; message: string; adminUrl: string; support: string }): Mail {
  const title = `Novo ticket #${o.id}: ${o.subject}`;
  const msg = esc(o.message).replace(/\n/g, '<br>');
  return {
    subject: `[BagParser #${o.id}] ${o.subject}`.slice(0, 200),
    html: layout({
      lang: 'pt',
      preheader: `${o.name}: ${o.message.slice(0, 80)}`,
      title,
      paragraphs: [`<strong>De:</strong> ${esc(o.name)} &lt;${esc(o.email)}&gt;<br><strong>Categoria:</strong> ${esc(o.category)}`, `<span style="display:block;background:#f6f4ee;border-radius:8px;padding:12px 14px">${msg}</span>`],
      button: { label: 'Abrir no painel', url: o.adminUrl },
      note: 'Responda este e-mail para falar direto com a pessoa (a resposta vai para o endereço dela). Depois marque o ticket como respondido no painel.',
      footer: footer('pt', o.support, 'Aviso interno: novo ticket enviado em /support.'),
    }),
    text: plain({ title, lines: [`De: ${o.name} <${o.email}>`, `Categoria: ${o.category}`, '', o.message], url: o.adminUrl, reason: 'Aviso interno: novo ticket enviado em /support.', support: o.support, lang: 'pt' }),
  };
}

// ---------------------------------------------------------------------------
// E-mail confirmation (D-65)
// ---------------------------------------------------------------------------

export function verifyEmail(o: { lang: MailLang; name: string; url: string; hours: number; support: string }): Mail {
  const T = {
    pt: {
      subject: 'Confirme seu e-mail no BagParser',
      title: 'Confirme seu e-mail',
      hi: `Olá, ${esc(o.name)}! Que bom ter você no BagParser.`,
      body: 'Para proteger sua conta e permitir recuperar a senha, confirme que este e-mail é seu clicando no botão abaixo.',
      button: 'Confirmar e-mail',
      note: `O link vale por ${o.hours} horas. Se você não criou uma conta no BagParser, ignore este e-mail.`,
      reason: 'Você recebeu este e-mail porque ele foi usado para criar uma conta no BagParser.',
      pre: 'Falta só um clique para confirmar sua conta.',
    },
    en: {
      subject: 'Confirm your e-mail on BagParser',
      title: 'Confirm your e-mail',
      hi: `Hi, ${esc(o.name)}! Great to have you on BagParser.`,
      body: 'To protect your account and let you recover your password, confirm that this e-mail is yours with the button below.',
      button: 'Confirm e-mail',
      note: `The link is valid for ${o.hours} hours. If you did not create a BagParser account, ignore this e-mail.`,
      reason: 'You received this e-mail because it was used to create a BagParser account.',
      pre: 'One click left to confirm your account.',
    },
    es: {
      subject: 'Confirma tu correo en BagParser',
      title: 'Confirma tu correo',
      hi: `¡Hola, ${esc(o.name)}! Qué bueno tenerte en BagParser.`,
      body: 'Para proteger tu cuenta y poder recuperar la contraseña, confirma que este correo es tuyo con el botón de abajo.',
      button: 'Confirmar correo',
      note: `El enlace es válido por ${o.hours} horas. Si no creaste una cuenta en BagParser, ignora este correo.`,
      reason: 'Recibiste este correo porque se usó para crear una cuenta en BagParser.',
      pre: 'Solo falta un clic para confirmar tu cuenta.',
    },
    pl: {
      subject: 'Potwierdź e-mail w BagParser',
      title: 'Potwierdź swój e-mail',
      hi: `Cześć, ${esc(o.name)}! Miło Cię widzieć w BagParser.`,
      body: 'Aby chronić konto i umożliwić odzyskanie hasła, potwierdź, że ten e-mail należy do Ciebie, klikając przycisk poniżej.',
      button: 'Potwierdź e-mail',
      note: `Link jest ważny przez ${o.hours} godzin. Jeśli nie zakładałeś konta w BagParser, zignoruj tę wiadomość.`,
      reason: 'Otrzymujesz tę wiadomość, ponieważ ten adres został użyty do założenia konta w BagParser.',
      pre: 'Został jeden klik, aby potwierdzić konto.',
    },
  }[o.lang];
  return {
    subject: T.subject,
    html: layout({ lang: o.lang, preheader: T.pre, title: T.title, paragraphs: [T.hi, T.body], button: { label: T.button, url: o.url }, note: T.note, footer: footer(o.lang, o.support, T.reason) }),
    text: plain({ title: T.title, lines: [strip(T.hi), T.body], url: o.url, note: T.note, reason: T.reason, support: o.support, lang: o.lang }),
  };
}

// ---------------------------------------------------------------------------
// Admin alerts (D-70, internal, pt-BR)
// ---------------------------------------------------------------------------

export function adminAlertEmail(o: { kind: 'error' | 'billing' | 'good'; title: string; lines: string[]; url: string; support: string }): Mail {
  const label = o.kind === 'good' ? 'Boa notícia' : o.kind === 'billing' ? 'Pagamentos' : 'Erro no site';
  return {
    subject: `[BagParser] ${o.title}`.slice(0, 200),
    html: layout({
      lang: 'pt',
      preheader: o.lines[0] ?? o.title,
      title: o.title,
      paragraphs: o.lines.map((l) => esc(l)),
      button: { label: 'Abrir o painel', url: o.url },
      note: 'Alertas iguais são agrupados: no máximo um a cada 15 minutos e 6 por hora de cada tipo.',
      footer: footer('pt', o.support, `Aviso automático do BagParser (${label}) para os admins.`),
    }),
    text: plain({ title: o.title, lines: o.lines, url: o.url, reason: `Aviso automático do BagParser (${label}) para os admins.`, support: o.support, lang: 'pt' }),
  };
}
