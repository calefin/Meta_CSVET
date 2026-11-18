import { useState, useRef, useEffect } from 'react';
import { useI18n } from '../i18n';
import { LOCALES_META, SUPPORTED_LOCALES } from '../i18n/detector';

export function LanguageSelector() {
  const { locale, setLocale, t } = useI18n();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (ref.current && !ref.current.contains(event.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const current = LOCALES_META[locale] ?? LOCALES_META.en;

  return (
    <div className="lang-selector-wrap" ref={ref} style={{ position: 'relative' }}>
      <button
        type="button"
        className="button secondary lang-btn"
        onClick={() => setOpen((prev) => !prev)}
        aria-expanded={open}
        aria-label={t('language')}
        title={current.name}
        style={{
          display: 'inline-flex',
          alignItems: 'center',
          gap: '0.4rem',
          padding: '0.35rem 0.65rem',
          fontSize: '0.95rem',
          borderRadius: '6px',
        }}
      >
        <span style={{ fontSize: '1.25rem', lineHeight: 1 }} aria-hidden="true">{current.flag}</span>
        <span className="lang-code" style={{ fontWeight: 600, fontSize: '0.85rem' }}>{current.code.toUpperCase()}</span>
        <span style={{ fontSize: '0.7rem', opacity: 0.7 }}>▼</span>
      </button>

      {open && (
        <ul
          className="lang-menu card"
          style={{
            position: 'absolute',
            top: '110%',
            right: 0,
            zIndex: 100,
            margin: 0,
            padding: '0.35rem',
            listStyle: 'none',
            minWidth: '140px',
            boxShadow: '0 8px 24px rgba(0,0,0,0.3)',
            borderRadius: '8px',
          }}
        >
          {SUPPORTED_LOCALES.map((code) => {
            const meta = LOCALES_META[code];
            const isSelected = code === locale;
            return (
              <li key={code}>
                <button
                  type="button"
                  onClick={() => {
                    setLocale(code);
                    setOpen(false);
                  }}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '0.6rem',
                    width: '100%',
                    padding: '0.45rem 0.65rem',
                    background: isSelected ? 'var(--color-bg-active, rgba(255,255,255,0.08))' : 'transparent',
                    border: 'none',
                    borderRadius: '4px',
                    color: 'inherit',
                    cursor: 'pointer',
                    textAlign: 'left',
                    fontWeight: isSelected ? 'bold' : 'normal',
                  }}
                >
                  <span style={{ fontSize: '1.2rem', lineHeight: 1 }} aria-hidden="true">{meta.flag}</span>
                  <span style={{ flex: 1, fontSize: '0.9rem' }}>{meta.name}</span>
                  {isSelected && <span style={{ color: 'var(--color-accent, #4ade80)' }}>✓</span>}
                </button>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
