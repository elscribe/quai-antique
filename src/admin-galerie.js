import { fetchJson, messageUtilisateur } from "./http.js";
import { photoUrl } from "./photo-url.js";
const token = sessionStorage.getItem("quai_api_token");
const status = document.querySelector("#galerie-admin-status");
const zone = document.querySelector("#galerie-admin");
const loginPrompt = document.querySelector("#admin-login-prompt");
const upload = document.querySelector("#photo-upload");
const photos = document.querySelector("#photos-admin");

async function requete(path, method = "GET", payload = null) {
  return fetchJson(path, { method: method, body: payload });
}

async function charger() {
  const body = await requete("photos");
  photos.replaceChildren();
  if (!body.data.length) photos.textContent = "Aucune photo publiée.";
  for (const photo of body.data) {
    const article = document.createElement("article");
    const image = document.createElement("img");
    const url = photoUrl(photo.url_image);
    if (url) image.src = url;
    else image.hidden = true;
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
      } catch (error) { status.textContent = messageUtilisateur(error); }
    });
    remove.addEventListener("click", async () => {
      if (!window.confirm("Supprimer cette photo de la galerie ?")) return;
      try {
        await requete(`photos/${encodeURIComponent(photo.uuid)}`, "DELETE");
        status.textContent = "Photo supprimée.";
        await charger();
      } catch (error) { status.textContent = messageUtilisateur(error); }
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
  } catch (error) { status.textContent = messageUtilisateur(error); }
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
  status.textContent = messageUtilisateur(error);
}
