# Quai Antique

Projet du restaurant Quai Antique, réalisé dans le cadre du projet fil
rouge de formation.

## Objectif

Préparer une première interface vitrine pour présenter le restaurant, sa galerie,
sa carte et son parcours de réservation.

## Structure

```text
.
├── src/
│   ├── index.html
│   ├── main.js
│   └── styles.css
├── Dockerfile
├── compose.yaml
└── README.md
```

## Lancer sans Docker

Ouvrir le fichier `src/index.html` dans un navigateur.

## Lancer avec Docker

```bash
docker compose up --build
```

Le site est ensuite disponible à l'adresse :

```text
http://localhost:8080
```

Pour arrêter le conteneur :

```bash
docker compose down
```

## État du projet

La partie Front contient maintenant une interface responsive avec accueil,
galerie interactive, carte filtrable, formulaire de réservation validé en
JavaScript et préparation de l'appel API `POST /api/reservations`.

Le parcours fonctionne en mode démonstration tant que l'API Symfony n'est pas
branchée. La base de données, l'authentification, la validation définitive et
l'enregistrement des réservations appartiennent au projet Back. Le Back Symfony
est en cours : le provider de sécurité cible l'entité `Utilisateur`; le champ
d'empreinte du jeton API est migré; l'inscription, le login et
l'authentification par jeton sont implémentés. Le profil client et les
permissions client/administrateur sont également en place. Postman CLI a validé
16 requêtes et 23 assertions; PHPUnit passe 5 tests et 29 assertions sur SQLite
isolé. Le détail figure dans `docs/security/rapport-tests-postman.md`. Le
provisionnement du premier compte administrateur restera à effectuer par
l'exploitant avant le déploiement.

Le rapport pédagogique et les scénarios de test sont dans
`RAPPORT-FRONT-ORAL.md`.
