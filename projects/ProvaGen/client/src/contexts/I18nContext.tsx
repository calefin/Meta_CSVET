import { createContext, useContext, useState, useCallback, ReactNode } from "react";
import { locales, type Locale, type TranslationKeys } from "@shared/locales/index";

const LOCALE_KEY = "profaid_locale";

type PathsToLeaves<T> = T extends object
  ? { [K in keyof T]: K extends string ? `${K}` | `${K}.${PathsToLeaves<T[K]>}` : never }[keyof T]
  : never;

type TranslationPath = PathsToLeaves<TranslationKeys>;

function getNestedValue(obj: Record<string, unknown>, path: string): string {
  const keys = path.split(".");
  let current: unknown = obj;
  for (const key of keys) {
    if (current == null || typeof current !== "object") return path;
    current = (current as Record<string, unknown>)[key];
  }
  return typeof current === "string" ? current : path;
}

type I18nContextType = {
  locale: Locale;
  setLocale: (locale: Locale) => void;
  t: (key: string) => string;
};

const I18nContext = createContext<I18nContextType>({
  locale: "pt",
  setLocale: () => {},
  t: (key) => key,
});

export function I18nProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>(() => {
    if (typeof window !== "undefined") {
      const params = new URLSearchParams(window.location.search);
      const urlLang = params.get("lang");
      if (urlLang === "pt" || urlLang === "en" || urlLang === "es") {
        localStorage.setItem(LOCALE_KEY, urlLang);
        return urlLang;
      }
    }
    const stored = localStorage.getItem(LOCALE_KEY);
    if (stored === "pt" || stored === "en" || stored === "es") return stored;
    return "pt";
  });

  const setLocale = useCallback((newLocale: Locale) => {
    setLocaleState(newLocale);
    localStorage.setItem(LOCALE_KEY, newLocale);
    
    // Atualiza a URL se necessário ou limpa se for o default
    if (typeof window !== "undefined") {
      const url = new URL(window.location.href);
      url.searchParams.set("lang", newLocale);
      window.history.replaceState({}, "", url.pathname + url.search);
    }
  }, []);

  const t = useCallback(
    (key: string): string => {
      const translations = locales[locale] as Record<string, unknown>;
      return getNestedValue(translations, key);
    },
    [locale]
  );

  return (
    <I18nContext.Provider value={{ locale, setLocale, t }}>
      {children}
    </I18nContext.Provider>
  );
}

export function useI18n(): I18nContextType {
  return useContext(I18nContext);
}
