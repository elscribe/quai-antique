import { apiBaseUrl } from "./api-config.js";

export function purgeSession() {
  sessionStorage.removeItem("quai_api_token");
  sessionStorage.removeItem("quai_api_roles");
  window.dispatchEvent(new Event("quai:session-expired"));
}

// Message affichable à l'utilisateur. Les erreurs créées par fetchJson (ou levées volontairement
// avec un texte français) sont affichées telles quelles ; une erreur de programmation
// (TypeError, SyntaxError…) n'est jamais montrée : elle part dans la console.
export function messageUtilisateur(error, repli = "Une erreur inattendue est survenue. Actualisez la page puis réessayez.") {
  if (!(error instanceof Error) || error instanceof TypeError || error instanceof SyntaxError
      || error instanceof RangeError || error instanceof ReferenceError) {
    // eslint-disable-next-line no-console -- trace technique gardée pour le débogage, jamais affichée à l’utilisateur
    console.error(error);
    return repli;
  }
  return error.message || repli;
}

export async function fetchJson(path, { method = "GET", body = null, signal, token = sessionStorage.getItem("quai_api_token") } = {}) {
  const headers = { Accept: "application/json" };
  if (token) headers["X-AUTH-TOKEN"] = token;
  if (body !== null && !(body instanceof FormData)) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(body);
  }
  let response;
  try {
    response = await fetch(`${apiBaseUrl}/${path.replace(/^\//, "")}`, { method, headers, body, signal });
  } catch (error) {
    if (error.name === "AbortError") throw error;
    throw new Error("Connexion au serveur impossible. Vérifiez votre connexion et réessayez.");
  }
  if (response.status === 401 && token) purgeSession();
  if (!response.ok) {
    const messages = {
      401: "Session invalide ou expirée. Reconnectez-vous.",
      403: "Vous n’avez pas l’autorisation d’effectuer cette action.",
      404: "La ressource demandée est introuvable.",
      409: "Cette action est en conflit avec les données actuelles. Actualisez puis réessayez.",
      413: "Le fichier envoyé est trop volumineux.",
      429: "Trop de demandes. Patientez avant de réessayer.",
    };
    let message = messages[response.status] || (response.status >= 500
      ? "Le serveur est momentanément indisponible. Réessayez plus tard."
      : "Vérifiez les informations saisies puis réessayez.");
    try {
      const data = await response.clone().json();
      if (typeof data.error === "string" && data.error.length < 500
          && !/[<>]|SQLSTATE|Exception|Unexpected token|Failed to fetch/i.test(data.error)) message = data.error;
    } catch { /* Une réponse HTML ou vide garde le message français de repli. */ }
    const error = new Error(message);
    error.status = response.status;
    throw error;
  }
  if (response.status === 204) return {};
  try { return await response.json(); }
  catch { throw new Error("La réponse du serveur est illisible. Réessayez plus tard."); }
}
