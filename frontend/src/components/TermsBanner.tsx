/** Asks signed-in users to accept a new version of the Terms/Privacy Policy (D-32). */
import { useState } from 'react';
import { auth } from '../api';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';

export function TermsBanner() {
  const { t } = useI18n();
  const { user, setUser } = useAuth();
  const [busy, setBusy] = useState(false);
  if (!user || user.terms_current) return null;
  return (
    <div className="terms-banner" role="alert">
      <span>
        {t('terms_updated')}{' '}
        <a href="/terms" onClick={linkHandler('/terms')}>
          {t('terms_of_use')}
        </a>{' '}
        ·{' '}
        <a href="/privacy" onClick={linkHandler('/privacy')}>
          {t('privacy_policy')}
        </a>
      </span>
      <button
        type="button"
        disabled={busy}
        onClick={async () => {
          setBusy(true);
          try {
            const res = await auth.acceptTerms();
            setUser({ ...user, ...res.user });
          } finally {
            setBusy(false);
          }
        }}
      >
        {t('accept')}
      </button>
    </div>
  );
}
