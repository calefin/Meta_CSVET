/**
 * My characters (D-28, D-49): added by name only, first snapshot right away.
 * Proving ownership (comment code) is optional and only gives the "verified owner" badge.
 * Server tracking (automatic snapshots, no clicks) is a Premium switch per character.
 */
import { useCallback, useEffect, useState } from 'react';
import { ApiError, characters, type CharacterListItem } from '../api';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';
import { CopyText } from '../components/CopyText';

export function errorKey(err: unknown): string {
  return err instanceof ApiError ? `char_err_${err.code}` : 'api_error';
}

/** Optional ownership proof, folded away by default */
function VerifyBox({ c, onDone }: { c: CharacterListItem; onDone: () => void }) {
  const { t } = useI18n();
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);
  const verify = async () => {
    setBusy(true);
    setMsg(null);
    try {
      await characters.verify(c.id);
      onDone();
    } catch (err) {
      const key = errorKey(err);
      setMsg(t(key) === key ? t('api_error') : t(key));
    } finally {
      setBusy(false);
    }
  };
  return (
    <details className="verify-box">
      <summary>{t('char_verify_optional')}</summary>
      <p className="muted small">{t('char_verify_why')}</p>
      <ol className="verify-steps">
        <li>
          {t('verify_step1')}{' '}
          <a href="https://www.tibia.com/account/?subtopic=accountmanagement" target="_blank" rel="noopener noreferrer">
            tibia.com → Account Management
          </a>
        </li>
        <li>
          {t('verify_step2')}{' '}
          <span className="verify-code">
            <CopyText text={c.verification_code} />
          </span>
        </li>
        <li>{t('verify_step3')}</li>
      </ol>
      {msg && <p className="alert">{msg}</p>}
      <button type="button" className="secondary" onClick={verify} disabled={busy}>
        {busy ? t('loading') : t('verify')}
      </button>
    </details>
  );
}

function CharCard({ c, premium, onChange }: { c: CharacterListItem; premium: boolean; onChange: () => void }) {
  const { t, n, locale } = useI18n();
  const [tracking, setTracking] = useState(!!c.auto_track);
  const [msg, setMsg] = useState<string | null>(null);

  const toggleTracking = async (on: boolean) => {
    setMsg(null);
    try {
      await characters.setTracking(c.id, on);
      setTracking(on);
    } catch (err) {
      setMsg(err instanceof ApiError && err.code === 'premium_required' ? t('char_tracking_pro') : t('api_error'));
    }
  };
  const remove = async () => {
    if (!window.confirm(t('char_confirm_remove', { name: c.name }))) return;
    await characters.remove(c.id).catch(() => undefined);
    onChange();
  };

  return (
    <li className="card char-card-v2">
      <a className="char-card-link" href={`/characters/${c.id}`} onClick={linkHandler(`/characters/${c.id}`)}>
        <div className="char-row-head">
          <strong>
            {c.name}
            {c.verified_at && (
              <span className="verified" title={t('ha_verified_owner')}>
                {' '}
                ✔
              </span>
            )}
          </strong>
          <span className="char-level">{t('level_n', { level: c.level ?? '?' })}</span>
        </div>
        <div className="muted small">
          {c.vocation} · {c.world}
          {c.guild ? ` · ${c.guild}` : ''}
        </div>
        {c.target_level && (
          <div className="small">
            {c.goal_achieved_at ? '✓ ' : '🎯 '}
            {t('goal_short', { level: c.target_level })}
            {c.target_date ? ` · ${new Date(`${c.target_date}T12:00:00`).toLocaleDateString(locale)}` : ''}
          </div>
        )}
        <div className="muted small">
          {c.last_refresh_at
            ? t('updated_at', { date: new Date(c.last_refresh_at).toLocaleString(locale, { dateStyle: 'short', timeStyle: 'short' }) })
            : ''}
          {c.experience != null ? ` · ${n(c.experience)} XP` : ''}
        </div>
      </a>
      <div className="char-card-actions">
        <label className={`check${premium ? '' : ' locked'}`} title={premium ? '' : t('char_tracking_pro')}>
          <input type="checkbox" checked={tracking} disabled={!premium} onChange={(e) => void toggleTracking(e.target.checked)} />{' '}
          {t('char_auto_tracking')} {!premium && <span className="tag pro">Pro</span>}
        </label>
        <a className="button secondary small-btn" href={`/hunt-analyser/mine`} onClick={linkHandler('/hunt-analyser/mine')}>
          🏹 {t('char_hunts')}
        </a>
        <button type="button" className="icon-btn" onClick={remove} title={t('delete')}>
          🗑️
        </button>
      </div>
      {msg && <p className="alert small">{msg}</p>}
      {!c.verified_at && <VerifyBox c={c} onDone={onChange} />}
    </li>
  );
}

export function CharactersPage() {
  const { t } = useI18n();
  const { user } = useAuth();
  const [list, setList] = useState<{ characters: CharacterListItem[]; limit: number; limits: { premium: number } } | null>(null);
  const [name, setName] = useState('');
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);

  const load = useCallback(() => {
    characters
      .list()
      .then(setList)
      .catch(() => setMsg(t('api_error')));
  }, [t]);

  useEffect(() => {
    if (user) load();
  }, [user, load]);

  if (user === undefined) return <p className="muted">{t('loading')}</p>;

  if (!user) {
    return (
      <div className="page chars-page">
        <h1>{t('chars_title')}</h1>
        <section className="card">
          <p>{t('chars_pitch')}</p>
          <ul className="plain-list bullets">
            <li>{t('chars_pitch_1')}</li>
            <li>{t('chars_pitch_2')}</li>
            <li>{t('chars_pitch_3')}</li>
          </ul>
          <div className="button-row">
            <a className="button" href="/register" onClick={linkHandler('/register')}>
              {t('register')}
            </a>
            <a className="button secondary" href="/login" onClick={linkHandler('/login')}>
              {t('login')}
            </a>
          </div>
        </section>
      </div>
    );
  }

  const add = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setMsg(null);
    try {
      await characters.add(name);
      setName('');
      load();
    } catch (err) {
      const key = errorKey(err);
      setMsg(
        err instanceof ApiError && err.code === 'character_limit'
          ? t('char_err_character_limit', { limit: String(err.data.limit), premium: list?.limits.premium ?? 10 })
          : t(key) === key
            ? t('api_error')
            : t(key),
      );
    } finally {
      setBusy(false);
    }
  };

  const full = list ? list.characters.length >= list.limit : false;

  return (
    <div className="page chars-page">
      <h1>{t('chars_title')}</h1>

      {list && (
        <form className="card add-char" onSubmit={add}>
          <h2>{t('add_character')}</h2>
          <p className="muted small">{t('char_add_hint')}</p>
          {!full ? (
            <div className="inline-form">
              <input
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                minLength={2}
                maxLength={29}
                autoComplete="off"
                placeholder={t('character_name')}
                aria-label={t('character_name')}
              />
              <button type="submit" disabled={busy}>
                {busy ? t('loading') : t('add_character')}
              </button>
            </div>
          ) : (
            <p className="muted">{t('char_err_character_limit', { limit: list.limit, premium: list.limits.premium })}</p>
          )}
          {msg && <p className="alert">{msg}</p>}
          <p className="muted small">
            {t('chars_limit_note', { used: list.characters.length, limit: list.limit })}
            {!user.premium && (
              <>
                {' · '}
                <a href="/pricing" onClick={linkHandler('/pricing')}>
                  ⭐ {t('chars_limit_upgrade_link')}
                </a>
              </>
            )}
          </p>
        </form>
      )}

      {!list && !msg && <p className="muted">{t('loading')}</p>}

      {list && list.characters.length > 0 && (
        <ul className="char-list">
          {list.characters.map((c) => (
            <CharCard key={c.id} c={c} premium={!!user.premium} onChange={load} />
          ))}
        </ul>
      )}

      {list && !user.premium && (
        <section className="card promo-banner">
          <div>
            <span className="tag pro">PRO</span> <strong>{t('chars_upgrade_title')}</strong>
            <p className="muted small">{t('char_pro_pitch', { premium: list.limits.premium })}</p>
          </div>
          <a className="button" href="/pricing" onClick={linkHandler('/pricing')}>
            ★ {t('chars_upgrade_cta')}
          </a>
        </section>
      )}
    </div>
  );
}
