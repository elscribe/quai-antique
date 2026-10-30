import { fetchJson } from "./http.js";

const formatPrix = new Intl.NumberFormat("fr-FR", {
  style: "currency",
  currency: "EUR",
});

async function lireCollection(path) {
  const body = await fetchJson(path, { token: null });
  if (!Array.isArray(body.data)) throw new Error("Réponse invalide");
  return body.data;
}

function articleCarte(element) {
  const article = document.createElement("article");
  const titre = document.createElement("h3");
  const prix = document.createElement("b");
  const description = document.createElement("p");
  titre.append(document.createTextNode(element.titre + " "));
  prix.textContent = formatPrix.format(Number(element.prix));
  titre.append(prix);
  description.textContent = element.description;
  article.append(titre, description);
  return article;
}

async function afficherPlats() {
  const status = document.querySelector("#carte-status");
  const container = document.querySelector("#carte-plats");
  try {
    const plats = await lireCollection("plats");
    const categories = new Map();
    for (const plat of plats) {
      const categorie = plat.categorie?.titre ?? "Autres plats";
      if (!categories.has(categorie)) categories.set(categorie, []);
      categories.get(categorie).push(plat);
    }
    for (const [nom, elements] of categories) {
      const section = document.createElement("section");
      const titre = document.createElement("h2");
      titre.textContent = nom;
      section.append(titre, ...elements.map(articleCarte));
      container.append(section);
    }
    status.textContent = plats.length ? "" : "La carte sera bientôt disponible.";
  } catch {
    status.textContent = "La carte est momentanément indisponible.";
  }
}

async function afficherMenus() {
  const status = document.querySelector("#menus-status");
  const container = document.querySelector("#carte-menus");
  try {
    const menus = await lireCollection("menus");
    container.append(...menus.map(articleCarte));
    status.textContent = menus.length ? "" : "Les menus seront bientôt disponibles.";
  } catch {
    status.textContent = "Les menus sont momentanément indisponibles.";
  }
}

await Promise.all([afficherPlats(), afficherMenus()]);
