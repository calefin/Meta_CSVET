import { useEffect, useState } from 'react';
import { PromoBox } from '../components/PromoBox';
import { SubscriptionBox } from '../components/SubscriptionBox';
import { NotifySettings } from '../components/NotifySettings';
import { ApiError, auth, type OAuthProvider } from '../api';
import { useAuth } from '../auth';
import { PROVIDER_NAMES, ProviderIcon, oauthErrorText, useOAuthProviders } from '../components/OAuthButtons';
import { useI18n } from '../i18n';
import { linkHandler, navigate } from '../router';

const errorText = (t: (k: string) => string, err: unknown) => {
  if (err instanceof ApiError) {
    const key = `auth_err_${err.code}`;
    if (t(key) !== key) return t(key);
  }
  return t('api_error');
};

export function AccountPage() {
  const { t, locale } = useI18n();
  const { user, setUser, logout, refresh } = useAuth();
  const providers = useOAuthProviders();

  const [name, setName] = useState('');
  const [character, setCharacter] = useState('');
  const [profileMsg, setProfileMsg] = useState<{ ok: boolean; text: string } | null>(null);

  const [currentPw, setCurrentPw] = useState('');
  const [newPw, setNewPw] = useState('');
  const [pwMsg, setPwMsg] = useState<{ ok: boolean; text: string } | null>(null);

  const [deletePw, setDeletePw] = useState('');
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleteMsg, setDeleteMsg] = useState<string | null>(null);

  // Return from linking Google/Discord: ?linked=<provider> or ?oauth_error=<code>
  const [linkMsg, setLinkMsg] = useState<{ ok: boolean; text: string } | null>(() => {
    const q = new URLSearchParams(window.location.search);
    const linked = q.get('linked') as OAuthProvider | null;
    if (linked && linked in PROVIDER_NAMES) return { ok: true, text: t('linked_ok', { provider: PROVIDER_NAMES[linked] }) };
    const err = oauthErrorText(t, q.get('oauth_error'));
    return err ? { ok: false, text: err } : null;
  });
  useEffect(() => {
    if (window.location.search) window.history.replaceState(null, '', '/account');
  }, []);

  useEffect(() => {
    if (user === null) navigate('/login');
    if (user) {
      setName(user.display_name);
      setCharacter(user.tibia_character ?? '');
    }
  }, [user]);

  if (!user) return <p className="muted">{t('loading')}</p>;

  const date = (iso: string) => new Date(iso).toLocaleDateString(locale);
  const hasPassword = user.has_password !== false;
  const identities = user.identities ?? [];

  const unlink = async (p: OAuthProvider) => {
    setLinkMsg(null);
    try {
      await auth.unlink(p);
      await refresh();
    } catch (err) {
      setLinkMsg({ ok: false, text: errorText(t, err) });
    }
  };

  const saveProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setProfileMsg(null);
    try {
      const res = await auth.update({ display_name: name, tibia_character: character.trim() || null });
      setUser({ ...user, ...res.user });
      setProfileMsg({ ok: true, text: t('saved') });
    } catch (err) {
      setProfileMsg({ ok: false, text: errorText(t, err) });
    }
  };

  const changePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    setPwMsg(null);
    try {
      await auth.changePassword(currentPw, newPw);
      setCurrentPw('');
      setNewPw('');
      setPwMsg({ ok: true, text: t('password_changed') });
      if (!hasPassword) await refresh();
    } catch (err) {
      setPwMsg({ ok: false, text: errorText(t, err) });
    }
  };

  const deleteAccount = async (e: React.FormEvent) => {
    e.preventDefault();
    setDeleteMsg(null);
    try {
      await auth.deleteAccount(hasPassword ? deletePw : '', hasPassword ? undefined : deletePw);
      setUser(null);
      navigate('/');
    } catch (err) {
      setDeleteMsg(errorText(t, err));
    }
  };

  return (
    <div className="page account-page">
      <h1>{t('account_title')}</h1>

      <section className="card">
        <div className="account-head">
          <div>
            <strong>{user.display_name}</strong>
            <div className="muted small">{user.email}</div>
            <div className="muted small">{t('member_since', { date: date(user.created_at) })}</div>
          </div>
          <div className="account-badges">
            {user.role === 'admin' && (
              <a className="tag admin" href="/admin" onClick={linkHandler('/admin')}>
                Admin
              </a>
            )}
            <span className={user.premium ? 'tag premium' : 'tag'}>
              {user.tier === 'guild' ? '🛡️ Guild VIP' : user.premium ? '⭐ VIP Pro' : t('plan_free')}
            </span>
          </div>
        </div>
        <p className="muted small">
          {user.premium && user.premium_until
            ? t('premium_until', { date: date(user.premium_until) })
            : t('plan_free_note')}
        </p>
        <SubscriptionBox />
        <PromoBox mode="redeem" />
      </section>

      <NotifySettings />

      <form className="card" onSubmit={saveProfile}>
        <h2>{t('profile')}</h2>
        <label className="field">
          <span>{t('display_name')}</span>
          <input value={name} onChange={(e) => setName(e.target.value)} required minLength={2} maxLength={30} />
        </label>
        <label className="field">
          <span>{t('tibia_character')}</span>
          <input value={character} onChange={(e) => setCharacter(e.target.value)} maxLength={29} placeholder={t('optional')} />
        </label>
        {profileMsg && <p className={profileMsg.ok ? 'notice' : 'alert'}>{profileMsg.text}</p>}
        <button type="submit">{t('save')}</button>
      </form>

      {(providers.length > 0 || identities.length > 0) && (
        <section className="card">
          <h2>{t('linked_logins')}</h2>
          <p className="muted small">{t('linked_logins_intro')}</p>
          <ul className="linked-logins">
            {(Object.keys(PROVIDER_NAMES) as OAuthProvider[])
              .filter((p) => providers.includes(p) || identities.some((i) => i.provider === p))
              .map((p) => {
                const linked = identities.find((i) => i.provider === p);
                return (
                  <li key={p}>
                    <ProviderIcon provider={p} />
                    <span className="linked-name">
                      <strong>{PROVIDER_NAMES[p]}</strong>
                      {linked?.email && <span className="muted small">{linked.email}</span>}
                    </span>
                    {linked ? (
                      <button type="button" className="secondary" onClick={() => void unlink(p)}>
                        {t('unlink')}
                      </button>
                    ) : (
                      <a className="button secondary" href={auth.oauthUrl(p, { link: true })}>
                        {t('link_provider', { provider: PROVIDER_NAMES[p] })}
                      </a>
                    )}
                  </li>
                );
              })}
          </ul>
          {linkMsg && <p className={linkMsg.ok ? 'notice' : 'alert'}>{linkMsg.text}</p>}
        </section>
      )}

      <form className="card" onSubmit={changePassword}>
        <h2>{hasPassword ? t('change_password') : t('set_password')}</h2>
        {!hasPassword && <p className="muted small">{t('set_password_intro')}</p>}
        {hasPassword && (
          <label className="field">
            <span>{t('current_password')}</span>
            <input type="password" value={currentPw} onChange={(e) => setCurrentPw(e.target.value)} required autoComplete="current-password" />
          </label>
        )}
        <label className="field">
          <span>{t('new_password')}</span>
          <input type="password" value={newPw} onChange={(e) => setNewPw(e.target.value)} required minLength={8} maxLength={200} autoComplete="new-password" />
          <small className="muted">{t('password_hint')}</small>
        </label>
        {pwMsg && <p className={pwMsg.ok ? 'notice' : 'alert'}>{pwMsg.text}</p>}
        <button type="submit">{hasPassword ? t('change_password') : t('set_password')}</button>
      </form>

      <section className="card">
        <div className="button-row">
          <button type="button" className="secondary" onClick={() => logout().then(() => navigate('/'))}>
            {t('logout')}
          </button>
          <a className="button secondary" href="/api/auth/export" download="bagparser-my-data.json">
            {t('export_data')}
          </a>
          <button type="button" className="secondary danger" onClick={() => setDeleteOpen((v) => !v)}>
            {t('delete_account')}
          </button>
        </div>
        {deleteOpen && (
          <form className="danger-zone" onSubmit={deleteAccount}>
            <p className="small">{t('delete_account_warning')}</p>
            <label className="field">
              <span>{hasPassword ? t('password') : t('delete_confirm_email')}</span>
              <input
                type={hasPassword ? 'password' : 'email'}
                value={deletePw}
                onChange={(e) => setDeletePw(e.target.value)}
                required
                autoComplete={hasPassword ? 'current-password' : 'off'}
                placeholder={hasPassword ? undefined : user.email}
              />
            </label>
            {deleteMsg && <p className="alert">{deleteMsg}</p>}
            <button type="submit" className="danger">
              {t('delete_account_confirm')}
            </button>
          </form>
        )}
      </section>
    </div>
  );
}
