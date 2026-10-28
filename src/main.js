const footerStyles = document.createElement("link");
footerStyles.rel = "stylesheet";
footerStyles.href = "footer.css?v=figma-footer-5";
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
    fetch(`${apiBaseUrl}/account/me`, {
      headers: { "X-AUTH-TOKEN": token },
    }).then(async (response) => {
      if (response.status === 401) {
        sessionStorage.removeItem("quai_api_token");
        sessionStorage.removeItem("quai_api_roles");
        sessionNavigation.textContent = "Connexion";
        sessionNavigation.href = "connexion.html";
        return;
      }
      if (!response.ok) throw new Error("Impossible de vérifier la session.");
      const profile = await response.json();
      const roles = profile.data.roles || [];
      sessionStorage.setItem("quai_api_roles", JSON.stringify(roles));
      setSessionLink(roles);
    }).catch(() => {
      if (!cachedRoles.length) sessionNavigation.textContent = "Session à vérifier";
    });
  }
}

const publicFooterMarkup = `<footer class="site-footer"><div class="footer-brand"><strong>Le Quai Antique</strong><p>Chambéry</p><p>Cuisine gastronomique inspirée par le terroir savoyard et orchestrée avec passion par le Chef Arnaud Michant.</p></div><div class="footer-section"><strong>Plan du site</strong><a href="index.html">Accueil</a><a href="galerie.html">Notre galerie</a><a href="carte.html">La carte &amp; menus</a><a href="reservation.html">Réserver une table</a><a href="contact.html">Contact &amp; accès</a></div><div class="footer-section"><strong>Contact &amp; accès</strong><p>14 Quai de la Rive Gauche<br>73000 Chambéry<br>04 79 12 34 56</p></div><div class="footer-section"><strong>Mentions</strong><a href="mentions-legales.html">Mentions légales</a><a href="confidentialite.html">Confidentialité</a><a href="cgu-cgv.html">CGU / CGV</a></div></footer>`;

document.querySelectorAll(".site-footer").forEach((footer) => {
  if (!footer.classList.contains("admin-footer"))
    footer.outerHTML = publicFooterMarkup;
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

const galleryActions = document.querySelector("#gallery-list");
if (galleryActions) {
  galleryActions.insertAdjacentHTML(
    "afterend",
    `<section class="gallery-actions" aria-label="Actions après la galerie"><a class="button button--outline" href="carte.html">Voir la carte</a><a class="button" href="reservation.html">Réserver une table</a></section>`,
  );
}

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
  const image = document.querySelector("#gallery-dialog-image");
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
const passwordMeetsRequirements = (password) =>
  password.length >= 8 &&
  /[a-z]/.test(password) &&
  /[A-Z]/.test(password) &&
  /\d/.test(password) &&
  /[^\w\s]/.test(password);
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
import { apiBaseUrl } from "./api-config.js";
