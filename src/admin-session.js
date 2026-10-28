import { apiBaseUrl } from "./api-config.js";

const api = apiBaseUrl;
const tokenKey = "quai_api_token";
const accountName = document.querySelector("#admin-account-name");
const logoutButton = document.querySelector("#admin-logout");
const sessionStatus = document.querySelector("#admin-session-status");

async function chargerCompteAdministrateur() {
  const token = sessionStorage.getItem(tokenKey);
  if (!token) {
    accountName.textContent = "Aucune session active";
    logoutButton.hidden = true;
    return null;
  }

  const response = await fetch(`${api}/account/me`, {
    headers: { "X-AUTH-TOKEN": token },
  });
  if (!response.ok) throw new Error("Session invalide ou expirée.");

  const profile = await response.json();
  if (!profile.data.roles.includes("ROLE_ADMIN")) {
    throw new Error("Cette page est réservée aux administrateurs.");
  }

  accountName.textContent = [profile.data.prenom, profile.data.nom]
    .filter(Boolean)
    .join(" ");
  logoutButton.hidden = false;
}

logoutButton.addEventListener("click", async () => {
  logoutButton.disabled = true;
  sessionStatus.textContent = "Déconnexion en cours…";

  try {
    const token = sessionStorage.getItem(tokenKey);
    const response = await fetch(`${api}/logout`, {
      method: "POST",
      headers: { "X-AUTH-TOKEN": token },
    });
    if (!response.ok && response.status !== 401) {
      throw new Error("Le serveur n’a pas confirmé la déconnexion.");
    }

    sessionStorage.removeItem(tokenKey);
    sessionStorage.removeItem("quai_api_roles");
    window.location.assign("connexion.html");
  } catch (error) {
    sessionStatus.textContent = error.message || "Déconnexion impossible. Réessayez.";
    logoutButton.disabled = false;
  }
});

try {
  await chargerCompteAdministrateur();
} catch (error) {
  accountName.textContent = "Session non vérifiée";
  logoutButton.hidden = true;
  sessionStatus.textContent = error.message;
}
