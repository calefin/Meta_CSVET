export type SupportedLocale = 'en' | 'pt-BR' | 'es' | 'pl';

export const SUPPORTED_LOCALES: SupportedLocale[] = ['en', 'pt-BR', 'es', 'pl'];
export const LOCALE_STORAGE_KEY = 'bagparser_locale';

export interface LocaleMeta {
  code: SupportedLocale;
  name: string;
  flag: string;
}

export const LOCALES_META: Record<SupportedLocale, LocaleMeta> = {
  en: { code: 'en', name: 'English', flag: '🇺🇸' },
  'pt-BR': { code: 'pt-BR', name: 'Português', flag: '🇧🇷' },
  es: { code: 'es', name: 'Español', flag: '🇪🇸' },
  pl: { code: 'pl', name: 'Polski', flag: '🇵🇱' },
};

function isSupported(value: string | null): value is SupportedLocale {
  return !!value && (SUPPORTED_LOCALES as string[]).includes(value);
}

function readStorage(): string | null {
  try {
    return localStorage.getItem(LOCALE_STORAGE_KEY);
  } catch {
    return null;
  }
}

/** Primary language is English (en). Overridden by ?lang= or remembered choice in localStorage. */
export function resolveUserLocale(): SupportedLocale {
  const paramLang = new URLSearchParams(window.location.search).get('lang');
  if (isSupported(paramLang)) return paramLang;

  const stored = readStorage();
  if (isSupported(stored)) return stored;

  // Primary language default is English as requested
  return 'en';
}

export function persistLocale(locale: SupportedLocale): void {
  try {
    localStorage.setItem(LOCALE_STORAGE_KEY, locale);
  } catch {
    /* storage unavailable: choice lasts for this page view only */
  }
}
