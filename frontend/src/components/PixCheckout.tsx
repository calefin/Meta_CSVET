/**
 * Pix checkout (D-69): QR code + "copia e cola" from Mercado Pago, countdown, and the status checked every few
 * seconds until the payment is confirmed (Pro days are added by the server, never by this page).
 */
import { useEffect, useState } from 'react';
import { ApiError, billing, type PixPayment } from '../api';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { Modal } from './Modal';
import { CopyText } from './CopyText';

export function PixCheckout({ plan, onClose }: { plan: 'monthly' | 'yearly'; onClose: () => void }) {
  const { t, locale } = useI18n();
  const { refresh } = useAuth();
  const [pix, setPix] = useState<PixPayment | null>(null);
  const [error, setError] = useState('');
  const [left, setLeft] = useState(0);

  const create = () => {
    setError('');
    setPix(null);
    billing
      .pixCreate(plan)
      .then(setPix)
      .catch((e) =>
        setError(
          e instanceof ApiError && e.code === 'stripe_active'
            ? t('pix_err_stripe')
            : e instanceof ApiError && e.code === 'rate_limited'
              ? t('pix_err_rate')
              : t('pix_err'),
        ),
      );
  };
  useEffect(create, [plan]); // eslint-disable-line react-hooks/exhaustive-deps

  // Countdown + status polling while pending
  useEffect(() => {
    if (!pix || pix.status !== 'pending') return;
    const tick = () => setLeft(Math.max(0, Math.round((Date.parse(pix.expires_at) - Date.now()) / 1000)));
    tick();
    const clock = setInterval(tick, 1000);
    const poll = setInterval(() => {
      billing
        .pixStatus(pix.id)
        .then((p) => {
          setPix(p);
          if (p.status === 'approved') void refresh();
        })
        .catch(() => null);
    }, 4000);
    return () => {
      clearInterval(clock);
      clearInterval(poll);
    };
  }, [pix?.id, pix?.status]); // eslint-disable-line react-hooks/exhaustive-deps

  const money = (cents: number) => `R$ ${(cents / 100).toFixed(2).replace('.', ',')}`;
  const expired = pix?.status === 'pending' && left === 0;

  return (
    <Modal title={t('pix_title')} onClose={onClose}>
      {error ? (
        <p className="alert">{error}</p>
      ) : !pix ? (
        <p className="muted">{t('pix_creating')}</p>
      ) : pix.status === 'approved' ? (
        <div className="pix-done">
          <p className="notice">✓ {t('pix_paid', { days: pix.days })}</p>
          {pix.premium_until && <p>{t('pix_until', { date: new Date(pix.premium_until).toLocaleDateString(locale) })}</p>}
          <button type="button" className="button" onClick={onClose}>
            {t('pix_close')}
          </button>
        </div>
      ) : expired || ['cancelled', 'rejected', 'expired'].includes(pix.status) ? (
        <div>
          <p className="alert">{t('pix_expired')}</p>
          <button type="button" className="button" onClick={create}>
            {t('pix_new')}
          </button>
        </div>
      ) : (
        <div className="pix-box">
          <p className="pix-amount">
            <strong>{money(pix.amount)}</strong> · {t('pix_days', { days: pix.days })}
          </p>
          {pix.qr_base64 && <img className="pix-qr" src={`data:image/png;base64,${pix.qr_base64}`} alt={t('pix_qr_alt')} width={220} height={220} />}
          <p className="small muted">{t('pix_scan')}</p>
          {pix.qr_code && (
            <>
              <code className="pix-code">{pix.qr_code}</code>
              <CopyText text={pix.qr_code} label={`📋 ${t('pix_copy')}`} />
            </>
          )}
          <p className="small pix-wait">
            ⏳ {t('pix_waiting')} · {t('pix_expires_in', { time: `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}` })}
          </p>
          <p className="small muted">{t('pix_note')}</p>
        </div>
      )}
    </Modal>
  );
}
