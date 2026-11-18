import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import translations from './translations.json';
import { persistLocale, resolveUserLocale, type SupportedLocale } from './detector';

type Dict = Record<string, string>;
const dicts = translations as Record<SupportedLocale, Dict>;

export type TFunction = (key: string, vars?: Record<string, string | number>) => string;

interface I18nValue {
  locale: SupportedLocale;
  setLocale: (l: SupportedLocale) => void;
  t: TFunction;
  /** Number formatting in the active locale */
  n: (value: number) => string;
}

const I18nContext = createContext<I18nValue | null>(null);

export function I18nProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<SupportedLocale>(() => resolveUserLocale());

  useEffect(() => {
    document.documentElement.lang = locale;
  }, [locale]);

  const setLocale = useCallback((l: SupportedLocale) => {
    persistLocale(l);
    setLocaleState(l);
  }, []);

  const value = useMemo<I18nValue>(() => {
    const numberFormat = new Intl.NumberFormat(locale);
    const t: TFunction = (key, vars) => {
      let text = dicts[locale][key] ?? dicts.en[key] ?? key;
      if (vars) {
        for (const [k, v] of Object.entries(vars)) {
          text = text.split(`{{${k}}}`).join(String(v));
        }
      }
      return text;
    };
    return { locale, setLocale, t, n: (v) => numberFormat.format(v) };
  }, [locale, setLocale]);

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n(): I18nValue {
  const ctx = useContext(I18nContext);
  if (!ctx) throw new Error('useI18n must be used inside <I18nProvider>');
  return ctx;
}
