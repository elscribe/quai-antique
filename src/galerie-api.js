import { apiBaseUrl, apiOrigin } from "./api-config.js";
const gallery = document.querySelector("#gallery-list");
const status = document.querySelector("#gallery-status");

try {
  const response = await fetch(`${apiBaseUrl}/photos`);
  const body = await response.json();
  if (!response.ok || !Array.isArray(body.data)) throw new Error("Galerie indisponible");
  for (const photo of body.data) {
    const url = photo.url_image.startsWith("/uploads/") ? apiOrigin + photo.url_image : photo.url_image;
    const button = document.createElement("button");
    button.type = "button";
    button.className = "gallery-card";
    button.dataset.src = url;
    button.dataset.alt = photo.titre;
    button.dataset.title = photo.titre;
    const image = document.createElement("img");
    image.src = url;
    image.alt = photo.titre;
    const title = document.createElement("span");
    title.textContent = photo.titre;
    button.append(image, title);
    gallery.append(button);
  }
  status.textContent = body.data.length ? "" : "La galerie sera bientôt disponible.";
} catch {
  status.textContent = "La galerie est momentanément indisponible.";
}
