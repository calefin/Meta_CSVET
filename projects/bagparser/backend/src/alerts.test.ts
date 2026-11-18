import { describe, expect, it } from 'vitest';
import { ALERTS_PER_HOUR, createAlerts, type AlertStore } from './alerts.js';
import type { Mailer } from './mailer.js';

function fakeStore(): AlertStore {
  const m = new Map<string, number>();
  return {
    async set(k) {
      if (m.has(k)) return null;
      m.set(k, 1);
      return 'OK';
    },
    async incr(k) {
      m.set(k, (m.get(k) ?? 0) + 1);
      return m.get(k)!;
    },
    async expire() {
      return 1;
    },
  };
}
function fakeMailer(sent: string[]): Mailer {
  return { enabled: true, support: 's@x', info: { host: 'h', from: 'f', support: 's@x', mode: 'smtp' }, send: async (to, mail) => void sent.push(`${to}|${mail.subject}`) };
}

describe('admin alerts (D-70)', () => {
  it('sends once per alert within 15 minutes and caps each kind per hour', async () => {
    const sent: string[] = [];
    const a = createAlerts({ mailer: fakeMailer(sent), store: fakeStore(), emails: ['a@x.com', 'b@x.com'], siteUrl: 'https://bagparser.com' });
    expect(await a.notify('error', 'Erro X', ['l1'])).toBe(true);
    expect(await a.notify('error', 'Erro X', ['l1'])).toBe(false); // same alert again: grouped
    for (let i = 0; i < 10; i++) await a.notify('error', `Erro ${i}`, ['l']);
    expect(sent.filter((s) => s.includes('Erro')).length).toBe(ALERTS_PER_HOUR);
    expect(await a.notify('good', 'Nova assinatura', ['x'])).toBe(true); // other kinds have their own cap
    expect(sent[0]).toBe('a@x.com, b@x.com|[BagParser] Erro X');
  });
  it('does nothing without e-mail or admins', async () => {
    const off = createAlerts({ mailer: null, store: fakeStore(), emails: ['a@x.com'], siteUrl: 'x' });
    expect(await off.notify('error', 't', [])).toBe(false);
    const sent: string[] = [];
    const noAdmins = createAlerts({ mailer: fakeMailer(sent), store: fakeStore(), emails: [], siteUrl: 'x' });
    expect(await noAdmins.notify('error', 't', [])).toBe(false);
  });
});
