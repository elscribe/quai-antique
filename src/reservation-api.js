import { fetchJson, messageUtilisateur } from "./http.js";

const form = document.querySelector("#reservation-form");
const slotsContainer = document.querySelector("#availability-slots");
const availabilityStatus = document.querySelector("#availability-status");
const resultMessage = document.querySelector("#form-message");
const submit = document.querySelector("#reservation-submit");
const reservationCaption = document.querySelector(".reservation-caption");
const dateInput = document.querySelector("#date");
const guestInput = document.querySelector("#guests");
const timeInput = document.querySelector("#time");
let currentRequest;
async function chargerDisponibilites() {
  currentRequest?.abort();
  currentRequest = new AbortController();
  slotsContainer.replaceChildren();
  timeInput.value = "";
  if (!dateInput.value || !guestInput.value || !guestInput.checkValidity()) {
    availabilityStatus.textContent = "Choisissez une date et le nombre de couverts.";
    return;
  }
  availabilityStatus.textContent = "Recherche des créneaux…";
  const params = new URLSearchParams({ date: dateInput.value, guests: guestInput.value });
  try {
    const body = await fetchJson(`availability?${params}`, { signal: currentRequest.signal, token: null });
    if (!Array.isArray(body.data)) throw new Error(body.error || "Disponibilité indisponible.");
    for (const slot of body.data) {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "time-slot";
      button.textContent = slot.heure;
      button.disabled = !slot.disponible;
      button.setAttribute("aria-pressed", "false");
      button.addEventListener("click", () => {
        slotsContainer.querySelectorAll("button").forEach((item) => {
          item.classList.remove("is-selected");
          item.setAttribute("aria-pressed", "false");
        });
        button.classList.add("is-selected");
        button.setAttribute("aria-pressed", "true");
        timeInput.value = slot.heure;
      });
      slotsContainer.append(button);
    }
    availabilityStatus.textContent = body.data.some((slot) => slot.disponible)
      ? "Sélectionnez un créneau disponible."
      : "Aucun créneau disponible pour cette date et ce nombre de couverts.";
  } catch (error) {
    if (error.name !== "AbortError") availabilityStatus.textContent = "Impossible de consulter les disponibilités pour le moment.";
  }
}

dateInput.addEventListener("change", chargerDisponibilites);
guestInput.addEventListener("input", chargerDisponibilites);

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  const token = sessionStorage.getItem("quai_api_token");
  if (!dateInput.value || !guestInput.checkValidity() || !timeInput.value) {
    resultMessage.textContent = "Choisissez une date, le nombre de couverts et un créneau disponible.";
    return;
  }
  if (!token) {
    resultMessage.textContent = "Connectez-vous ou créez un compte pour confirmer votre réservation.";
    return;
  }
  const allergies = [...form.querySelectorAll('input[name="allergies"]:checked')].map((item) => item.value);
  const autres = form.elements.autres_allergies.value.trim();
  if (autres) allergies.push(autres);
  submit.disabled = true;
  try {
    await fetchJson("reservations", { method: "POST", body: {
      date: dateInput.value, heure: timeInput.value, nb_convives: Number(guestInput.value),
      allergies: allergies.length ? allergies.join(", ") : null,
    } });
    window.location.assign("espace-client.html?reservation=confirmee");
  } catch (error) {
    if (error.status === 409) await chargerDisponibilites();
    resultMessage.textContent = messageUtilisateur(error, "La réservation n’a pas pu être enregistrée.");
  } finally {
    submit.disabled = false;
  }
});

async function chargerPreferences() {
  const token = sessionStorage.getItem("quai_api_token");
  if (!token) {
    reservationCaption.hidden = false;
    return;
  }
  try {
    const profile = (await fetchJson("account/me")).data;
    reservationCaption.hidden = true;
    sessionStorage.setItem("quai_api_roles", JSON.stringify(profile.roles || []));
    if (profile.nbConvivesDefaut) guestInput.value = profile.nbConvivesDefaut;
    const allergies = (profile.allergies || "").split(",").map((item) => item.trim());
    const known = [...form.querySelectorAll('input[name="allergies"]')].map((item) => item.value);
    form.elements.autres_allergies.value = allergies.filter((item) => item && !known.includes(item)).join(", ");
    form.querySelectorAll('input[name="allergies"]').forEach((item) => {
      item.checked = allergies.includes(item.value);
    });
    if (dateInput.value && guestInput.value) await chargerDisponibilites();
  } catch {
    // Les préférences restent facultatives si le profil est momentanément indisponible.
    reservationCaption.hidden = false;
  }
}

await chargerPreferences();
