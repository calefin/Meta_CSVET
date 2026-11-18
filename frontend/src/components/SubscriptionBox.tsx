/** Subscription state + Stripe Customer Portal link on /account (D-51) */
import { useEffect, useState } from 'react';
import { billing } from '../api';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';

export function SubscriptionBox() {
  const { t } = useI18n();
  const { refresh } = useAuth();
  const [state, setState] = useState<Awaited<ReturnType<typeof billing.me>> | null>(null);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState('');
  const success = new URLSearchParams(window.location.search).get('checkout') === 'success';

  useEffect(() => {
    billing
      .me()
      .then(setState)
      .catch(() => null);
    // The webhook may land a few seconds after the redirect: refresh the plan once more
    if (success) {
      const id = setTimeout(() => {
        void refresh?.();
        billing.me().then(setState).catch(() => null);
      }, 4000);
      return () => clearTimeout(id);
    }
  }, [success, refresh]);

  const resume = async () => {
    setBusy(true);
    setMsg('');
    try {
      await billing.resume();
      setState(await billing.me());
      setMsg(t('sub_resumed'));
    } catch {
      setMsg(t('sub_portal_error'));
    } finally {
      setBusy(false);
    }
  };
  const live = state ? ['active', 'trialing', 'past_due'].includes(state.status ?? '') : false;

  const portal = async () => {
    setBusy(true);
    setMsg('');
    try {
      window.location.href = (await billing.portal()).url;
    } catch {
      setMsg(t('sub_portal_error'));
      setBusy(false);
    }
  };

  if (!state) return success ? <p className="ok-text">{t('sub_thanks')}</p> : null;
  return (
    <div className="subscription-box">
      {success && <p className="ok-text">{t('sub_thanks')}</p>}
      {state.status && (
        <p className="small">
          {t('sub_status')}: <strong>{t(`sub_status_${state.status}`) === `sub_status_${state.status}` ? state.status : t(`sub_status_${state.status}`)}</strong>
          {state.cancel_at_period_end && ` · ${t('sub_cancel_at_end')}`}
        </p>
      )}
      <div className="button-row">
        {live && state.cancel_at_period_end && (
          <button type="button" className="button" onClick={resume} disabled={busy || !state.enabled}>
            ↩️ {t('sub_resume')}
          </button>
        )}
        {state.has_customer && !live && (
          <a className="button" href="/pricing" onClick={linkHandler('/pricing')}>
            ⭐ {t('sub_subscribe_again')}
          </a>
        )}
        {state.has_customer ? (
          <button type="button" className="secondary" onClick={portal} disabled={busy || !state.enabled}>
            💳 {t('sub_manage')}
          </button>
        ) : (
          <a className="button secondary" href="/pricing" onClick={linkHandler('/pricing')}>
            ⭐ {t('sub_subscribe')}
          </a>
        )}
      </div>
      {msg && <p className={msg === t('sub_resumed') ? 'ok-text small' : 'error small'}>{msg}</p>}
    </div>
  );
}
