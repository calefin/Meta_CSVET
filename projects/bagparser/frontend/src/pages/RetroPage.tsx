/**
 * Monthly retrospective (D-76): the month in numbers and a card (1080×1350 PNG) to download or share.
 * The bot sends the link on the 1st; the page also lets you pick any of the last 12 months.
 */
import { useEffect, useMemo, useRef, useState } from 'react';
import { ApiError, retro, type Retro } from '../api';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';
import { gp } from './hunt/common';

function lastMonths(n: number): string[] {
  const now = new Date();
  const out: string[] = [];
  for (let i = 0; i < n; i++) {
    const d = new Date(Date.UTC(now.getFullYear(), now.getMonth() - i, 15));
    out.push(`${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`);
  }
  return out;
}

const monthLabel = (m: string, locale: string) =>
  new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${m}-15T12:00:00Z`));

/** Draws the share card; returns a PNG blob */
async function drawCard(r: Retro, locale: string, t: (k: string, v?: Record<string, string | number>) => string): Promise<Blob | null> {
  const W = 1080;
  const H = 1350;
  const c = document.createElement('canvas');
  c.width = W;
  c.height = H;
  const g = c.getContext('2d');
  if (!g) return null;
  const font = (size: number, weight = 600) => `${weight} ${size}px "Segoe UI", system-ui, -apple-system, Roboto, sans-serif`;
  // Background
  const bg = g.createLinearGradient(0, 0, 0, H);
  bg.addColorStop(0, '#14634a');
  bg.addColorStop(1, '#0d4634');
  g.fillStyle = bg;
  g.fillRect(0, 0, W, H);
  g.fillStyle = '#f6efe0';
  g.font = font(40, 600);
  g.fillText('BagParser', 80, 110);
  g.fillStyle = '#e8a317';
  g.beginPath();
  g.arc(1000, 92, 22, 0, Math.PI * 2);
  g.fill();
  g.fillStyle = '#f6efe0';
  g.font = font(72, 800);
  g.fillText(t('retro_card_title'), 80, 230);
  g.font = font(48, 500);
  g.fillStyle = '#e8a317';
  const ml = monthLabel(r.month, locale);
  g.fillText(ml.charAt(0).toUpperCase() + ml.slice(1), 80, 300);
  g.fillStyle = 'rgba(246,239,224,0.8)';
  g.font = font(36, 500);
  g.fillText(r.name, 80, 360);

  // Stat tiles
  const tiles: [string, string][] = [
    [t('retro_hunts'), String(r.hunts)],
    [t('retro_hours'), r.hours.toLocaleString(locale, { maximumFractionDigits: 1 })],
    ['XP', gp(r.xp, locale)],
    [t('retro_profit'), `${gp(r.profit, locale)} gp`],
  ];
  tiles.forEach(([label, value], i) => {
    const x = 80 + (i % 2) * 470;
    const y = 420 + Math.floor(i / 2) * 210;
    g.fillStyle = 'rgba(246,239,224,0.1)';
    g.beginPath();
    g.roundRect(x, y, 450, 180, 28);
    g.fill();
    g.fillStyle = 'rgba(246,239,224,0.75)';
    g.font = font(30, 600);
    g.fillText(label.toUpperCase(), x + 32, y + 56);
    g.fillStyle = '#ffffff';
    g.font = font(68, 800);
    g.fillText(value, x + 32, y + 140, 400);
  });

  // Lines
  let y = 900;
  const line = (icon: string, text: string) => {
    g.fillStyle = '#f6efe0';
    g.font = font(38, 600);
    g.fillText(`${icon}  ${text}`, 80, y, W - 160);
    y += 66;
  };
  if (r.best) line('🚀', `${t('retro_best')}: ${r.best.place} · ${gp(r.best.xp_h, locale)} XP/h`);
  if (r.favorite && r.favorite.hunts > 1) line('❤️', `${t('retro_favorite')}: ${r.favorite.place} (${r.favorite.hunts}×)`);
  for (const ch of r.chars.slice(0, 3)) line('📈', `${ch.name}: ${ch.level_to > ch.level_from ? `${ch.level_from} → ${ch.level_to} · ` : ''}+${gp(ch.exp_gain, locale)} XP`);
  if (r.deaths) line('☠️', t('retro_deaths', { n: r.deaths }));

  g.fillStyle = '#e8a317';
  g.font = font(36, 700);
  g.fillText('bagparser.com', 80, H - 80);
  return new Promise((resolve) => c.toBlob((b) => resolve(b), 'image/png'));
}

export function RetroPage() {
  const { t, locale } = useI18n();
  const { user } = useAuth();
  const months = useMemo(() => lastMonths(12), []);
  const initial = new URLSearchParams(window.location.search).get('month');
  const [month, setMonth] = useState(initial && /^\d{4}-\d{2}$/.test(initial) ? initial : months[1]);
  const [data, setData] = useState<Retro | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const preview = useRef<string | null>(null);
  const [img, setImg] = useState<string | null>(null);

  useEffect(() => {
    if (!user) return;
    setData(null);
    setError('');
    retro
      .get(month)
      .then(setData)
      .catch((e) => setError(e instanceof ApiError ? e.code : 'error'));
  }, [user, month]);

  useEffect(() => {
    if (!data) return;
    let alive = true;
    void drawCard(data, locale, t).then((b) => {
      if (!alive || !b) return;
      if (preview.current) URL.revokeObjectURL(preview.current);
      preview.current = URL.createObjectURL(b);
      setImg(preview.current);
    });
    return () => {
      alive = false;
    };
  }, [data, locale, t]);

  const share = async () => {
    if (!data) return;
    setBusy(true);
    try {
      const blob = await drawCard(data, locale, t);
      if (!blob) return;
      const file = new File([blob], `bagparser-${data.month}.png`, { type: 'image/png' });
      const nav = navigator as Navigator & { canShare?: (d: ShareData) => boolean };
      if (nav.canShare?.({ files: [file] })) {
        await nav.share({ files: [file], title: t('retro_card_title'), text: 'bagparser.com' }).catch(() => undefined);
      } else {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = file.name;
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 2000);
      }
    } finally {
      setBusy(false);
    }
  };

  if (!user)
    return (
      <div className="page">
        <h1>📊 {t('retro_title')}</h1>
        <p>
          <a href="/login" onClick={linkHandler('/login')}>
            {t('login')}
          </a>{' '}
          · {t('retro_login')}
        </p>
      </div>
    );

  const empty = data && !data.hunts && !data.chars.length;
  return (
    <div className="page retro-page">
      <h1>📊 {t('retro_title')}</h1>
      <p className="muted">{t('retro_intro')}</p>
      <label className="field inline">
        <span>{t('retro_month')}</span>{' '}
        <select value={month} onChange={(e) => setMonth(e.target.value)}>
          {months.map((m) => (
            <option key={m} value={m}>
              {monthLabel(m, locale)}
            </option>
          ))}
        </select>
      </label>
      {error && <p className="alert">{t('api_error')}</p>}
      {!data && !error && <p className="muted">…</p>}
      {data && empty && <p className="muted">{t('retro_empty')}</p>}
      {data && !empty && (
        <>
          <div className="kpi-grid" style={{ margin: '1rem 0' }}>
            <div className="kpi">
              <span className="kpi-label">{t('retro_hunts')}</span>
              <strong>{data.hunts}</strong>
            </div>
            <div className="kpi">
              <span className="kpi-label">{t('retro_hours')}</span>
              <strong>{data.hours.toLocaleString(locale, { maximumFractionDigits: 1 })}</strong>
            </div>
            <div className="kpi">
              <span className="kpi-label">XP</span>
              <strong>{gp(data.xp, locale)}</strong>
            </div>
            <div className="kpi">
              <span className="kpi-label">{t('retro_profit')}</span>
              <strong className={data.profit < 0 ? 'neg' : 'pos'}>{gp(data.profit, locale)} gp</strong>
            </div>
          </div>
          <ul className="retro-lines">
            {data.best && (
              <li>
                🚀 {t('retro_best')}: <strong>{data.best.place}</strong> · {gp(data.best.xp_h, locale)} XP/h
              </li>
            )}
            {data.favorite && data.favorite.hunts > 1 && (
              <li>
                ❤️ {t('retro_favorite')}: <strong>{data.favorite.place}</strong> ({data.favorite.hunts}×)
              </li>
            )}
            {data.chars.map((c) => (
              <li key={c.name}>
                📈 <strong>{c.name}</strong>: {c.level_to > c.level_from ? `${c.level_from} → ${c.level_to} · ` : ''}+{gp(c.exp_gain, locale)} XP
              </li>
            ))}
            {data.deaths > 0 && <li>☠️ {t('retro_deaths', { n: data.deaths })}</li>}
          </ul>
          <div className="button-row">
            <button type="button" className="button" disabled={busy} onClick={() => void share()}>
              📤 {t('retro_share')}
            </button>
          </div>
          {img && <img className="retro-card" src={img} alt={t('retro_card_title')} width={540} height={675} />}
        </>
      )}
    </div>
  );
}
