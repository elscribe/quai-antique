# Quai Antique Front

Projet front-end du restaurant Quai Antique, réalisé dans le cadre du projet fil
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

Cette version contient une page d'accueil statique responsive. Les prochaines
étapes consisteront à compléter les pages galerie, carte, réservation et compte.
