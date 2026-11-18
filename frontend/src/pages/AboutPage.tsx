import { useEffect } from 'react';
import { useI18n } from '../i18n';
import { SITE } from '../site';
import { linkHandler } from '../router';

/**
 * About / contact / monetisation / privacy (D-27).
 * Covers the Tibia Fansite Agreement items: CipSoft credit with links, contact section,
 * transparent monetisation, and the site's own rules.
 */
export function AboutPage() {
  const { t } = useI18n();

  useEffect(() => {
    const id = window.location.hash.slice(1);
    if (id) document.getElementById(id)?.scrollIntoView();
  }, []);

  return (
    <div className="page about-page">
      <h1>{t('about_title')}</h1>

      <section className="card" id="about">
        <p>{t('about_intro')}</p>
        <p>
          {t('credit_made_by')}{' '}
          <a href="https://www.cipsoft.com" target="_blank" rel="noopener noreferrer">
            CipSoft GmbH
          </a>
. {t('credit_copyright')} (
          <a href="https://www.tibia.com" target="_blank" rel="noopener noreferrer">
            tibia.com
          </a>
          )
        </p>
        <p className="muted small">{t('about_not_affiliated')}</p>
      </section>

      <section className="card" id="contact">
        <h2>{t('contact')}</h2>
        <p>
          {t('contact_owner')}: <strong>{SITE.owner}</strong>
          {SITE.ownerCharacter && (
            <>
              {' '}
              ({t('tibia_character')}: {SITE.ownerCharacter})
            </>
          )}
        </p>
        <p>
          {t('email')}: <a href={`mailto:${SITE.contactEmail}`}>{SITE.contactEmail}</a>
        </p>
        <p className="muted small">{t('contact_note')}</p>
      </section>

      <section className="card" id="monetization">
        <h2>{t('monetization_title')}</h2>
        <p>{t('monetization_intro')}</p>
        <ul className="plain-list bullets">
          <li>{t('monetization_free')}</li>
          <li>{t('monetization_premium')}</li>
          <li>{t('monetization_ads')}</li>
          <li>{t('monetization_never')}</li>
        </ul>
      </section>

      <section className="card" id="privacy">
        <h2>{t('privacy_policy')}</h2>
        <p>{t('privacy_summary')}</p>
        <p>
          <a href="/privacy" onClick={linkHandler('/privacy')}>
            {t('privacy_policy')}
          </a>{' '}
          ·{' '}
          <a href="/terms" onClick={linkHandler('/terms')}>
            {t('terms_of_use')}
          </a>
        </p>
      </section>

      <section className="card" id="rules">
        <h2>{t('rules_title')}</h2>
        <p>{t('rules_text')}</p>
      </section>
    </div>
  );
}
