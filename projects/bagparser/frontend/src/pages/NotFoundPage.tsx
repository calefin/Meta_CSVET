/** Real 404 page (D-49): unknown addresses used to show the home page silently. */
import { useEffect } from 'react';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';
import { NAV_GROUPS } from '../components/NavBar';

export function NotFoundPage({ path }: { path: string }) {
  const { t } = useI18n();
  useEffect(() => {
    // Keep crawlers from indexing the SPA fallback for this address
    const meta = document.createElement('meta');
    meta.name = 'robots';
    meta.content = 'noindex';
    document.head.appendChild(meta);
    document.title = `${t('nf_title')} | BagParser`;
    return () => meta.remove();
  }, [t]);

  return (
    <div className="page not-found">
      <h1>{t('nf_title')}</h1>
      <p className="muted">{t('nf_text', { path })}</p>
      <div className="tools-grid">
        {NAV_GROUPS.flatMap((g) => g.items)
          .slice(0, 6)
          .map((i) => (
            <a key={i.path} className="card tool-card" href={i.path} onClick={linkHandler(i.path)}>
              <strong>
                {i.icon} {t(i.key)}
              </strong>
              <span className="muted small">{t(i.desc)}</span>
            </a>
          ))}
      </div>
      <p>
        <a href="/" onClick={linkHandler('/')}>
          ← {t('nf_home')}
        </a>
      </p>
    </div>
  );
}
