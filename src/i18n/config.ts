import { en } from "./dictionaries/en";
import { es } from "./dictionaries/es";
import type { Dict } from "./types";

export const locales = ["en", "es"] as const;
export type Locale = typeof locales[number];
export const defaultLocale: Locale = "es";

export const dictionaries: Record<Locale, Dict> = { en, es };

export function getDict(lang: string): Dict {
  return dictionaries[(locales as readonly string[]).includes(lang) ? (lang as Locale) : defaultLocale];
}
