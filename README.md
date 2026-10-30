# Quai Antique

Application du restaurant Quai Antique, réalisée dans le cadre du projet fil rouge de formation. Le Front statique et l’API Symfony sont séparés dans le code, puis servis ensemble par la stack Docker locale.

## Architecture

- `src/` : pages et scripts du Front ;
- `back/` : API Symfony 7.4, Doctrine et migrations ;
- `compose.yaml` : stack locale Nginx, PHP-FPM et MySQL ;
- `docs/` : conception, rapports de validation et guides opérationnels.

## Démarrage local avec Docker

Prérequis : Docker Desktop démarré.

Pour exécuter Symfony ou Composer directement sur la machine (hors conteneur), PHP 8.4.1 ou supérieur est requis par les dépendances verrouillées. Les conteneurs PHP du projet utilisent PHP 8.5.

1. Copier `.env.docker.example` vers `.env.docker`.
2. Remplacer les valeurs d’exemple par des secrets locaux et faire correspondre `MYSQL_PASSWORD` à la valeur présente dans `DATABASE_URL`.
3. Depuis la racine du dépôt, démarrer la stack :

```sh
docker compose --env-file .env.docker config -q
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker ps
```

Le site est disponible sur [http://localhost:82](http://localhost:82) (port modifiable avec la variable `WEB_PORT`). Nginx sert le Front et transmet `/api` à Symfony sur la même origine : l’image Front remplace `src/api-config.js` par `docker/api-config.js`, qui utilise `window.location.origin`. L’onglet Réseau du navigateur doit donc montrer des appels vers `localhost:82/api/…`, jamais vers `127.0.0.1:8000`.

Sur une base Docker neuve, appliquer les migrations puis initialiser la configuration du restaurant (horaires et capacité) :

```sh
docker compose --env-file .env.docker exec api php bin/console doctrine:migrations:migrate --no-interaction
docker compose --env-file .env.docker exec api php bin/console app:init-restaurant
```

`app:init-restaurant` est idempotente : si le restaurant existe déjà, elle ne modifie rien. Les valeurs par défaut (40 couverts, 12:00–14:00 et 19:00–21:00) se remplacent par les options `--capacite-max`, `--debut-midi`, `--fin-midi`, `--debut-soir` et `--fin-soir`.

Pour créer un compte administrateur, la commande demande le mot de passe de façon masquée (il n’apparaît ni dans l’historique du terminal ni dans la liste des processus) :

```sh
docker compose --env-file .env.docker exec api php bin/console app:create-admin --email=admin@example.test --prenom=Prénom --nom=Nom
```

Les fixtures de démonstration (`doctrine:fixtures:load --group=quai-antique-demo`) sont facultatives et **purgent la base ciblée**. Ne les charger que dans la base Docker locale, jamais dans une base contenant des données à conserver. Elles ne sont pas installées dans l’image de production.

Pour arrêter les conteneurs en conservant leurs volumes :

```sh
docker compose --env-file .env.docker down
```

Ne pas utiliser `down -v` sauf si l’effacement de la base et des images téléversées est volontaire. Ne pas committer `.env.docker`.

## Vérifications

Le guide [Docker local](docs/deployment/docker-local.md) décrit les contrôles de configuration, de schéma Doctrine et la suite PHPUnit isolée sur SQLite en mémoire. Le rapport [préparation production](docs/deployment/production-readiness.md) distingue l’état local validé des étapes encore nécessaires avant une publication.

La [matrice des exigences, critères et preuves](docs/conception/exigences-criteres-preuves.md) relie chaque exigence du cahier des charges au test automatisé ou à la recette qui la couvre.

La suite PHPUnit compte 46 méthodes de test, soit 48 exécutions (le test `UuidGeneratorTest` est joué sur trois jeux de données). Le résultat de référence est la dernière sortie brute datée conservée avec la validation finale, pas un chiffre recopié dans la documentation.

## État

La stack Docker locale sert le Front, l’API Symfony et sa base MySQL Docker dédiée. La base MySQL d’un éventuel serveur local (port 3307) reste indépendante de celle de Docker. Cette configuration utilise le mode développement et ne doit pas être exposée comme une installation de production. Le déploiement en ligne n’a pas été réalisé.
