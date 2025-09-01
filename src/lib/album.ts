// src/lib/album.ts
export type AlbumItem = {
  imagen: string;           // <-- siempre string
  categoria: string;
  caption: string;
  capacity: number;
  model: "Confort" | "Familiar";
  width?: number;
  height?: number;
};

// Puede venir de /src/assets (ImageMetadata) o de /public (string)
type AnyImg = string | { src: string; width?: number; height?: number };

// OJO: dejá eager + import:"default" (sirve para ambos casos)
const modules = import.meta.glob<AnyImg>(
  "../../public/images/{Confort,Familiar}/**/*.{jpg,jpeg,png,webp}",
  { eager: true, import: "default" }
) as Record<string, AnyImg>;

function toMeta(val: AnyImg) {
  if (typeof val === "string") return { src: val, width: undefined, height: undefined };
  return { src: val.src, width: val.width, height: val.height };
}

function parentFolder(path: string, model: "Confort" | "Familiar") {
  const parts = path.split("/");
  const i = parts.lastIndexOf(model);
  return i >= 0 && parts[i + 1] ? parts[i + 1] : "";
}

function sortByFolderAndName([aPath]: [string, AnyImg], [bPath]: [string, AnyImg]) {
  const ap = aPath.split("/");
  const bp = bPath.split("/");
  const aFolder = ap.slice(0, -1).join("/");
  const bFolder = bp.slice(0, -1).join("/");
  if (aFolder !== bFolder) return aFolder.localeCompare(bFolder, undefined, { numeric: true });
  return ap[ap.length - 1].localeCompare(bp[bp.length - 1], undefined, { numeric: true });
}

export const ALBUM: AlbumItem[] = Object.entries(modules)
  .sort(sortByFolderAndName)
  .map(([path, mod]) => {
    const model: "Confort" | "Familiar" = path.includes("/Confort/") ? "Confort" : "Familiar";
    const meta = toMeta(mod);
    return {
      imagen: meta.src,                 // <-- URL string final
      width: meta.width,                // (opcional)
      height: meta.height,              // (opcional)
      categoria: "",                    // poné "Interiores" si lo querés fijo
      caption: parentFolder(path, model),
      capacity: model === "Confort" ? 3 : 6,
      model,
    };
  });

export default ALBUM;
