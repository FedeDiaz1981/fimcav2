export type Locale = "es" | "en";

export type Place = {
  id: string;
  name: string;
  labels?: Partial<Record<Locale, string>>;
  query?: string;
  lat?: number;
  lng?: number;
};

export interface MapaProps {
  brandColor?: string;
  alto?: number;
  useRouting?: boolean;
  fijo?: string | { lat: number; lng: number };
  lugares?: Place[];
  locale?: Locale;
  i18n?: { title?: string; subtitle?: string };
}