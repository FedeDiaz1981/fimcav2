// src/types/gallery.ts

/** Categorías habilitadas en la galería */
export const CATEGORIES = ['Interiores', 'Exteriores', 'Parque', 'Pileta',] as const;

/** Tipo literal de categoría (derivado de CATEGORIES) */
export type Categoria = typeof CATEGORIES[number];

/** Item de imagen tal como lo consume el componente */
export interface ImageItem {
  /** URL absoluta o relativa a la imagen */
  imagen: string;
  /** Debe ser una de las categorías permitidas */
  categoria: Categoria;
  /** Texto mostrado debajo de la imagen */
  caption?: string;
  /** Alt de accesibilidad (si no está, se usa caption/categoría) */
  alt?: string;
  /** Dimensiones opcionales (recomendado si vienen del backend) */
  width?: number;
  height?: number;
  capacity?: number;
  model?: string;
}

export interface SimpleImage {
  src: string;
  alt?: string;
  width?: number;
  height?: number;
}

/** Mapa { categoría -> lista de imágenes } */
export type GalleryData = Record<Categoria, ImageItem[]>;

/** Útil cuando no siempre tenés todas las categorías pobladas */
export type PartialGalleryData = Partial<GalleryData>;

/** Type guard para validar categorías dinámicas (por si vienen como string del backend) */
export function isCategoria(value: unknown): value is Categoria {
  return typeof value === 'string' && (CATEGORIES as readonly string[]).includes(value);
}

/** Helper: agrupa un array plano de imágenes en un objeto por categoría */
export function groupByCategoria(items: ImageItem[]): PartialGalleryData {
  const acc: PartialGalleryData = {};
  for (const it of items) {
    if (!isCategoria(it.categoria)) continue;
    (acc[it.categoria] ??= []).push(it);
  }
  return acc;
}
