import type { MiddlewareHandler } from "astro";
import { defaultLocale, locales } from "./i18n/config";

export const onRequest: MiddlewareHandler = async ({ request, redirect, url }, next) => {
  const [, maybeLang] = url.pathname.split("/"); // "", "es" | "en" | ...
  // /  -> /es
  if (url.pathname === "/") {
    return redirect(`/${defaultLocale}/`, 307);
  }
  // Si no es un lang válido, manda a default
  if (maybeLang && !locales.includes(maybeLang as any)) {
    return redirect(`/${defaultLocale}/`, 307);
  }
  return next();
};
