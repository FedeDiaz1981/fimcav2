// src/lib/gallery-data.ts
export const CAT_ORDER = ['Interiores', 'Exteriores', 'Parque', 'Pileta'] as const;
export type Categoria = typeof CAT_ORDER[number];

export interface ImageItem {
  imagen: string;
  categoria: Categoria;
  caption?: string; // subcarpeta (id de cabaña)
  alt?: string;     // nombre de la carpeta padre inmediata
  width?: number;
  height?: number;
}
export type GalleryData = Partial<Record<Categoria, ImageItem[]>>;

// Puede venir de /src/assets (metadata) o /public (string)
type AnyImg = string | { src: string; width?: number; height?: number };

// Escanea TODO dentro de /public/images
const modules = import.meta.glob<AnyImg>(
  '../../public/images/**/*.{jpg,jpeg,png,webp}',
  { eager: true, import: 'default' }
) as Record<string, AnyImg>;

function toMeta(v: AnyImg) {
  if (typeof v === 'string') return { src: v, width: undefined, height: undefined };
  return { src: v.src, width: v.width, height: v.height };
}

function sortByFolderAndName([a]: [string, AnyImg], [b]: [string, AnyImg]) {
  const ap = a.split('/'), bp = b.split('/');
  const aFolder = ap.slice(0, -1).join('/'), bFolder = bp.slice(0, -1).join('/');
  if (aFolder !== bFolder) return aFolder.localeCompare(bFolder, undefined, { numeric: true });
  return ap.at(-1)!.localeCompare(bp.at(-1)!, undefined, { numeric: true });
}

function relSegs(p: string) {
  const parts = p.split('/');
  const i = parts.lastIndexOf('images');
  return i >= 0 ? parts.slice(i + 1) : parts; // [categoria, (subcarpetas...), archivo]
}

function normalizeCategoria(seg?: string): Categoria | null {
  if (!seg) return null;
  const s = seg.toLowerCase();

  for (const c of CAT_ORDER) if (c.toLowerCase() === s) return c;

  if (/(salon|living|cocina|dormitorio|ba(?:n|ñ)o|interiores?)/i.test(s)) return 'Interiores';
  if (/(exteriores?|patio|terraza|galer[ií]a)/i.test(s)) return 'Exteriores';
  if (/(parque|jard[ií]n)/i.test(s)) return 'Parque';
  if (/(pileta|piscina|alberca)/i.test(s)) return 'Pileta';
  return null;
}

/* === NUEVO: comparador por "nombre" (alt -> caption -> archivo) === */
function compareByNombre(a: ImageItem, b: ImageItem) {
  const nombre = (i: ImageItem) =>
    (i.alt?.trim() || i.caption?.trim() || i.imagen.split('/').pop() || '');

  const byName = nombre(a).localeCompare(nombre(b), 'es', { numeric: true, sensitivity: 'base' });
  if (byName !== 0) return byName;

  // Desempate por nombre de archivo completo para estabilidad
  return (a.imagen || '').localeCompare(b.imagen || '', 'es', { numeric: true, sensitivity: 'base' });
}

/**
 * Estructura recomendada:
 * public/images/
 *   Interiores/
 *     101/              ← (opcional) id cabaña -> caption
 *       foto-1.jpg
 *   Exteriores/
 *   Parque/
 *   Pileta/
 */
export function buildGalleryData(): GalleryData {
  const out: GalleryData = {};

  for (const [path, mod] of Object.entries(modules).sort(sortByFolderAndName)) {
    const segs = relSegs(path);
    const cat = normalizeCategoria(segs[0]);
    if (!cat) continue;

    // si hay subcarpeta después de la categoría, la usamos como caption (p. ej. "101")
    const caption = segs.length >= 3 ? segs[1] : undefined;

    // carpeta padre inmediata (la que contiene el archivo)
    const parentFolder = segs.length >= 2 ? segs[segs.length - 2] : undefined;

    const m = toMeta(mod);
    (out[cat] ||= []).push({
      imagen: m.src,
      width: m.width,
      height: m.height,
      categoria: cat,
      caption,
      alt: parentFolder,
    });
  }

  // === NUEVO: ordenar cada categoría por "nombre" ===
  for (const cat of CAT_ORDER) {
    if (out[cat]?.length) out[cat]!.sort(compareByNombre);
  }

  return out;
}

// Listo para importar directo en la página
export const GALLERY_DATA: GalleryData = buildGalleryData();
