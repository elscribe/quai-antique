# Quai Antique — Rapport de réalisation Front

## Périmètre

Cette version réalise la partie visible et interactive du Front du projet fil rouge : interface responsive, galerie, carte filtrable, formulaire de réservation, validation JavaScript et préparation de l'appel API. La base de données, la validation définitive, l'authentification et l'enregistrement restent dans le Back Symfony.

## Correspondance avec les cours du profil rouge

| Cours | Réalisation dans le projet |
|---|---|
| Réaliser l'interface utilisateur | hiérarchie visuelle, navigation, composants, labels, messages, responsive et accessibilité de base |
| Partie Front Responsive | grilles adaptatives, menu mobile, champs utilisables sur téléphone, tests de largeur |
| Dynamiser partie Front | menu mobile, galerie modale, filtre de carte, rendu de contenu par JavaScript, validation du formulaire |
| Appeler API depuis le front | fonction `sendReservation`, `fetch` POST préparé vers `/api/reservations`, états chargement/succès/erreur |
| Recommandations sécurité front | pas de secret dans le navigateur, `textContent` pour les messages, validation serveur attendue, pas de confiance dans les données client |
| Mettre en ligne partie front application | Docker/Nginx conservé et vérifiable avec `docker compose up --build` |

## Étapes de code

### Étape 1 — Structurer l'interface

`index.html` contient maintenant les zones accueil, galerie, carte, réservation et compte. Chaque zone possède un titre accessible et une navigation claire. Les images locales du dossier `assets` sont utilisées à la place des images génériques.

### Étape 2 — Rendre l'interface responsive

`styles.css` utilise une grille flexible et deux seuils responsive. Sur mobile, la navigation devient un bouton, les cartes passent en une colonne et le formulaire reste lisible sans défilement horizontal.

### Étape 3 — Dynamiser la galerie

`main.js` construit les cartes à partir de `galleryItems`. Un clic ouvre une boîte de dialogue native `dialog`. Le contenu est injecté avec `textContent` et `createElement`, pas avec `innerHTML`.

### Étape 4 — Dynamiser la carte

Les plats sont regroupés dans `menuItems`. Les boutons « Tout », « Entrées », « Plats » et « Desserts » filtrent les données et reconstruisent uniquement les cartes correspondantes.

### Étape 5 — Construire le formulaire

Le formulaire demande le nom, l'e-mail, la date, l'heure, le nombre de couverts et une demande facultative. Chaque champ obligatoire possède un label et une zone d'erreur associée.

### Étape 6 — Valider côté navigateur

`validateReservation` contrôle les champs avant l'envoi. Le formulaire signale les erreurs, positionne le focus sur le premier champ incorrect et conserve les messages lisibles par lecteur d'écran.

### Étape 7 — Préparer l'API

`sendReservation` fonctionne actuellement en mode démonstration pour permettre de tester le parcours sans Back. Quand Symfony sera disponible, il suffira de renseigner `API_BASE_URL` et de connecter la route POST `/api/reservations`. La validation Back restera obligatoire.

## Scénarios de test manuel

| ID | Test | Résultat attendu |
|---|---|---|
| T01 | Ouvrir la page sur ordinateur | toutes les sections et images s'affichent |
| T02 | Réduire la fenêtre sous 860 px | le bouton Menu apparaît et la navigation s'ouvre/se ferme |
| T03 | Cliquer une image de galerie | la boîte de dialogue affiche l'image et son titre |
| T04 | Filtrer la carte par « Plats » | seuls les plats sont affichés |
| T05 | Envoyer le formulaire vide | erreurs visibles, focus sur le nom, aucun envoi |
| T06 | Saisir un e-mail invalide | message d'erreur sur l'e-mail |
| T07 | Saisir une date passée | message d'erreur sur la date |
| T08 | Saisir 0 ou 13 couverts | message d'erreur sur le nombre |
| T09 | Envoyer des données valides | état de chargement puis message de démonstration |
| T10 | Naviguer au clavier | lien d'accès rapide, focus et boutons utilisables |
| T11 | Lancer Docker | la page est servie sur le port défini par `compose.yaml` |
| T12 | Brancher l'API et provoquer une erreur | message générique affiché, détails techniques non exposés |

## Explication orale courte

« J'ai commencé par structurer les besoins du restaurant en sections visibles : accueil, galerie, carte, réservation et compte. J'ai ensuite appliqué une mise en page responsive pour les écrans mobiles et desktop. J'ai utilisé JavaScript pour rendre la galerie interactive, filtrer la carte et valider le formulaire. Le formulaire transforme les données avec `FormData`, affiche les erreurs côté navigateur et prépare un appel `fetch` en POST. La validation côté Front améliore l'expérience, mais le Back devra refaire ses propres contrôles avant d'enregistrer la réservation. Enfin, j'ai prévu des tests manuels pour les cas valides, les erreurs, le responsive, l'accessibilité et le déploiement Docker. »

## Limites à annoncer au jury

- Le mode démonstration ne crée pas de réservation réelle.
- L'API Symfony et la base de données sont nécessaires pour la confirmation définitive.
- L'authentification et les droits d'administration appartiennent au Back.
- Il faut produire les captures et compléter le rapport de tests après exécution réelle dans le navigateur.
