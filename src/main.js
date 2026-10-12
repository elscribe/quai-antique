const API_BASE_URL = "";

const menuToggle = document.querySelector(".menu-toggle");
const mainNavigation = document.querySelector("#main-navigation");
if (menuToggle && mainNavigation) {
  menuToggle.addEventListener("click", () => {
    const isOpen = mainNavigation.classList.toggle("is-open");
    menuToggle.setAttribute("aria-expanded", String(isOpen));
  });
  mainNavigation.addEventListener("click", (event) => {
    if (event.target instanceof HTMLAnchorElement) {
      mainNavigation.classList.remove("is-open");
      menuToggle.setAttribute("aria-expanded", "false");
    }
  });
}

const galleryItems = [
  { src: "../assets/salle accueil.png", alt: "Salle d'accueil du restaurant", title: "La salle d'accueil" },
  { src: "../assets/salle.png", alt: "Salle du restaurant Quai Antique", title: "Une salle conviviale" },
  { src: "../assets/terrasse.png", alt: "Terrasse du restaurant", title: "La terrasse" },
  { src: "../assets/fondu.png", alt: "Fondue savoyarde", title: "La fondue savoyarde" },
  { src: "../assets/rissoles.png", alt: "Rissoles savoyardes", title: "Les rissoles" },
  { src: "../assets/fromages.png", alt: "Sélection de fromages", title: "Les fromages" },
  { src: "../assets/gateau.png", alt: "Dessert maison", title: "Le dessert maison" },
  { src: "../assets/coktail.png", alt: "Cocktail servi au restaurant", title: "Un cocktail maison" }
];
const menuItems = [
  { name: "Rissoles savoyardes", category: "entree", label: "Entrée", description: "Pâte croustillante, légumes de saison et herbes fraîches." },
  { name: "Assiette de fromages", category: "entree", label: "Entrée", description: "Sélection de fromages des Alpes et confiture maison." },
  { name: "Fondue du Quai", category: "plat", label: "Plat", description: "Fromages savoyards fondus, pain de campagne et salade." },
  { name: "Diots de Savoie", category: "plat", label: "Plat", description: "Diots mijotés, polenta crémeuse et jus au vin blanc." },
  { name: "Dessert aux fruits", category: "dessert", label: "Dessert", description: "Création du moment selon les arrivages de saison." }
];

function renderGallery() {
  const gallery = document.querySelector("#gallery-list");
  if (!gallery) return;
  gallery.replaceChildren(...galleryItems.map((item) => {
    const button = document.createElement("button");
    button.className = "gallery-card"; button.type = "button";
    button.dataset.src = item.src; button.dataset.alt = item.alt; button.dataset.title = item.title;
    const image = document.createElement("img"); image.src = item.src; image.alt = item.alt; image.loading = "lazy";
    const caption = document.createElement("span"); caption.textContent = item.title;
    button.append(image, caption); return button;
  }));
}

function renderMenu(category = "all") {
  const menu = document.querySelector("#menu-list");
  if (!menu) return;
  const items = category === "all" ? menuItems : menuItems.filter((item) => item.category === category);
  menu.replaceChildren(...items.map((item) => {
    const article = document.createElement("article"); article.className = "menu-card";
    const label = document.createElement("p"); label.className = "eyebrow"; label.textContent = item.label;
    const title = document.createElement("h3"); title.textContent = item.name;
    const description = document.createElement("p"); description.textContent = item.description;
    article.append(label, title, description); return article;
  }));
}
document.querySelectorAll(".filter-button").forEach((button) => button.addEventListener("click", () => {
  document.querySelectorAll(".filter-button").forEach((item) => item.classList.remove("is-active"));
  button.classList.add("is-active"); renderMenu(button.dataset.category);
}));

const galleryDialog = document.querySelector("#gallery-dialog");
document.querySelector("#gallery-list")?.addEventListener("click", (event) => {
  const card = event.target.closest(".gallery-card");
  if (!card || !galleryDialog) return;
  const image = document.querySelector("#gallery-dialog-image");
  image.src = card.dataset.src; image.alt = card.dataset.alt;
  document.querySelector("#gallery-dialog-title").textContent = card.dataset.title;
  galleryDialog.showModal();
});
document.querySelector(".dialog-close")?.addEventListener("click", () => galleryDialog.close());
galleryDialog?.addEventListener("click", (event) => { if (event.target === galleryDialog) galleryDialog.close(); });

const reservationForm = document.querySelector("#reservation-form");
const formMessage = document.querySelector("#form-message");
const submitButton = document.querySelector("#reservation-submit");
function setFieldError(name, message) {
  const field = document.querySelector(`#${name}`); const error = document.querySelector(`#${name}-error`);
  if (!field || !error) return;
  field.setAttribute("aria-invalid", message ? "true" : "false"); error.textContent = message;
}
function validateReservation(data) {
  const errors = {};
  if (!data.name.trim()) errors.name = "Indiquez votre nom.";
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) errors.email = "Indiquez une adresse e-mail valide.";
  if (!data.date) errors.date = "Choisissez une date.";
  else if (data.date < new Date().toISOString().slice(0, 10)) errors.date = "La date doit être future.";
  if (!data.time) errors.time = "Choisissez une heure.";
  if (!Number.isInteger(Number(data.guests)) || Number(data.guests) < 1 || Number(data.guests) > 12) errors.guests = "Choisissez entre 1 et 12 couverts.";
  return errors;
}
async function sendReservation(data) {
  if (!API_BASE_URL) { await new Promise((resolve) => window.setTimeout(resolve, 500)); return { demo: true }; }
  const response = await fetch(`${API_BASE_URL}/api/reservations`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(data) });
  if (!response.ok) throw new Error("La réservation n'a pas pu être envoyée.");
  return response.json();
}
reservationForm?.addEventListener("submit", async (event) => {
  event.preventDefault(); document.querySelectorAll(".field-error").forEach((error) => { error.textContent = ""; }); formMessage.textContent = "";
  const data = Object.fromEntries(new FormData(reservationForm).entries()); const errors = validateReservation(data);
  Object.entries(errors).forEach(([name, message]) => setFieldError(name, message));
  if (Object.keys(errors).length) { formMessage.textContent = "Vérifiez les champs signalés."; formMessage.className = "form-message form-message--error"; document.querySelector(`#${Object.keys(errors)[0]}`)?.focus(); return; }
  submitButton.disabled = true; submitButton.textContent = "Envoi en cours…"; formMessage.textContent = "Nous vérifions votre demande…"; formMessage.className = "form-message";
  try { const result = await sendReservation(data); reservationForm.reset(); formMessage.textContent = result.demo ? "Demande validée en mode démonstration. L'API Symfony sera branchée ensuite." : "Votre demande a bien été envoyée."; formMessage.className = "form-message form-message--success"; }
  catch { formMessage.textContent = "Impossible d'envoyer la demande. Réessayez plus tard."; formMessage.className = "form-message form-message--error"; }
  finally { submitButton.disabled = false; submitButton.textContent = "Envoyer ma demande"; }
});
document.querySelector("#login-form")?.addEventListener("submit", (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const data = Object.fromEntries(new FormData(form).entries());
  const message = document.querySelector("#login-message");
  if (!data.email || !data.password || String(data.password).length < 8) {
    message.textContent = "Indiquez un e-mail et un mot de passe d'au moins 8 caractères.";
    message.className = "form-message form-message--error";
    return;
  }
  message.textContent = "Connexion simulée : l'API Symfony sera branchée ensuite.";
  message.className = "form-message form-message--success";
});
renderGallery(); renderMenu();
