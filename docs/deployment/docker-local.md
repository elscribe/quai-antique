# Docker local — Front, API et MySQL

Mise à jour : 28 octobre 2026.

## Ce que démarre la stack

- `web` : Nginx sert les fichiers du Front et transmet `/api` à Symfony. Le port hôte par défaut est `82`.
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

La suite PHPUnit force SQLite en mémoire afin de ne jamais purger la base MySQL Docker. Le contrôle du 28/10 a obtenu 40 tests et 334 assertions réussis.

Le propriétaire a également vérifié dans le navigateur l’accueil, la Carte et la Galerie sur la stack parallèle au port `8086`. Après son accord, cette stack a été basculée sur `82`. Les appels Front vers l’API utilisent alors la même origine ; `http://127.0.0.1:82/api/health` répond 200.

## Arrêt et conservation des données

```sh
docker compose --env-file .env.docker down
```

Cette commande arrête les conteneurs et conserve les volumes. `docker compose down -v` supprime aussi la base et les images téléversées ; ne l’utiliser que si leur effacement est voulu.

## Ce qui reste pour la production

Avant publication, créer une configuration de production séparée : désactiver le mode debug, exclure MakerBundle/PHPUnit et les autres outils de développement, injecter les secrets depuis l’hébergeur, terminer HTTPS, vérifier les en-têtes de sécurité, puis définir sauvegarde/restauration pour MySQL et les fichiers uploadés. Le déploiement lui-même n’a pas été réalisé.
