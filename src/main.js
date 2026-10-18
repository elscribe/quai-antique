const menuToggle = document.querySelector('.menu-toggle');
const navigation = document.querySelector('#main-navigation');

menuToggle?.addEventListener('click', () => {
  const open = navigation.classList.toggle('is-open');
  menuToggle.setAttribute('aria-expanded', String(open));
});

navigation?.addEventListener('click', (event) => {
  if (event.target instanceof HTMLAnchorElement) {
    navigation.classList.remove('is-open');
    menuToggle?.setAttribute('aria-expanded', 'false');
  }
});

const gallery = document.querySelector('#gallery-list');
const galleryDialog = document.querySelector('#gallery-dialog');
gallery?.addEventListener('click', (event) => {
  const card = event.target.closest('.gallery-card');
  if (!card || !galleryDialog) return;
  const image = document.querySelector('#gallery-dialog-image');
  image.src = card.dataset.src;
  image.alt = card.dataset.alt;
  document.querySelector('#gallery-dialog-title').textContent = card.dataset.title;
  galleryDialog.showModal();
});
document.querySelector('.dialog-close')?.addEventListener('click', () => galleryDialog?.close());
galleryDialog?.addEventListener('click', (event) => { if (event.target === galleryDialog) galleryDialog.close(); });

const reservationForm = document.querySelector('#reservation-form');
const formMessage = document.querySelector('#form-message');
const submitButton = document.querySelector('#reservation-submit');
const setError = (field, message) => {
  const input = document.querySelector(`#${field}`);
  const error = document.querySelector(`#${field}-error`);
  input?.setAttribute('aria-invalid', String(Boolean(message)));
  if (error) error.textContent = message;
};

document.querySelectorAll('.time-slot').forEach((slot) => {
  slot.addEventListener('click', () => {
    document.querySelectorAll('.time-slot').forEach((button) => {
      button.classList.remove('is-selected');
      button.setAttribute('aria-pressed', 'false');
    });
    slot.classList.add('is-selected');
    slot.setAttribute('aria-pressed', 'true');
    document.querySelector('#time').value = slot.dataset.time;
    setError('time', '');
  });
});

reservationForm?.addEventListener('submit', (event) => {
  event.preventDefault();
  ['date', 'time', 'guests'].forEach((field) => setError(field, ''));
  const data = Object.fromEntries(new FormData(reservationForm).entries());
  const errors = {};
  if (!data.date) errors.date = 'Choisissez une date.';
  if (!data.time) errors.time = 'Choisissez un créneau.';
  if (!Number.isInteger(Number(data.guests)) || Number(data.guests) < 1 || Number(data.guests) > 6) errors.guests = 'Choisissez le nombre de couverts.';
  Object.entries(errors).forEach(([field, message]) => setError(field, message));
  if (Object.keys(errors).length) {
    formMessage.textContent = 'Vérifiez les champs signalés.';
    formMessage.className = 'form-message form-message--error';
    document.querySelector(`#${Object.keys(errors)[0]}`)?.focus();
    return;
  }
  submitButton.disabled = true;
  submitButton.textContent = 'Envoi en cours…';
  window.setTimeout(() => {
    reservationForm.reset();
    document.querySelectorAll('.time-slot').forEach((button) => {
      button.classList.remove('is-selected');
      button.setAttribute('aria-pressed', 'false');
    });
    formMessage.textContent = 'Créneau enregistré en mode démonstration. La connexion et la disponibilité réelle seront reliées à Symfony.';
    formMessage.className = 'form-message form-message--success';
    submitButton.disabled = false;
    submitButton.textContent = 'Continuer vers la connexion';
  }, 400);
});

document.querySelector('#login-form')?.addEventListener('submit', (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(event.currentTarget).entries());
  const message = document.querySelector('#login-message');
  if (!data.email || !data.password || String(data.password).length < 8) {
    message.textContent = "Indiquez un e-mail et un mot de passe d'au moins 8 caractères.";
    message.className = 'form-message form-message--error';
    return;
  }
  message.textContent = 'Connexion simulée : l’API Symfony sera branchée ensuite.';
  message.className = 'form-message form-message--success';
});
