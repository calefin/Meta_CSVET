/**
 * Bulletins and summaries (D-71, D-75): Telegram (linked chat + groups activated by me) and Discord channels.
 * Each destination: daily bulletin (hour, world), my hunts (private chat / my channels only) and weekly summary.
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import { ApiError, daily, notify, type NotifyPatch, type NotifyState, type NotifyTarget } from '../api';
import { useI18n } from '../i18n';
import { useAuth } from '../auth';
import { linkHandler } from '../router';

const browserTz = (() => {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Sao_Paulo';
  } catch {
    return 'America/Sao_Paulo';
  }
})();

const HOURS = Array.from({ length: 24 }, (_, h) => h);

function TargetSettings({
  target,
  worlds,
  busy,
  personalAllowed,
  onPatch,
}: {
  target: NotifyTarget;
  worlds: string[];
  busy: boolean;
  personalAllowed: boolean;
  onPatch: (p: NotifyPatch) => void;
}) {
  const { t } = useI18n();
  const { user } = useAuth();
  const pro = !!user?.premium;
  const otherTz = target.tz !== browserTz;
  const [guild, setGuild] = useState(target.guild ?? '');
  useEffect(() => setGuild(target.guild ?? ''), [target.guild]);
  const saveGuild = () => {
    const v = guild.trim();
    if (v === (target.guild ?? '')) return;
    onPatch({ guild: v || null });
  };
  return (
    <div className="notify-target-settings">
      <div className="notify-row">
        <label className="field inline">
          <input type="checkbox" checked={target.daily} disabled={busy} onChange={(e) => onPatch({ daily: e.target.checked })} /> {t('notify_daily')}
        </label>
        <select
          aria-label={t('notify_daily')}
          value={target.hour}
          disabled={busy}
          onChange={(e) => onPatch({ hour: Number(e.target.value), tz: browserTz })}
        >
          {HOURS.map((h) => (
            <option key={h} value={h}>
              {String(h).padStart(2, '0')}:00
            </option>
          ))}
        </select>
        {otherTz && <span className="muted small">({target.tz.replace(/_/g, ' ')})</span>}
      </div>
      <div className="notify-row">
        <label className="muted small" htmlFor={`world-${target.id}`}>
          {t('notify_world')}
        </label>
        <select id={`world-${target.id}`} value={target.world ?? ''} disabled={busy} onChange={(e) => onPatch({ world: e.target.value || null })}>
          <option value="">— {t('notify_no_world')} —</option>
          {worlds.map((w) => (
            <option key={w} value={w}>
              {w}
            </option>
          ))}
        </select>
      </div>
      <div className="notify-row">
        <label className="muted small" htmlFor={`guild-${target.id}`}>
          🏆 {t('notify_guild')}
        </label>
        {pro ? (
          <input
            id={`guild-${target.id}`}
            className="notify-guild"
            value={guild}
            disabled={busy}
            placeholder={t('notify_guild_ph')}
            maxLength={40}
            onChange={(e) => setGuild(e.target.value)}
            onBlur={saveGuild}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                saveGuild();
              }
            }}
          />
        ) : (
          <a className="small" href="/pricing" onClick={linkHandler('/pricing')}>
            {t('notify_pro_only')}
          </a>
        )}
      </div>
      {personalAllowed && (
        <div className="notify-row">
          <label className="field inline">
            <input type="checkbox" checked={target.alerts} disabled={busy || !pro} onChange={(e) => onPatch({ alerts: e.target.checked })} /> {t('notify_alerts')}
          </label>
          {!pro && (
            <a className="small" href="/pricing" onClick={linkHandler('/pricing')}>
              {t('notify_pro_only')}
            </a>
          )}
        </div>
      )}
      {personalAllowed && (
        <div className="notify-row">
          <label className="field inline">
            <input type="checkbox" checked={target.personal} disabled={busy} onChange={(e) => onPatch({ personal: e.target.checked, ...(e.target.checked ? {} : { weekly: false }) })} />{' '}
            {t('notify_personal')}
          </label>
          {target.personal && (
            <label className="field inline">
              <input type="checkbox" checked={target.weekly} disabled={busy} onChange={(e) => onPatch({ weekly: e.target.checked })} /> {t('notify_weekly')}
            </label>
          )}
        </div>
      )}
    </div>
  );
}

export function NotifySettings() {
  const { t, locale } = useI18n();
  const [state, setState] = useState<NotifyState | null>(null);
  const [worlds, setWorlds] = useState<string[]>([]);
  const [url, setUrl] = useState('');
  const [label, setLabel] = useState('');
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const [linking, setLinking] = useState(false);
  const pollRef = useRef<number | null>(null);

  const load = useCallback(() => notify.get().then(setState), []);

  useEffect(() => {
    load().catch(() => null);
    daily
      .worlds()
      .then((w) => setWorlds([...w].sort((a, b) => a.localeCompare(b))))
      .catch(() => null);
    return () => {
      if (pollRef.current) window.clearInterval(pollRef.current);
    };
  }, [load]);

  const errText = (e: unknown) => {
    const code = e instanceof ApiError ? e.code : 'error';
    const key = `notify_err_${code}`;
    return t(key) === key ? t('api_error') : t(key);
  };

  const run = async (fn: () => Promise<unknown>, ok: string) => {
    setBusy(true);
    setMsg(null);
    try {
      await fn();
      setMsg({ ok: true, text: ok });
    } catch (e) {
      setMsg({ ok: false, text: errText(e) });
    } finally {
      setBusy(false);
    }
  };

  const replace = (n: NotifyTarget) =>
    setState((s) =>
      s
        ? {
            telegram: {
              ...s.telegram,
              chat: s.telegram.chat?.id === n.id ? n : s.telegram.chat,
              groups: s.telegram.groups.map((g) => (g.id === n.id ? n : g)),
            },
            discord: s.discord.map((d) => (d.id === n.id ? n : d)),
          }
        : s,
    );

  const patch = (target: NotifyTarget, p: NotifyPatch) => void run(async () => replace(await notify.update(target.id, p)), t('notify_saved'));
  const remove = (target: NotifyTarget, ok: string) =>
    void run(async () => {
      await notify.remove(target.id);
      await load();
    }, ok);
  const test = (target: NotifyTarget) => void run(() => notify.test(target.id), t('notify_test_ok'));

  const linkTelegram = () =>
    void run(async () => {
      // Opened right away (a window opened after an await is blocked as a pop-up on phones)
      const win = window.open('about:blank', '_blank');
      try {
        const r = await notify.telegramLink();
        if (win) win.location.href = r.url;
        else window.location.href = r.url;
      } catch (e) {
        win?.close();
        throw e;
      }
      setLinking(true);
      let tries = 0;
      if (pollRef.current) window.clearInterval(pollRef.current);
      pollRef.current = window.setInterval(() => {
        tries += 1;
        notify
          .get()
          .then((s) => {
            setState(s);
            if (s.telegram.chat || tries > 60) {
              if (pollRef.current) window.clearInterval(pollRef.current);
              setLinking(false);
              if (s.telegram.chat) setMsg({ ok: true, text: t('notify_tg_done') });
            }
          })
          .catch(() => null);
      }, 3000);
    }, '');

  if (!state) return null;
  const tg = state.telegram;
  const botLink = tg.bot ? (
    <a href={`https://t.me/${tg.bot}`} target="_blank" rel="noreferrer">
      @{tg.bot}
    </a>
  ) : null;
  const withBot = (key: string) => {
    const [before, after] = t(key, { bot: '\u0000' }).split('\u0000');
    return (
      <>
        {before}
        {botLink}
        {after}
      </>
    );
  };

  return (
    <section className="card notify-settings">
      <h2>📬 {t('notify_title')}</h2>
      <p className="muted small">{t('notify_intro')}</p>

      <h3>✈️ {t('notify_tg_title')}</h3>
      {!tg.enabled ? (
        <p className="muted small">{t('notify_tg_soon')}</p>
      ) : tg.chat ? (
        <div className="notify-target">
          <p className="small">
            ✅ {t('notify_tg_linked', { name: tg.chat.title ?? 'Telegram' })} · {botLink}
          </p>
          <TargetSettings target={tg.chat} worlds={worlds} busy={busy} personalAllowed onPatch={(p) => patch(tg.chat!, p)} />
          <div className="button-row">
            <button type="button" className="button secondary small-btn" disabled={busy} onClick={() => test(tg.chat!)}>
              🔔 {t('notify_test')}
            </button>
            <button type="button" className="button secondary small-btn danger" disabled={busy} onClick={() => remove(tg.chat!, t('notify_tg_unlinked'))}>
              {t('notify_tg_unlink')}
            </button>
          </div>
        </div>
      ) : (
        <div className="button-row">
          <button type="button" className="button" disabled={busy || linking} onClick={linkTelegram}>
            ✈️ {t('notify_tg_link')}
          </button>
          {linking && <span className="muted small">{t('notify_tg_linking')}</span>}
        </div>
      )}
      {tg.enabled && (
        <>
          {tg.groups.length > 0 && (
            <>
              <h4>{t('notify_tg_groups')}</h4>
              <p className="muted small">{t('notify_group_note')}</p>
              {tg.groups.map((g) => (
                <div key={g.id} className="notify-target">
                  <p className="small">
                    <strong>{g.title ?? 'Telegram'}</strong>
                  </p>
                  <TargetSettings target={g} worlds={worlds} busy={busy} personalAllowed={false} onPatch={(p) => patch(g, p)} />
                  <div className="button-row">
                    <button type="button" className="button secondary small-btn" disabled={busy} onClick={() => test(g)}>
                      🔔 {t('notify_test')}
                    </button>
                    <button type="button" className="button secondary small-btn" disabled={busy} onClick={() => remove(g, t('notify_removed'))}>
                      {t('notify_stop_group')}
                    </button>
                  </div>
                </div>
              ))}
            </>
          )}
          <p className="muted small">{withBot('notify_tg_group_hint')}</p>
        </>
      )}

      <h3>💬 {t('notify_dc_title')}</h3>
      {state.discord.map((d) => (
        <div key={d.id} className="notify-target">
          <p className="small">
            <strong>{d.title ?? 'Discord'}</strong> <code>{d.webhook}</code>
          </p>
          <TargetSettings target={d} worlds={worlds} busy={busy} personalAllowed onPatch={(p) => patch(d, p)} />
          <div className="button-row">
            <button type="button" className="button secondary small-btn" disabled={busy} onClick={() => test(d)}>
              🔔 {t('notify_test')}
            </button>
            <button type="button" className="button secondary small-btn danger" disabled={busy} onClick={() => remove(d, t('notify_removed'))}>
              {t('notify_remove')}
            </button>
          </div>
        </div>
      ))}
      {state.discord.length < 5 ? (
        <form
          className="notify-add"
          onSubmit={(e) => {
            e.preventDefault();
            void run(async () => {
              await notify.addDiscord(url.trim(), label.trim(), locale, browserTz);
              setUrl('');
              setLabel('');
              await load();
            }, t('notify_added'));
          }}
        >
          <input value={url} onChange={(e) => setUrl(e.target.value)} placeholder="https://discord.com/api/webhooks/…" aria-label="Webhook URL" />
          <input value={label} onChange={(e) => setLabel(e.target.value)} placeholder={t('notify_dc_label')} aria-label={t('notify_dc_label')} maxLength={60} />
          <button type="submit" className="button" disabled={busy || !url.includes('/api/webhooks/')}>
            {t('notify_dc_add')}
          </button>
        </form>
      ) : (
        <p className="muted small">{t('notify_dc_limit')}</p>
      )}
      <details className="small">
        <summary>{t('notify_how')}</summary>
        <ol>
          <li>{t('notify_how_1')}</li>
          <li>{t('notify_how_2')}</li>
          <li>{t('notify_how_3')}</li>
        </ol>
      </details>
      <p className="small">
        📊{' '}
        <a href="/retro" onClick={linkHandler('/retro')}>
          {t('retro_link')}
        </a>
        {tg.enabled && tg.bot && (
          <>
            {' '}
            · 💚{' '}
            <a href={`https://t.me/${tg.bot}?start=apoiar`} target="_blank" rel="noreferrer">
              {t('notify_donate')}
            </a>
          </>
        )}
      </p>
      {msg && msg.text && <p className={msg.ok ? 'small pos' : 'small error'}>{msg.text}</p>}
    </section>
  );
}
