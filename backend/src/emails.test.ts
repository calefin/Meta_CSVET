import { describe, expect, it } from 'vitest';
import { mailLang, passwordChangedEmail, resetEmail, ticketNoticeEmail } from './emails.js';

describe('transactional e-mails (D-65)', () => {
  it('picks the language from the site locale or Accept-Language', () => {
    expect(mailLang('pt-BR')).toBe('pt');
    expect(mailLang('es-ES,es;q=0.9')).toBe('es');
    expect(mailLang('pl')).toBe('pl');
    expect(mailLang(undefined)).toBe('en');
  });

  it('reset e-mail has HTML + plain text, the link and the expiry, and escapes the name', () => {
    const m = resetEmail({ lang: 'pt', name: '<img src=x onerror=alert(1)>', url: 'https://bagparser.com/reset?token=abc', minutes: 60, support: 'support@csvet.app' });
    expect(m.subject).toBe('Redefinir sua senha do BagParser');
    expect(m.html).toContain('href="https://bagparser.com/reset?token=abc"');
    expect(m.html).not.toContain('<img src=x');
    expect(m.html).toContain('&lt;img src=x');
    expect(m.text).toContain('https://bagparser.com/reset?token=abc');
    expect(m.text).toContain('60 minutos');
    expect(m.html).not.toMatch(/<script|<img /i);
  });

  it('password changed notice and ticket notice', () => {
    const c = passwordChangedEmail({ lang: 'en', name: 'Bob', when: new Date('2026-11-07T12:00:00Z'), support: 's@x', loginUrl: 'https://bagparser.com/login' });
    expect(c.subject).toBe('Your BagParser password was changed');
    expect(c.text).toContain('https://bagparser.com/login');
    const t = ticketNoticeEmail({ id: 7, name: 'Ana', email: 'ana@x.com', category: 'bug', subject: 'Parser', message: 'linha 1\n<b>linha 2</b>', adminUrl: 'https://bagparser.com/admin/tickets', support: 's@x' });
    expect(t.subject).toBe('[BagParser #7] Parser');
    expect(t.html).toContain('linha 1<br>&lt;b&gt;linha 2');
  });
});
