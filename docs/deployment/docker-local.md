# Docker local — Front, API et MySQL

Mise à jour : 28 octobre 2026.

## Ce que démarre la stack

- `web` : Nginx sert les fichiers du Front et transmet `/api` à Symfony. Le port hôte par défaut est `82`, publié sur `127.0.0.1` seulement.
- `api` : PHP-FPM 8.5, Symfony 7.4, dépendances de développement incluses pour les tests et les fixtures.
- `database` : MySQL 8.4.13, accessible seulement depuis le réseau Docker. Les données utilisent un volume nommé distinct de la base MySQL déjà utilisée localement sur le port `3307`.
- `uploads_data` : volume partagé ; PHP peut écrire dans `public/uploads`, Nginx peut lire les images publiées.

Le nom Compose du développement est explicitement `quai-antique`, identique à celui de la stack locale actuelle afin de conserver ses volumes existants. Le profil distinct `compose.production.yaml` porte le nom `quai-antique-prod-preview`, donc ses conteneurs, son réseau et ses volumes restent isolés quand les commandes sont lancées sans `-p`. Un `-p` explicite remplace ces noms et doit rester distinct entre les environnements.

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

Sur une base Docker neuve, appliquer les migrations, puis charger les fixtures de démonstration (restaurant, carte, galerie, comptes et une réservation) :

```sh
docker compose --env-file .env.docker exec api php bin/console doctrine:migrations:migrate --no-interaction
docker compose --env-file .env.docker exec api php bin/console doctrine:fixtures:load --group=quai-antique-demo --no-interaction
```

Pour une base sans données de démonstration, remplacer le chargement des fixtures par `app:init-restaurant` (configuration du restaurant) et `app:create-admin` (compte administrateur), comme décrit dans le [README](../../README.md).

Le chargement des fixtures purge la base visée. Avant de lancer cette commande, vérifier que l’API cible bien la base Docker `quai_antique_docker` dans `.env.docker`.

## Vérifications utilisées

```sh
docker compose --env-file .env.docker exec api php bin/console doctrine:schema:validate
docker compose --env-file .env.docker exec api php bin/console doctrine:migrations:status
docker compose --env-file .env.docker exec -e APP_ENV=test -e DATABASE_URL=sqlite:///:memory: api php bin/phpunit -c phpunit.dist.xml --colors=never
```

La suite PHPUnit force SQLite en mémoire (`phpunit.dist.xml`) afin de ne jamais purger la base MySQL Docker. Elle compte 46 méthodes de test, soit 48 exécutions. Le résultat de référence est la sortie brute datée conservée dans le dossier de validation finale.

L’image Front remplace `src/api-config.js` par `docker/api-config.js` : le navigateur appelle l’API sur la même origine que la page (`http://127.0.0.1:82/api/…`), via Nginx. Pour le vérifier, ouvrir l’onglet Réseau des outils de développement : aucun appel ne doit partir vers `127.0.0.1:8000`.

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
docker compose -p quai-antique-prod-preview --env-file .env.production -f compose.production.yaml exec api php bin/console app:init-restaurant --env=prod
docker compose -p quai-antique-prod-preview --env-file .env.production -f compose.production.yaml exec api php bin/console app:create-admin --env=prod --email=adresse@domaine --prenom=Prénom --nom=Nom
```

Les fixtures ne sont pas installées dans l’image de production : la configuration du restaurant et le compte administrateur se créent avec ces deux commandes.

Le site d’aperçu est disponible sur `http://127.0.0.1:8086` par défaut. Pour supprimer uniquement cet environnement de prévisualisation et ses données jetables, utiliser la même commande Compose avec `down -v`. Ne pas utiliser cette commande avec le projet Compose de développement.

Le déploiement lui-même n’a pas été réalisé. Le profil de prévisualisation lie le port au loopback local et n’ajoute pas de TLS ; il ne faut pas l’exposer tel quel. Adapter le réseau, le domaine, HTTPS, les secrets, le stockage et les sauvegardes au fournisseur choisi.
