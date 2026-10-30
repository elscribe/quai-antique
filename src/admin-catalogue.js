import { fetchJson, messageUtilisateur } from "./http.js";

const token = sessionStorage.getItem("quai_api_token");
const status = document.querySelector("#catalogue-admin-status");
const zone = document.querySelector("#catalogue-admin");
const loginPrompt = document.querySelector("#admin-login-prompt");
const forms = {
  categories: document.querySelector("#categorie-form"),
  plats: document.querySelector("#plat-form"),
  menus: document.querySelector("#menu-form"),
};
const targets = {
  categories: document.querySelector("#categories-admin"),
  plats: document.querySelector("#plats-admin"),
  menus: document.querySelector("#menus-admin"),
};

async function requete(path, method = "GET", payload = null) {
  return fetchJson(path, { method: method, body: payload });
}

function afficherListe(type, items) {
  const target = targets[type];
  target.replaceChildren();
  if (!items.length) {
    target.textContent = "Aucun élément enregistré.";
    return;
  }
  for (const item of items) {
    const article = document.createElement("article");
    const titre = document.createElement("h3");
    titre.textContent = item.titre;
    const detail = document.createElement("p");
    detail.textContent = type === "categories" ? "" : `${item.description} — ${item.prix} €`;
    const edit = document.createElement("button");
    edit.type = "button";
    edit.className = "button button--outline";
    edit.textContent = "Modifier";
    edit.addEventListener("click", () => {
      const form = forms[type];
      form.elements.uuid.value = item.uuid;
      form.elements.titre.value = item.titre;
      if (type !== "categories") {
        form.elements.description.value = item.description;
        form.elements.prix.value = item.prix;
      }
      if (type === "plats") form.elements.categorie_uuid.value = item.categorie.uuid;
      if (type === "menus") {
        const ids = new Set(item.categories.map((category) => category.uuid));
        for (const option of form.elements.categories.options) option.selected = ids.has(option.value);
      }
      form.elements.titre.focus();
    });
    const remove = document.createElement("button");
    remove.type = "button";
    remove.className = "button button--outline";
    remove.textContent = "Supprimer";
    remove.addEventListener("click", async () => {
      if (!window.confirm(`Supprimer « ${item.titre} » ?`)) return;
      try {
        await requete(`${type}/${encodeURIComponent(item.uuid)}`, "DELETE");
        status.textContent = "Élément supprimé.";
        await charger();
      } catch (error) { status.textContent = messageUtilisateur(error); }
    });
    article.append(titre, detail, edit, remove);
    target.append(article);
  }
}

async function charger() {
  const [categories, plats, menus] = await Promise.all([
    requete("categories"), requete("plats"), requete("menus"),
  ]);
  const select = forms.plats.elements.categorie_uuid;
  const selected = select.value;
  select.replaceChildren();
  for (const category of categories.data) {
    const option = document.createElement("option");
    option.value = category.uuid;
    option.textContent = category.titre;
    select.append(option);
  }
  if (selected) select.value = selected;
  const menuSelect = forms.menus.elements.categories;
  const menuSelected = new Set([...menuSelect.selectedOptions].map((option) => option.value));
  menuSelect.replaceChildren();
  for (const category of categories.data) {
    const option = document.createElement("option");
    option.value = category.uuid;
    option.textContent = category.titre;
    option.selected = menuSelected.has(category.uuid);
    menuSelect.append(option);
  }
  afficherListe("categories", categories.data);
  afficherListe("plats", plats.data);
  afficherListe("menus", menus.data);
}

for (const [type, form] of Object.entries(forms)) {
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const fields = form.elements;
    const payload = { titre: fields.titre.value.trim() };
    if (type !== "categories") {
      payload.description = fields.description.value.trim();
      payload.prix = fields.prix.value;
    }
    if (type === "plats") payload.categorie_uuid = fields.categorie_uuid.value;
    if (type === "menus") payload.categories = [...fields.categories.selectedOptions].map((option) => option.value);
    const uuid = fields.uuid.value;
    try {
      await requete(uuid ? `${type}/${encodeURIComponent(uuid)}` : type, uuid ? "PUT" : "POST", payload);
      form.reset();
      fields.uuid.value = "";
      status.textContent = "Modifications enregistrées.";
      await charger();
    } catch (error) { status.textContent = messageUtilisateur(error); }
  });
}

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
