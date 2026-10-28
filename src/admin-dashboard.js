import { apiBaseUrl } from "./api-config.js";

const api = apiBaseUrl;
const token = sessionStorage.getItem("quai_api_token");
const status = document.querySelector("#admin-status");
const content = document.querySelector("#admin-content");
const loginPrompt = document.querySelector("#admin-login-prompt");
const filter = document.querySelector("#admin-filter");
const list = document.querySelector("#admin-reservations");
const settings = document.querySelector("#restaurant-settings");

async function request(path, method = "GET", data = null) {
  const headers = { "X-AUTH-TOKEN": token };
  if (data !== null) headers["Content-Type"] = "application/json";
  const response = await fetch(`${api}/${path}`, {
    method, headers, body: data === null ? null : JSON.stringify(data),
  });
  const body = response.status === 204 ? {} : await response.json();
  if (!response.ok) throw new Error(body.error || "La demande a échoué.");
  return body;
}

function ajouterChamp(form, label, name, type, value) {
  const wrapper = document.createElement("label");
  wrapper.append(label);
  const input = document.createElement("input");
  input.name = name;
  input.type = type;
  input.value = value ?? "";
  input.required = name !== "allergies";
  if (name === "nb_convives") { input.min = "1"; input.max = "65535"; }
  wrapper.append(input);
  form.append(wrapper);
}

async function chargerReservations() {
  const date = filter.elements.date.value;
  const body = await request(date ? `reservations?date=${encodeURIComponent(date)}` : "reservations");
  list.replaceChildren();
  if (!body.data.length) list.textContent = "Aucune réservation pour cette sélection.";
  for (const item of body.data) {
    const article = document.createElement("article");
    article.className = "dashboard-card";
    const title = document.createElement("h3");
    title.textContent = `${item.client?.prenom ?? ""} ${item.client?.nom ?? ""} — ${item.date} à ${item.heure}`;
    const email = document.createElement("p");
    email.textContent = item.client?.email ?? "";
    const form = document.createElement("form");
    form.className = "account-form";
    ajouterChamp(form, "Date", "date", "date", item.date);
    ajouterChamp(form, "Heure", "heure", "time", item.heure);
    ajouterChamp(form, "Couverts", "nb_convives", "number", item.nb_convives);
    ajouterChamp(form, "Allergies", "allergies", "text", item.allergies);
    const save = document.createElement("button");
    save.type = "submit";
    save.className = "button";
    save.textContent = "Enregistrer";
    const remove = document.createElement("button");
    remove.type = "button";
    remove.className = "button button--outline";
    remove.textContent = "Supprimer";
    form.append(save, remove);
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        await request(`reservations/${encodeURIComponent(item.uuid)}`, "PUT", {
          date: form.elements.date.value, heure: form.elements.heure.value,
          nb_convives: Number(form.elements.nb_convives.value), allergies: form.elements.allergies.value || null,
        });
        status.textContent = "Réservation modifiée.";
        await chargerReservations();
      } catch (error) { status.textContent = error.message; }
    });
    remove.addEventListener("click", async () => {
      if (!window.confirm("Supprimer cette réservation ?")) return;
      try {
        await request(`reservations/${encodeURIComponent(item.uuid)}`, "DELETE");
        status.textContent = "Réservation supprimée.";
        await chargerReservations();
      } catch (error) { status.textContent = error.message; }
    });
    article.append(title, email, form);
    list.append(article);
  }
}

filter.addEventListener("submit", async (event) => {
  event.preventDefault();
  try { await chargerReservations(); } catch (error) { status.textContent = error.message; }
});

settings.addEventListener("submit", async (event) => {
  event.preventDefault();
  const fields = settings.elements;
  try {
    await request("restaurant", "PUT", {
      horaire_midi: { debut: fields.midi_debut.value, fin: fields.midi_fin.value },
      horaire_soir: { debut: fields.soir_debut.value, fin: fields.soir_fin.value },
      capacite_max: Number(fields.capacite_max.value),
    });
    status.textContent = "Horaires et capacité enregistrés.";
  } catch (error) { status.textContent = error.message; }
});

try {
  if (!token) throw new Error("Connectez-vous avec un compte administrateur.");
  const profile = await request("account/me");
  if (!profile.data.roles.includes("ROLE_ADMIN")) throw new Error("Cet espace est réservé aux administrateurs.");
  const restaurant = (await request("restaurant")).data;
  settings.elements.midi_debut.value = restaurant.horaire_midi.debut;
  settings.elements.midi_fin.value = restaurant.horaire_midi.fin;
  settings.elements.soir_debut.value = restaurant.horaire_soir.debut;
  settings.elements.soir_fin.value = restaurant.horaire_soir.fin;
  settings.elements.capacite_max.value = restaurant.capacite_max;
  content.hidden = false;
  loginPrompt.hidden = true;
  await chargerReservations();
} catch (error) {
  content.hidden = true;
  loginPrompt.hidden = false;
  status.textContent = error.message;
}
