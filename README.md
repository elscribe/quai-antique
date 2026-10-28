# Quai Antique

Application du restaurant Quai Antique, réalisée dans le cadre du projet fil rouge de formation. Le Front statique et l’API Symfony sont séparés dans le code, puis servis ensemble par la stack Docker locale.

## Architecture

- `src/` : pages et scripts du Front ;
- `back/` : API Symfony 7.4, Doctrine et migrations ;
- `compose.yaml` : stack locale Nginx, PHP-FPM et MySQL ;
- `docs/` : conception, rapports de validation et guides opérationnels.

## Démarrage local avec Docker

Prérequis : Docker Desktop démarré.

1. Copier `.env.docker.example` vers `.env.docker`.
2. Remplacer les valeurs d’exemple par des secrets locaux et faire correspondre `MYSQL_PASSWORD` à la valeur présente dans `DATABASE_URL`.
3. Depuis la racine du dépôt, démarrer la stack :

```sh
docker compose --env-file .env.docker config -q
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker ps
```

Le site est disponible sur [http://localhost:82](http://localhost:82). L’API passe par la même origine, par exemple `/api/health`.

Sur une base Docker neuve, appliquer les migrations :

```sh
docker compose --env-file .env.docker exec api php bin/console doctrine:migrations:migrate --no-interaction
```

Les fixtures de démonstration sont facultatives et purgent la base ciblée. Ne les charger que dans la base Docker locale, jamais dans une base contenant des données à conserver.

Pour arrêter les conteneurs en conservant leurs volumes :

```sh
docker compose --env-file .env.docker down
```

Ne pas utiliser `down -v` sauf si l’effacement de la base et des images téléversées est volontaire. Ne pas committer `.env.docker`.

## Vérifications

Le guide [Docker local](docs/deployment/docker-local.md) décrit les contrôles de configuration, de schéma Doctrine et la suite PHPUnit isolée sur SQLite en mémoire. Le rapport [préparation production](docs/deployment/production-readiness.md) distingue l’état local validé des étapes encore nécessaires avant une publication.

## État

La stack Docker locale sert le Front, l’API Symfony et sa base MySQL Docker dédiée. Elle a été vérifiée sur le port 82 ; la base MySQL locale du port 3307 reste indépendante. Cette configuration utilise le mode développement et ne doit pas être exposée comme une installation de production. Le déploiement en ligne n’a pas été réalisé.
