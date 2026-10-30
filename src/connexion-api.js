import { passwordMeetsRequirements as motDePasseValide } from "./password.js";
import { fetchJson, messageUtilisateur } from "./http.js";

const tokenKey = "quai_api_token";
const login = document.querySelector("#login-form");
const registration = document.querySelector("#registration-form");
const logout = document.querySelector("#logout-button");
const loginMessage = document.querySelector("#login-message");
const registrationMessage = document.querySelector("#registration-message");
const accountLinks = document.querySelector("#account-links");
const adminLink = document.querySelector("#admin-link");

function message(element, content, error = false) {
  element.textContent = content;
  element.className = error ? "form-message form-message--error" : "form-message form-message--success";
}



async function post(path, payload, token = null) {
  return fetchJson(path, { method: "POST", body: payload, token });
}

function garderSession(body) {
  if (typeof body.apiToken !== "string" || !body.data) throw new Error("Réponse de connexion invalide.");
  sessionStorage.setItem(tokenKey, body.apiToken);
  sessionStorage.setItem("quai_api_roles", JSON.stringify(body.data.roles || []));
  logout.hidden = false;
  accountLinks.hidden = false;
  adminLink.hidden = !body.data.roles?.includes("ROLE_ADMIN");
}

function redirigerApresConnexion(roles = []) {
  const destinationDemandee = new URLSearchParams(window.location.search).get("redirect");
  const destinationsAutorisees = ["administration.html", "espace-client.html", "reservation.html"];
  if (destinationsAutorisees.includes(destinationDemandee)) {
    window.location.assign(destinationDemandee);
    return;
  }

  window.location.assign(
    roles.includes("ROLE_ADMIN") ? "administration.html" : "espace-client.html",
  );
}

login?.addEventListener("submit", async (event) => {
  event.preventDefault();
  const email = login.elements.email.value.trim();
  const password = login.elements.password.value;
  if (!login.elements.email.checkValidity() || !password) {
    message(loginMessage, "Indiquez une adresse e-mail et votre mot de passe.", true);
    return;
  }
  const button = login.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    const body = await post("login", { email, password });
    garderSession(body);
    login.elements.password.value = "";
    message(loginMessage, `Vous êtes connecté(e), ${body.data.prenom}.`);
    redirigerApresConnexion(body.data.roles);
  } catch (error) {
    message(loginMessage, messageUtilisateur(error, "Connexion impossible."), true);
  } finally {
    button.disabled = false;
  }
});

registration?.addEventListener("submit", async (event) => {
  event.preventDefault();
  const fields = registration.elements;
  if (!fields.firstName.value.trim() || !fields.lastName.value.trim()
      || !fields.email.checkValidity() || !motDePasseValide(fields.password.value)
      || fields.password.value !== fields.confirmPassword.value) {
    message(registrationMessage, "Vérifiez les champs et la confirmation du mot de passe.", true);
    return;
  }
  const button = registration.querySelector('button[type="submit"]');
  button.disabled = true;
  try {
    const allergies = [...registration.querySelectorAll('input[name="allergies"]:checked')].map((item) => item.value);
    const autres = fields.otherAllergies.value.trim();
    if (autres) allergies.push(autres);
    const body = await post("registration", {
      prenom: fields.firstName.value.trim(), nom: fields.lastName.value.trim(),
      email: fields.email.value.trim(), password: fields.password.value,
      nbConvivesDefaut: Number(fields.defaultGuests.value),
      allergies: allergies.length ? allergies.join(", ") : null,
    });
    garderSession(body);
    registration.reset();
    message(registrationMessage, `Votre compte est créé, ${body.data.prenom}. Vous êtes connecté(e).`);
    redirigerApresConnexion(body.data.roles);
  } catch (error) {
    message(registrationMessage, messageUtilisateur(error, "Inscription impossible."), true);
  } finally {
    button.disabled = false;
  }
});

logout.hidden = !sessionStorage.getItem(tokenKey);
logout.addEventListener("click", async () => {
  const token = sessionStorage.getItem(tokenKey);
  try {
    if (token) await post("logout", null, token);
    message(loginMessage, "Vous êtes déconnecté(e).");
  } catch {
    message(loginMessage, "Session locale effacée ; la déconnexion du serveur n’a pas pu être confirmée.", true);
  } finally {
    sessionStorage.removeItem(tokenKey);
    sessionStorage.removeItem("quai_api_roles");
    logout.hidden = true;
    accountLinks.hidden = true;
    adminLink.hidden = true;
  }
});

const parametres = new URLSearchParams(window.location.search);
if (parametres.get("deconnecte") === "1") {
  message(loginMessage, "Vous êtes déconnecté(e).");
} else if (parametres.get("motdepasse") === "modifie") {
  message(loginMessage, "Votre mot de passe a été modifié. Connectez-vous avec le nouveau mot de passe.");
}

const existingToken = sessionStorage.getItem(tokenKey);
if (existingToken) {
  try {
    const body = await fetchJson("account/me");
    sessionStorage.setItem("quai_api_roles", JSON.stringify(body.data.roles || []));
    accountLinks.hidden = false;
    adminLink.hidden = !body.data.roles?.includes("ROLE_ADMIN");
    redirigerApresConnexion(body.data.roles);
  } catch (error) {
    message(loginMessage, messageUtilisateur(error, "Impossible de vérifier la session pour le moment."), true);
  }
}
