/** Promo code box (D-50): /pricing checks a discount, /account redeems free Premium days. */
import { useEffect, useRef, useState } from 'react';
import { navigate } from '../router';

/** D-78: code from a /cupom/CODE link, applied on the account page (after signing up if needed) */
const PENDING_KEY = 'bagparser_pending_promo';
const readPending = (): string => {
  try {
    return localStorage.getItem(PENDING_KEY) ?? '';
  } catch {
    return '';
  }
};
const clearPending = () => {
  try {
    localStorage.removeItem(PENDING_KEY);
  } catch {
    /* private mode */
  }
};

export function PromoLink({ code }: { code: string }) {
  const { user } = useAuth();
  useEffect(() => {
    if (user === undefined) return; // still loading the session
    const clean = decodeURIComponent(code).trim().toUpperCase().slice(0, 32);
    try {
      if (clean) localStorage.setItem(PENDING_KEY, clean);
    } catch {
      /* private mode: the code is shown on the account page anyway */
    }
    navigate(user ? '/account' : '/register?next=/account');
  }, [code, user]);
  return null;
}
import { ApiError, promo } from '../api';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';

export function PromoBox({ mode, onDiscount }: { mode: 'check' | 'redeem'; onDiscount?: (percent: number, code: string) => void }) {
  const { t, locale } = useI18n();
  const { refresh } = useAuth();
  const [code, setCode] = useState(() => (mode === 'redeem' ? readPending() : ''));
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const auto = useRef(false);

  const submit = async (e?: React.FormEvent) => {
    e?.preventDefault();
    if (mode === 'redeem') clearPending();
    setBusy(true);
    setMsg(null);
    try {
      if (mode === 'redeem') {
        const r = await promo.redeem(code);
        if (r.applied && r.premium_until) {
          setMsg({ ok: true, text: t('promo_redeemed', { days: r.premium_days ?? 0, date: new Date(r.premium_until).toLocaleDateString(locale) }) });
          await refresh?.();
        } else setMsg({ ok: true, text: t('promo_is_discount', { pct: r.percent_off ?? 0 }) });
      } else {
        const r = await promo.check(code);
        if (r.kind === 'discount' && r.percent_off) {
          onDiscount?.(r.percent_off, r.code);
          setMsg({ ok: true, text: t('promo_discount_ok', { pct: r.percent_off }) });
        } else setMsg({ ok: true, text: t('promo_is_trial', { days: r.premium_days ?? 0 }) });
      }
    } catch (err) {
      const c = err instanceof ApiError ? err.code : 'error';
      const key = `promo_err_${c}`;
      setMsg({ ok: false, text: t(key) === key ? t('promo_err_invalid_code') : t(key) });
    } finally {
      setBusy(false);
    }
  };

  // A code that came from a /cupom link is applied as soon as the account page opens
  useEffect(() => {
    if (mode !== 'redeem' || auto.current || code.trim().length < 3 || !readPending()) return;
    auto.current = true;
    void submit();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mode]);

  return (
    <form className="promo-box" onSubmit={submit}>
      <label>
        <span>{t('promo_label')}</span>
        <span className="inline-form">
          <input value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} maxLength={32} autoCapitalize="characters" placeholder="CODE" />
          <button type="submit" disabled={busy || code.trim().length < 3}>
            {busy ? t('loading') : mode === 'redeem' ? t('promo_redeem') : t('promo_apply')}
          </button>
        </span>
      </label>
      {msg && <p className={msg.ok ? 'ok-text small' : 'error small'}>{msg.text}</p>}
    </form>
  );
}
