import { fetchJson } from "./http.js";
import { passwordMeetsRequirements } from "./password.js";
const footerStyles = document.createElement("link");
footerStyles.rel = "stylesheet";
footerStyles.href = "footer.css?v=front-audit-2026-10-28";
document.head.append(footerStyles);

const sessionNavigation = document.querySelector("[data-session-nav]");
if (sessionNavigation) {
  const token = sessionStorage.getItem("quai_api_token");
  let cachedRoles = [];
  try {
    const storedRoles = JSON.parse(sessionStorage.getItem("quai_api_roles") || "[]");
    if (Array.isArray(storedRoles)) cachedRoles = storedRoles;
  } catch {
    sessionStorage.removeItem("quai_api_roles");
  }
  const setSessionLink = (roles) => {
    const isAdmin = roles.includes("ROLE_ADMIN");
    sessionNavigation.textContent = isAdmin ? "Tableau de bord" : "Mon espace";
    sessionNavigation.href = isAdmin ? "administration.html" : "espace-client.html";
  };

  if (!token) {
    sessionNavigation.textContent = "Connexion";
    sessionNavigation.href = "connexion.html";
  } else {
    if (cachedRoles.length) setSessionLink(cachedRoles);
    else sessionNavigation.textContent = "Vérification de la session…";
    fetchJson("account/me").then((profile) => {
      const roles = profile.data.roles || [];
      sessionStorage.setItem("quai_api_roles", JSON.stringify(roles));
      setSessionLink(roles);
    }).catch(() => {
      if (!sessionStorage.getItem("quai_api_token")) {
        sessionNavigation.textContent = "Connexion";
        sessionNavigation.href = "connexion.html";
      } else if (!cachedRoles.length) sessionNavigation.textContent = "Session à vérifier";
    });
  }
}

const publicFooterMarkup = `<footer class="site-footer"><div class="footer-brand"><strong>Le Quai Antique</strong><p>Chambéry</p><p>Cuisine gastronomique inspirée par le terroir savoyard et orchestrée avec passion par le Chef Arnaud Michant.</p></div><div class="footer-section"><strong>Plan du site</strong><a href="index.html">Accueil</a><a href="galerie.html">Notre galerie</a><a href="carte.html">La carte &amp; menus</a><a href="reservation.html">Réserver une table</a><a href="contact.html">Contact &amp; accès</a></div><div class="footer-section"><strong>Contact &amp; accès</strong><p>14 Quai de la Rive Gauche<br>73000 Chambéry<br>04 79 12 34 56</p><a href="mailto:contact@lequaiantique.fr">contact@lequaiantique.fr</a><strong>Horaires</strong><p data-public-hours>Horaires momentanément indisponibles. Contactez-nous au 04 79 12 34 56.</p></div><div class="footer-section"><strong>Mentions</strong><a href="mentions-legales.html">Mentions légales</a><a href="confidentialite.html">Confidentialité</a><a href="cgu-cgv.html">CGU / CGV</a></div></footer>`;

// Pied de page public commun : gabarit statique (aucune donnée externe) cloné dans chaque page.
const publicFooter = new DOMParser().parseFromString(publicFooterMarkup, "text/html").body.firstElementChild;
document.querySelectorAll(".site-footer").forEach((footer) => {
  if (!footer.classList.contains("admin-footer"))
    footer.replaceWith(publicFooter.cloneNode(true));
});

const legalDestinations = {
  "Mentions légales": "mentions-legales.html",
  Confidentialité: "confidentialite.html",
  "CGU / CGV": "cgu-cgv.html",
};
document.querySelectorAll(".site-footer a").forEach((link) => {
  const destination = legalDestinations[link.textContent.trim()];
  if (destination) link.setAttribute("href", destination);
});

const menuToggle = document.querySelector(".menu-toggle");
const navigation = document.querySelector("#main-navigation");

menuToggle?.addEventListener("click", () => {
  const open = navigation.classList.toggle("is-open");
  menuToggle.setAttribute("aria-expanded", String(open));
});

navigation?.addEventListener("click", (event) => {
  if (event.target instanceof HTMLAnchorElement) {
    navigation.classList.remove("is-open");
    menuToggle?.setAttribute("aria-expanded", "false");
  }
});

const gallery = document.querySelector("#gallery-list");
const galleryDialog = document.querySelector("#gallery-dialog");
gallery?.addEventListener("click", (event) => {
  const card = event.target.closest(".gallery-card");
  if (!card || !galleryDialog) return;
  // L'image de la modale est créée au premier clic : pas de <img> sans source dans le HTML.
  let image = document.querySelector("#gallery-dialog-image");
  if (!image) {
    image = document.createElement("img");
    image.id = "gallery-dialog-image";
    galleryDialog.querySelector("#gallery-dialog-title").before(image);
  }
  image.src = card.dataset.src;
  image.alt = card.dataset.alt;
  document.querySelector("#gallery-dialog-title").textContent =
    card.dataset.title;
  galleryDialog.showModal();
});
document
  .querySelector(".dialog-close")
  ?.addEventListener("click", () => galleryDialog?.close());
galleryDialog?.addEventListener("click", (event) => {
  if (event.target === galleryDialog) galleryDialog.close();
});

const isValidEmail = (email) => {
  const atIndex = email.indexOf("@");
  const lastDotIndex = email.lastIndexOf(".");

  return (
    atIndex > 0 &&
    atIndex === email.lastIndexOf("@") &&
    lastDotIndex > atIndex + 1 &&
    lastDotIndex < email.length - 1 &&
    !/\s/.test(email)
  );
};
const setAccountFieldValidity = (input, valid) =>
  input?.setAttribute("aria-invalid", String(!valid));

const validateLogin = (form) => {
  const email = form.elements.email;
  const password = form.elements.password;
  const emailValid = isValidEmail(email.value.trim());
  const passwordValid = password.value.length >= 8;
  setAccountFieldValidity(email, emailValid);
  setAccountFieldValidity(password, passwordValid);
  return emailValid && passwordValid;
};

const loginForm = document.querySelector("#login-form");
["email", "password"].forEach((name) =>
  loginForm?.elements[name].addEventListener("input", () =>
    validateLogin(loginForm),
  ),
);

const registrationForm = document.querySelector("#registration-form");
const validateRegistration = (form) => {
  const fields = form.elements;
  const values = {
    firstName: fields.firstName.value.trim(),
    lastName: fields.lastName.value.trim(),
    email: fields.email.value.trim(),
    password: fields.password.value,
    confirmPassword: fields.confirmPassword.value,
  };
  const valid = {
    firstName: Boolean(values.firstName),
    lastName: Boolean(values.lastName),
    email: isValidEmail(values.email),
    password: passwordMeetsRequirements(values.password),
    confirmPassword:
      Boolean(values.confirmPassword) &&
      values.password === values.confirmPassword,
  };
  Object.entries(valid).forEach(([name, isValid]) =>
    setAccountFieldValidity(fields[name], isValid),
  );
  return Object.values(valid).every(Boolean);
};

["firstName", "lastName", "email", "password", "confirmPassword"].forEach(
  (name) => {
    registrationForm?.elements[name].addEventListener("input", () =>
      validateRegistration(registrationForm),
    );
  },
);


const hoursTargets = document.querySelectorAll("[data-public-hours]");
if (hoursTargets.length) {
  fetchJson("restaurant", { token: null }).then(({ data }) => {
    const time = (value) => {
      if (typeof value !== "string" || !/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(value)) throw new Error("Horaires invalides");
      return value.slice(0, 5);
    };
    const hours = `Du mardi au dimanche — Midi : ${time(data.horaire_midi.debut)} – ${time(data.horaire_midi.fin)} ; soir : ${time(data.horaire_soir.debut)} – ${time(data.horaire_soir.fin)}. Fermé le lundi.`;
    hoursTargets.forEach((target) => { target.textContent = hours; });
  }).catch(() => {
    hoursTargets.forEach((target) => { target.textContent = "Horaires momentanément indisponibles. Contactez-nous au 04 79 12 34 56."; });
  });
}
window.addEventListener("quai:session-expired", () => {
  if (sessionNavigation) {
    sessionNavigation.textContent = "Connexion";
    sessionNavigation.href = "connexion.html";
  }
});
