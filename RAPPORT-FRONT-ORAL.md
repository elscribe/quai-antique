# Front Quai Antique — repères pour l’oral

Mise à jour : 29 octobre 2026. Le Front est un site HTML/CSS/JavaScript multipage de 13 pages, relié à une API Symfony : la carte, la galerie, les disponibilités, le compte client et l’administration passent par des appels HTTP.

## Organisation du code

- Chaque page charge `main.js` (menu, pied de page commun, horaires publics) et, si besoin, son script dédié : `reservation-api.js`, `connexion-api.js`, `espace-client-api.js`, `galerie-api.js`, `catalogue.js` et les scripts `admin-*.js`.
- `http.js` centralise les appels à l’API avec `fetchJson` : il ajoute le jeton `X-AUTH-TOKEN`, transforme chaque code HTTP (401, 403, 404, 409, 413, 429, 5xx, panne réseau, réponse illisible) en message français, et efface la session locale quand le serveur répond 401. La fonction `messageUtilisateur` garantit qu’une erreur de programmation n’est jamais affichée telle quelle : elle part dans la console et l’utilisateur voit un message générique.
- `password.js` applique la même règle de mot de passe que le serveur (`PasswordPolicy.php`) : 8 à 128 octets, une minuscule, une majuscule, un chiffre et un caractère spécial. Le navigateur prévient tôt, le serveur décide.
- `photo-url.js` n’accepte que les images locales sous `/assets/` ou `/uploads/`, avec la même expression régulière que le contrôleur `GalleryPhotoController`. Une URL externe, `javascript:`, `data:` ou un chemin avec `..` est ignoré.
- `api-config.js` choisit l’adresse de l’API. En Docker, l’image remplace ce fichier par `docker/api-config.js`, qui utilise l’origine de la page : Front et API sont servis par le même Nginx.

## Ce que je peux expliquer

- **Rôles.** Les pages publiques présentent le restaurant, la carte et la galerie. L’espace client et l’administration affichent des actions selon le rôle, mais c’est l’API qui vérifie les droits : un client qui appelle une route d’administration reçoit 403, et la réservation d’un autre client renvoie 404.
- **Session.** Le jeton est conservé dans `sessionStorage` : il disparaît à la fermeture de l’onglet et n’est pas envoyé automatiquement comme un cookie. Il reste lisible par un script injecté ; c’est pourquoi aucune donnée de l’API n’est insérée avec `innerHTML` (le DOM est construit avec `textContent` et `createElement`). Côté serveur, le jeton est haché et expire au bout de 12 heures.
- **Formulaires.** Les champs ont des contraintes HTML (`required`, `min`, `type`) pour un premier retour ; le serveur contrôle les données et les règles métier. Changer d’e-mail ou de mot de passe demande le mot de passe actuel ; après un changement de mot de passe, l’utilisateur est renvoyé vers la connexion. Les suppressions sont confirmées avec `window.confirm`.
- **Mise en page.** Pas de Bootstrap, de Sass ni de routeur JavaScript : les mises en page utilisent CSS Grid et Flexbox, avec des points de rupture à 760 et 1100 px. Ce choix d’adaptation aux maquettes reste à faire confirmer par la formation.

## Vérifications du 29 octobre 2026

Contrôles automatisés réalisés sur les 13 pages, avec une API simulée (Playwright) :

- accessibilité axe-core sur 51 rendus (13 pages, états connecté et non connecté, 375, 768 et 1440 px) : aucune violation ;
- aucun débordement horizontal ;
- 150 cas d’erreur simulés (401, 403, 409, 413, 429, 500, 502, panne réseau, réponse HTML ou vide) : aucun message technique affiché ;
- URL d’images piégées (`javascript:`, `data:`, domaine externe, `..`) : aucune affichée ;
- ESLint, html-validate et HTMLHint : 0 erreur.

Ces contrôles portent sur le Front isolé. Les parcours de bout en bout sur la stack Docker sont prouvés séparément, dans le dossier de validation finale.

## Limites connues

- Des écarts visuels subsistent avec Figma sur le tableau de bord, l’espace client et les écrans de gestion. Certaines maquettes contredisent le cahier des charges sur les jours d’ouverture : le cahier des charges fait foi.
- Les messages d’erreur sont affichés de façon globale par formulaire, pas champ par champ.
- La [recette historique](docs/conception/recette-parcours-2026-10-27.md) a été faite sur une base isolée ; la [matrice exigences et preuves](docs/conception/exigences-criteres-preuves.md) indique les tests qui couvrent chaque exigence.

## Formulation pour le jury

« J’ai construit un Front multipage relié à une API Symfony. Un module unique gère les appels HTTP et traduit chaque erreur en message compréhensible. Les formulaires guident la saisie, mais c’est le serveur qui contrôle les données, les droits et la capacité du restaurant. J’ai choisi CSS Grid et Flexbox pour suivre les maquettes, et j’ai vérifié l’accessibilité et le responsive sur toutes les pages avec des tests automatisés. »
