const footerStyles = document.createElement("link");
footerStyles.rel = "stylesheet";
footerStyles.href = "footer.css?v=figma-footer-3";
document.head.append(footerStyles);

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

const reservationForm = document.querySelector("#reservation-form");
const formMessage = document.querySelector("#form-message");
const submitButton = document.querySelector("#reservation-submit");
const setError = (field, message) => {
  const input = document.querySelector(`#${field}`);
  const error = document.querySelector(`#${field}-error`);
  input?.setAttribute("aria-invalid", String(Boolean(message)));
  if (error) error.textContent = message;
};

const reservationFields = ["date", "guests"];
const validateReservationField = (field) => {
  const input = document.querySelector(`#${field}`);
  if (!input) return true;
  if (field === "date") {
    const valid = Boolean(input.value);
    setError(field, valid ? "" : "Choisissez une date.");
    return valid;
  }
  const valid =
    Number.isInteger(Number(input.value)) &&
    Number(input.value) >= 1 &&
    Number(input.value) <= 6;
  setError(field, valid ? "" : "Choisissez le nombre de couverts.");
  return valid;
};

reservationFields.forEach((field) => {
  document
    .querySelector(`#${field}`)
    ?.addEventListener("change", () => validateReservationField(field));
});

document.querySelectorAll(".time-slot").forEach((slot) => {
  slot.addEventListener("click", () => {
    document.querySelectorAll(".time-slot").forEach((button) => {
      button.classList.remove("is-selected");
      button.setAttribute("aria-pressed", "false");
    });
    slot.classList.add("is-selected");
    slot.setAttribute("aria-pressed", "true");
    document.querySelector("#time").value = slot.dataset.time;
    setError("time", "");
  });
});

reservationForm?.addEventListener("submit", (event) => {
  event.preventDefault();
  ["date", "time", "guests"].forEach((field) => setError(field, ""));
  const data = Object.fromEntries(new FormData(reservationForm).entries());
  const errors = {};
  if (!data.date) errors.date = "Choisissez une date.";
  if (!data.time) errors.time = "Choisissez un créneau.";
  if (
    !Number.isInteger(Number(data.guests)) ||
    Number(data.guests) < 1 ||
    Number(data.guests) > 6
  )
    errors.guests = "Choisissez le nombre de couverts.";
  Object.entries(errors).forEach(([field, message]) =>
    setError(field, message),
  );
  if (Object.keys(errors).length) {
    formMessage.textContent = "Vérifiez les champs signalés.";
    formMessage.className = "form-message form-message--error";
    document.querySelector(`#${Object.keys(errors)[0]}`)?.focus();
    return;
  }
  submitButton.disabled = true;
  submitButton.textContent = "Envoi en cours…";
  window.setTimeout(() => {
    reservationForm.reset();
    document.querySelectorAll(".time-slot").forEach((button) => {
      button.classList.remove("is-selected");
      button.setAttribute("aria-pressed", "false");
    });
    formMessage.textContent =
      "Créneau enregistré en mode démonstration. La connexion et la disponibilité réelle seront reliées à Symfony.";
    formMessage.className = "form-message form-message--success";
    submitButton.disabled = false;
    submitButton.textContent = "Continuer vers la connexion";
  }, 400);
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

loginForm?.addEventListener("submit", (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const message = document.querySelector("#login-message");
  if (!validateLogin(form)) {
    message.textContent =
      "Indiquez un e-mail et un mot de passe d'au moins 8 caractères.";
    message.className = "form-message form-message--error";
    return;
  }
  message.textContent =
    "Connexion simulée : l’API Symfony sera branchée ensuite.";
  message.className = "form-message form-message--success";
});

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

registrationForm?.addEventListener("submit", (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const message = document.querySelector("#registration-message");
  if (!validateRegistration(form)) {
    message.textContent =
      "Vérifiez vos informations : e-mail valide, mot de passe de 8 caractères avec minuscule, majuscule, chiffre et caractère spécial, puis confirmation identique.";
    message.className = "form-message form-message--error";
    return;
  }
  form.reset();
  message.textContent =
    "Compte simulé créé côté Front. Symfony enregistrera le compte, le mot de passe chiffré et les préférences.";
  message.className = "form-message form-message--success";
});

document.querySelectorAll("[data-demo-action]").forEach((button) => {
  button.addEventListener("click", () => {
    const message = document.querySelector("#admin-message");
    if (!message) return;
    message.textContent =
      "Cette action est préparée côté interface. Symfony contrôlera ensuite les droits et enregistrera les changements.";
  });
});

const editReservationForm = document.querySelector("#edit-reservation-form");
editReservationForm?.addEventListener("submit", (event) => {
  event.preventDefault();
  const message = document.querySelector("#edit-reservation-message");
  message.textContent =
    "Modification vérifiée en mode démonstration. Symfony enregistrera ensuite les données après contrôle des droits.";
  message.className = "form-message form-message--success";
});

const cancelReservationDialog = document.querySelector(
  "#cancel-reservation-dialog",
);
document
  .querySelector("#open-cancel-dialog")
  ?.addEventListener("click", () => cancelReservationDialog?.showModal());
document
  .querySelectorAll("[data-close-dialog]")
  .forEach((button) =>
    button.addEventListener("click", () => cancelReservationDialog?.close()),
  );
document.querySelector("#confirm-cancel")?.addEventListener("click", () => {
  cancelReservationDialog?.close();
  const message = document.querySelector("#edit-reservation-message");
  message.textContent =
    "Annulation simulée : aucune donnée n’a été supprimée. Symfony assurera l’annulation réelle et sa traçabilité.";
  message.className = "form-message form-message--success";
});

const apiBaseUrl = "http://127.0.0.1:8000";

try {
  const response = await fetch(`${apiBaseUrl}/api/health`);

  if (!response.ok) {
    throw new Error(`Erreur HTTP : ${response.status}`);
  }

  const data = await response.json();
  console.log("API Quai Antique :", data);
} catch (error) {
  console.error("Erreur lors de l’appel API :", error);
}
