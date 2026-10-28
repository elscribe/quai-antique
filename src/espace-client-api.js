import { apiBaseUrl } from "./api-config.js";

const base = apiBaseUrl;
const tokenKey = "quai_api_token";
const status = document.querySelector("#client-status");
const loginLink = document.querySelector("#client-login");
const logoutButton = document.querySelector("#client-logout");
const profilForm = document.querySelector("#client-profile");
const passwordForm = document.querySelector("#client-password");
const deleteForm = document.querySelector("#client-delete");
const reservations = document.querySelector("#client-reservations");
let profil;

async function requete(path, method = "GET", data = null) {
  const token = sessionStorage.getItem(tokenKey);
  if (!token) throw new Error("Connectez-vous pour accéder à votre espace client.");
  const headers = { "X-AUTH-TOKEN": token };
  if (data !== null) headers["Content-Type"] = "application/json";
  const response = await fetch(`${base}${path}`, {
    method, headers, body: data === null ? null : JSON.stringify(data),
  });
  const body = response.status === 204 ? {} : await response.json();
  if (!response.ok) {
    if (response.status === 401) {
      sessionStorage.removeItem(tokenKey);
      sessionStorage.removeItem("quai_api_roles");
    }
    throw new Error(body.error || "La demande n’a pas pu aboutir.");
  }
  return body;
}

function remplirProfil(data) {
  profil = data;
  document.querySelector("#client-welcome").textContent = `Bonjour ${data.prenom}`;
  profilForm.elements.prenom.value = data.prenom;
  profilForm.elements.nom.value = data.nom;
  profilForm.elements.email.value = data.email;
  profilForm.elements.nbConvivesDefaut.value = data.nbConvivesDefaut ?? "";
  profilForm.elements.allergies.value = data.allergies ?? "";
}

function champ(form, label, name, type, value) {
  const wrapper = document.createElement("label");
  wrapper.append(document.createTextNode(label));
  const input = document.createElement("input");
  input.name = name;
  input.type = type;
  input.value = value ?? "";
  input.required = name !== "allergies";
  if (name === "nb_convives") {
    input.min = "1";
    input.max = "65535";
  }
  wrapper.append(input);
  form.append(wrapper);
}

function afficherReservations(items) {
  reservations.replaceChildren();
  if (!items.length) {
    reservations.textContent = "Vous n’avez pas de réservation.";
    return;
  }
  for (const item of items) {
    const article = document.createElement("article");
    article.className = "dashboard-card";
    const title = document.createElement("h3");
    title.textContent = `Réservation du ${item.date} à ${item.heure}`;
    const edit = document.createElement("form");
    edit.className = "account-form";
    champ(edit, "Date", "date", "date", item.date);
    champ(edit, "Heure", "heure", "time", item.heure);
    champ(edit, "Nombre de couverts", "nb_convives", "number", item.nb_convives);
    champ(edit, "Allergies", "allergies", "text", item.allergies);
    const save = document.createElement("button");
    save.type = "submit";
    save.className = "button";
    save.textContent = "Enregistrer";
    const remove = document.createElement("button");
    remove.type = "button";
    remove.className = "button button--outline";
    remove.textContent = "Annuler la réservation";
    edit.append(save, remove);
    edit.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        await requete(`/reservations/${encodeURIComponent(item.uuid)}`, "PUT", {
          date: edit.elements.date.value, heure: edit.elements.heure.value,
          nb_convives: Number(edit.elements.nb_convives.value), allergies: edit.elements.allergies.value || null,
        });
        status.textContent = "Réservation modifiée.";
        await chargerReservations();
      } catch (error) { status.textContent = error.message; }
    });
    remove.addEventListener("click", async () => {
      if (!window.confirm("Annuler cette réservation ?")) return;
      try {
        await requete(`/reservations/${encodeURIComponent(item.uuid)}`, "DELETE");
        status.textContent = "Réservation annulée.";
        await chargerReservations();
      } catch (error) { status.textContent = error.message; }
    });
    article.append(title, edit);
    reservations.append(article);
  }
}

async function chargerReservations() {
  const body = await requete("/reservations");
  afficherReservations(body.data);
}

profilForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  try {
    const fields = profilForm.elements;
    const body = await requete("/account/me", "PUT", {
      prenom: fields.prenom.value.trim(), nom: fields.nom.value.trim(), email: fields.email.value.trim(),
      nbConvivesDefaut: fields.nbConvivesDefaut.value ? Number(fields.nbConvivesDefaut.value) : null,
      allergies: fields.allergies.value.trim() || null,
    });
    remplirProfil(body.data);
    status.textContent = "Vos informations ont été enregistrées.";
  } catch (error) { status.textContent = error.message; }
});

passwordForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (!profil) return;
  try {
    await requete("/account/me", "PUT", {
      prenom: profil.prenom, nom: profil.nom, email: profil.email,
      nbConvivesDefaut: profil.nbConvivesDefaut, allergies: profil.allergies,
      currentPassword: passwordForm.elements.currentPassword.value, password: passwordForm.elements.password.value,
    });
    sessionStorage.removeItem("quai_api_token");
    sessionStorage.removeItem("quai_api_roles");
    passwordForm.reset();
    status.textContent = "Mot de passe modifié. Reconnectez-vous pour poursuivre.";
  } catch (error) { status.textContent = error.message; }
});

deleteForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (!window.confirm("Supprimer définitivement votre compte et toutes vos réservations ?")) return;
  try {
    await requete("/account/me", "DELETE", { currentPassword: deleteForm.elements.currentPassword.value });
    sessionStorage.removeItem("quai_api_token");
    sessionStorage.removeItem("quai_api_roles");
    document.querySelectorAll(".client-section").forEach((section) => { section.hidden = true; });
    status.textContent = "Votre compte et vos réservations ont été supprimés.";
  } catch (error) { status.textContent = error.message; }
});

logoutButton.addEventListener("click", async () => {
  logoutButton.disabled = true;
  try {
    await requete("/logout", "POST");
    sessionStorage.removeItem(tokenKey);
    sessionStorage.removeItem("quai_api_roles");
    window.location.assign("connexion.html?deconnecte=1");
  } catch (error) {
    if (!sessionStorage.getItem(tokenKey)) {
      window.location.assign("connexion.html?deconnecte=1");
      return;
    }
    status.textContent = "La déconnexion n’a pas pu être confirmée. Réessayez.";
    logoutButton.disabled = false;
  }
});

try {
  const body = await requete("/account/me");
  remplirProfil(body.data);
  loginLink.hidden = true;
  logoutButton.hidden = false;
  await chargerReservations();
  if (new URLSearchParams(window.location.search).get("reservation") === "confirmee") {
    status.textContent = "Votre réservation est confirmée et apparaît ci-dessous.";
    window.history.replaceState(null, "", "espace-client.html");
  }
} catch (error) {
  document.querySelectorAll(".client-section").forEach((section) => { section.hidden = true; });
  status.textContent = error.message;
  logoutButton.hidden = true;
  loginLink.hidden = Boolean(sessionStorage.getItem(tokenKey));
}
