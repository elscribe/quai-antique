import { apiOrigin } from "./api-config.js";

// Même règle que l'API (GalleryPhotoController::input) : seules les images locales
// sous /assets/ ou /uploads/ sont acceptées. Toute autre valeur (URL externe,
// javascript:, data:, chemin avec "..") est ignorée et la photo n'est pas affichée.
const allowedPath =
  /^\/(?:assets\/(?:[A-Za-z0-9_-]+\/)*[A-Za-z0-9_-]+|uploads\/[a-f0-9]{32})\.(?:png|jpe?g|webp|gif)$/i;

export function photoUrl(value) {
  if (typeof value !== "string" || !allowedPath.test(value)) return null;
  return `${apiOrigin}${value}`;
}
