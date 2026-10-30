import { fetchJson, messageUtilisateur } from "./http.js";

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

  logoutButton.hidden = false;
  const profile = await fetchJson("account/me");
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
    try { await fetchJson("logout", { method: "POST" }); }
    catch (error) { if (error.status !== 401) throw error; }

    sessionStorage.removeItem(tokenKey);
    sessionStorage.removeItem("quai_api_roles");
    window.location.assign("connexion.html");
  } catch (error) {
    sessionStatus.textContent = messageUtilisateur(error, "Déconnexion impossible. Réessayez.");
    logoutButton.disabled = false;
  }
});

try {
  await chargerCompteAdministrateur();
} catch (error) {
  accountName.textContent = "Session non vérifiée";
  logoutButton.hidden = !sessionStorage.getItem(tokenKey);
  sessionStatus.textContent = messageUtilisateur(error);
}
