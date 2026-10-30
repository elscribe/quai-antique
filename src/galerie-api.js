import { fetchJson } from "./http.js";
import { photoUrl } from "./photo-url.js";
const gallery = document.querySelector("#gallery-list");
const status = document.querySelector("#gallery-status");

try {
  const body = await fetchJson("photos", { token: null });
  if (!Array.isArray(body.data)) throw new Error("Galerie indisponible");
  for (const photo of body.data) {
    const url = photoUrl(photo.url_image);
    if (!url) continue;
    const button = document.createElement("button");
    button.type = "button";
    button.className = "gallery-card";
    button.dataset.src = url;
    button.dataset.alt = photo.titre;
    button.dataset.title = photo.titre;
    const image = document.createElement("img");
    image.src = url;
    image.alt = ""; // le titre est déjà lu dans le <span> du bouton : pas de doublon pour les lecteurs d'écran
    const title = document.createElement("span");
    title.textContent = photo.titre;
    button.append(image, title);
    gallery.append(button);
  }
  status.textContent = gallery.children.length ? "" : "La galerie sera bientôt disponible.";
} catch {
  status.textContent = "La galerie est momentanément indisponible.";
}
