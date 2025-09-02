type Place = {
  id: string;
  name: string;                      // fallback
  labels?: { es?: string; en?: string }; // i18n opcional
  query?: string;
  lat?: number;
  lng?: number;
};
