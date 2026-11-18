import { pt } from "./pt";
import { en } from "./en";
import { es } from "./es";

export type Locale = "pt" | "en" | "es";

export const locales = { pt, en, es } as const;

export type TranslationKeys = typeof pt;

export { pt, en, es };
