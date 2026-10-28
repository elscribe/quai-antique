import { apiBaseUrl, apiOrigin } from "./api-config.js";
const token = sessionStorage.getItem("quai_api_token");
const status = document.querySelector("#galerie-admin-status");
const zone = document.querySelector("#galerie-admin");
const loginPrompt = document.querySelector("#admin-login-prompt");
const upload = document.querySelector("#photo-upload");
const photos = document.querySelector("#photos-admin");

async function requete(path, method = "GET", payload = null) {
  const headers = { "X-AUTH-TOKEN": token };
  let body = payload;
  if (payload !== null && !(payload instanceof FormData)) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(payload);
  }
  const response = await fetch(`${apiBaseUrl}/${path}`, { method, headers, body });
  const data = response.status === 204 ? {} : await response.json();
  if (!response.ok) throw new Error(data.error || "La demande a échoué.");
  return data;
}

async function charger() {
  const body = await requete("photos");
  photos.replaceChildren();
  if (!body.data.length) photos.textContent = "Aucune photo publiée.";
  for (const photo of body.data) {
    const article = document.createElement("article");
    const image = document.createElement("img");
    image.src = photo.url_image.startsWith("/uploads/") ? apiOrigin + photo.url_image : photo.url_image;
    image.alt = photo.titre;
    image.width = 160;
    const form = document.createElement("form");
    form.className = "account-form";
    const label = document.createElement("label");
    label.append("Titre");
    const title = document.createElement("input");
    title.value = photo.titre;
    title.required = true;
    title.maxLength = 150;
    label.append(title);
    const imageLabel = document.createElement("label");
    imageLabel.append("Remplacer l’image (facultatif)");
    const file = document.createElement("input");
    file.type = "file";
    file.accept = "image/jpeg,image/png,image/webp";
    imageLabel.append(file);
    const save = document.createElement("button");
    save.type = "submit";
    save.className = "button";
    save.textContent = "Enregistrer la photo";
    const remove = document.createElement("button");
    remove.type = "button";
    remove.className = "button button--outline";
    remove.textContent = "Supprimer";
    form.append(label, imageLabel, save, remove);
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        if (file.files.length) {
          const data = new FormData();
          data.set("titre", title.value.trim());
          data.set("image", file.files[0]);
          await requete(`photos/${encodeURIComponent(photo.uuid)}/image`, "POST", data);
        } else {
          await requete(`photos/${encodeURIComponent(photo.uuid)}`, "PUT", { titre: title.value.trim(), url_image: photo.url_image });
        }
        status.textContent = "Photo modifiée.";
        await charger();
      } catch (error) { status.textContent = error.message; }
    });
    remove.addEventListener("click", async () => {
      if (!window.confirm("Supprimer cette photo de la galerie ?")) return;
      try {
        await requete(`photos/${encodeURIComponent(photo.uuid)}`, "DELETE");
        status.textContent = "Photo supprimée.";
        await charger();
      } catch (error) { status.textContent = error.message; }
    });
    article.append(image, form);
    photos.append(article);
  }
}

upload.addEventListener("submit", async (event) => {
  event.preventDefault();
  try {
    await requete("photos/upload", "POST", new FormData(upload));
    upload.reset();
    status.textContent = "Photo ajoutée.";
    await charger();
  } catch (error) { status.textContent = error.message; }
});

try {
  if (!token) throw new Error("Connectez-vous avec un compte administrateur.");
  const profile = await requete("account/me");
  if (!profile.data.roles.includes("ROLE_ADMIN")) throw new Error("Cet espace est réservé aux administrateurs.");
  zone.hidden = false;
  loginPrompt.hidden = true;
  await charger();
} catch (error) {
  zone.hidden = true;
  loginPrompt.hidden = false;
  status.textContent = error.message;
}
