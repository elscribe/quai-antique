# Docker local — Front, API et MySQL

Mise à jour : 28 octobre 2026.

## Ce que démarre la stack

- `web` : Nginx sert les fichiers du Front et transmet `/api` à Symfony. Le port hôte par défaut est `82`, publié sur `127.0.0.1` seulement.
- `api` : PHP-FPM 8.5, Symfony 7.4, dépendances de développement incluses pour les tests et les fixtures.
- `database` : MySQL 8.4.13, accessible seulement depuis le réseau Docker. Les données utilisent un volume nommé distinct de la base MySQL déjà utilisée localement sur le port `3307`.
- `uploads_data` : volume partagé ; PHP peut écrire dans `public/uploads`, Nginx peut lire les images publiées.

La stack est un environnement de développement et de validation locale (`APP_ENV=dev`, `APP_DEBUG=1`). Elle n’est pas le profil de production et ne contient aucun secret de production.

## Préparer l’environnement local

1. Copier `.env.docker.example` vers `.env.docker`.
2. Remplacer `APP_SECRET`, `MYSQL_PASSWORD` et `MYSQL_ROOT_PASSWORD` par des valeurs locales aléatoires. La valeur de `MYSQL_PASSWORD` doit correspondre à celle encodée dans `DATABASE_URL`.
3. Garder `WEB_PORT=82` pour utiliser le port Docker du projet. Pour un essai parallèle, choisir un port libre et définir `WEB_PORT` dans `.env.docker`.
4. Ne pas committer `.env.docker` : ce fichier est ignoré par Git.

## Démarrer et initialiser

Depuis la racine du projet :

```sh
docker compose --env-file .env.docker config -q
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker ps
```

Sur une base Docker neuve, appliquer les migrations puis les fixtures de démonstration :

```sh
docker compose --env-file .env.docker exec api php bin/console doctrine:migrations:migrate --no-interaction
docker compose --env-file .env.docker exec api php bin/console doctrine:fixtures:load --no-interaction
```

Le chargement des fixtures purge la base visée. Avant de lancer cette commande, vérifier que l’API cible bien la base Docker `quai_antique_docker` dans `.env.docker`.

## Vérifications utilisées

```sh
docker compose --env-file .env.docker exec api php bin/console doctrine:schema:validate
docker compose --env-file .env.docker exec api php bin/console doctrine:migrations:status
docker compose --env-file .env.docker exec -e APP_ENV=test -e DATABASE_URL=sqlite:///:memory: api php bin/phpunit -c phpunit.dist.xml --colors=never
```

La suite PHPUnit force SQLite en mémoire afin de ne jamais purger la base MySQL Docker. Après l’actualisation de PHPUnit et l’ajout d’un contrôle d’upload à la limite de 3 MiB, le contrôle du 28/10 a obtenu **41 tests et 344 assertions réussis dans le conteneur Docker**.

Le propriétaire a également vérifié dans le navigateur l’accueil, la Carte et la Galerie sur la stack parallèle au port `8086`. Après son accord, cette stack a été basculée sur `82`. Les appels Front vers l’API utilisent alors la même origine ; `http://127.0.0.1:82/api/health` répond 200.

## Arrêt et conservation des données

```sh
docker compose --env-file .env.docker down
```

Cette commande arrête les conteneurs et conserve les volumes. `docker compose down -v` supprime aussi la base et les images téléversées ; ne l’utiliser que si leur effacement est voulu.

## Ce qui reste pour la production

La stack de ce guide est réservée au développement et aux validations locales. L’audit priorisé, les observations techniques et les gates de mise en production sont consignés dans [production-readiness.md](production-readiness.md). Le conteneur local règle PHP à `upload_max_filesize=4M` et `post_max_size=5M`, et Nginx accepte 5m ; le plafond applicatif demeure 3 MiB. Le proxy de l’hébergeur devra appliquer une limite compatible.

Un profil de prévisualisation distinct est défini dans `compose.production.yaml`, avec des Dockerfiles dédiés. Son test local utilise le port 8086, une base neuve et des secrets temporaires locaux. Il force `APP_ENV=prod`, `APP_DEBUG=0` et installe sans dépendances de développement. Voir `production-readiness.md` pour son résultat et ses limites.

Pour rejouer ce profil sur une machine locale, copier `.env.production.example` en `.env.production`, remplacer toutes les valeurs de remplacement par des valeurs locales aléatoires cohérentes, puis lancer :

```sh
docker compose -p quai-antique-prod-preview --env-file .env.production -f compose.production.yaml config -q
docker compose -p quai-antique-prod-preview --env-file .env.production -f compose.production.yaml up -d --build
docker compose -p quai-antique-prod-preview --env-file .env.production -f compose.production.yaml exec api php bin/console doctrine:migrations:migrate --no-interaction --env=prod
docker compose -p quai-antique-prod-preview --env-file .env.production -f compose.production.yaml exec api php bin/console doctrine:schema:validate --env=prod
```

Le site d’aperçu est disponible sur `http://127.0.0.1:8086` par défaut. Pour supprimer uniquement cet environnement de prévisualisation et ses données jetables, utiliser la même commande Compose avec `down -v`. Ne pas utiliser cette commande avec le projet Compose de développement.

Le déploiement lui-même n’a pas été réalisé. Le profil de prévisualisation lie le port au loopback local et n’ajoute pas de TLS ; il ne faut pas l’exposer tel quel. Adapter le réseau, le domaine, HTTPS, les secrets, le stockage et les sauvegardes au fournisseur choisi.
