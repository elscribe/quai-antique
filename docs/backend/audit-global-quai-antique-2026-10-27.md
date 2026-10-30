# Audit global Quai Antique — état avant déploiement

Mise à jour : 29 octobre 2026. Cette note remplace la version du 27-28 octobre, dont plusieurs résultats n’étaient plus à jour (nombre de tests, uploads, concurrence, état de `main`). Elle distingue ce qui est **implémenté dans le code** de ce qui est **prouvé par une sortie datée**. Les preuves d’exécution (Docker, MySQL, PHPUnit, profil de production) sont rassemblées dans le dossier de validation finale du 29 octobre, avec la date, le commit et le code de retour de chaque commande. Le déploiement en ligne n’a pas été réalisé.

## État du code

- **Backend Symfony 7.4 / Doctrine.** 8 entités, 6 migrations, contrôleurs d’API pour le restaurant, les catégories, les plats, les menus, la galerie, les réservations et le compte. Commandes d’exploitation : `app:init-restaurant` (idempotente) et `app:create-admin` (mot de passe saisi de façon masquée).
- **Sécurité.** Jeton `X-AUTH-TOKEN` haché en SHA-256, expiration à 12 heures, révocation à la déconnexion et au changement de mot de passe ; mots de passe hachés ; rôle administrateur contrôlé côté serveur ; limiteurs de connexion (5 échecs / 15 min) et d’inscription (5 / heure / IP) ; mot de passe actuel exigé pour changer d’e-mail ou de mot de passe et pour supprimer le compte ; accès aux réservations d’autrui refusé (404).
- **Réservations.** Créneaux de 15 minutes, jours de fermeture, capacité par service, verrou pessimiste MySQL sur le restaurant pour éviter la sur-réservation de la dernière place (désactivé sous SQLite).
- **Documentation d’API.** Nelmio/OpenAPI : Swagger UI sur `/api/doc`, JSON sur `/api/doc.json`, schémas de réponse sur toutes les opérations 200/201. Un test vérifie que chaque route du routeur est documentée.
- **Tests.** 46 méthodes de test PHPUnit / BrowserKit (48 exécutions), sur SQLite en mémoire forcé par `phpunit.dist.xml`.
- **Front.** 13 pages reliées à l’API par `src/http.js`. Voir [RAPPORT-FRONT-ORAL.md](../../RAPPORT-FRONT-ORAL.md).
- **Docker.** `compose.yaml` (projet `quai-antique`, port 82 par défaut) : Nginx (Front et proxy `/api`), PHP-FPM, MySQL 8.4, volume pour les uploads. `compose.production.yaml` (projet `quai-antique-prod-preview`, port 8086) : images sans dépendances de développement, sans tests ni Composer.

## Correspondance avec les cours backend

- **Documenter son API** : attributs OpenAPI dans les contrôleurs, Swagger UI, test de contrat (`ApiDocumentationTest`).
- **Base de données DataFixtures** : fixtures ordonnées par références et groupes (`quai-antique-demo`), enregistrées uniquement en `dev` et `test` (`config/services.yaml`). Le chargement sans `--append` purge la base ciblée : contrôler `DATABASE_URL` avant.
- **Tester une application web** : tests fonctionnels BrowserKit, base SQLite isolée.
- **Sécuriser l’accès aux données** : voir la section Sécurité ci-dessus. Le jeton est conservé côté navigateur dans `sessionStorage` ; aucune donnée de l’API n’est insérée avec `innerHTML`.

Le projet adapte les cours au modèle Quai Antique ; les écarts sont consignés dans les [rapports de la base de données](../database/rapport-ecarts-cours.md) et de [validation des cours backend](rapport-validation-cours-backend.md).

## Ce qui doit être prouvé par une sortie datée

| Point | Commande ou geste |
|---|---|
| Schéma à jour | `doctrine:migrations:status` et `doctrine:schema:validate`, code de retour 0 |
| Tests | `php bin/phpunit --testdox` dans le conteneur |
| Front et API sur la même origine | Onglet Réseau : appels vers `localhost:82/api/…` |
| Persistance des uploads | téléversement, `restart`, `down` sans `-v`, `up` : l’image reste servie |
| Concurrence MySQL | deux réservations simultanées sur la dernière place : 201 et 409 |
| Profil de production | build, `about --env=prod`, `nginx -t`, absence de PHPUnit et de `tests/` dans l’image |

## Avant un déploiement

1. Choisir l’hébergeur, le domaine et la terminaison HTTPS ; ajouter HSTS et une politique CSP.
2. Configurer `trusted_proxies` si un proxy est placé devant Nginx, sinon les limiteurs comptent toutes les requêtes comme venant de la même IP.
3. Injecter les secrets par l’hébergeur ; utiliser un utilisateur MySQL dédié, non root.
4. Au premier déploiement : migrations, `app:init-restaurant`, `app:create-admin`.
5. Prévoir la sauvegarde et la restauration de MySQL et du volume des uploads.
6. Compléter les informations de l’éditeur et de l’hébergeur dans `mentions-legales.html`.
7. Lancer un audit SonarQube et traiter ses alertes.
