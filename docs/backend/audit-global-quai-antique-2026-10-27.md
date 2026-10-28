# Audit global Quai Antique — état avant déploiement

Mise à jour : 28 octobre 2026. Cet audit décrit le dépôt après intégration locale des branches Base de données, CRUD, Sécurité, API complète et Front API. Ces intégrations sont fusionnées dans le `main` distant ; la copie locale `main` doit encore être actualisée. Le déploiement n’a pas été réalisé.

## État de validation

- **Backend Symfony/Doctrine : validé localement.** Six migrations exécutées, aucune en attente ; mapping et schéma MySQL synchronisés. Le conteneur Symfony est valide.
- **API et sécurité : validées par tests automatisés.** PHPUnit : 40 tests et 334 assertions réussis. Les routes, contrôleurs, validation, autorisations, authentification, documentation, fixtures et principaux parcours API sont couverts.
- **Front connecté : implémenté et contrôlé.** Les pages publiques, l’espace client et l’administration consomment l’API. Le dernier test statique des modules JavaScript passe. La navigation du rôle et les redirections sont intégrées.
- **Recette Front : acceptée par le propriétaire du projet le 28/10 après revue du site ; aucun nouveau parcours global n’est requis.** Les parcours visiteur, client et administrateur ont été joués dans l’instance isolée, y compris ajout/suppression de photo et suppression d’une réservation côté admin. Voir [la checklist de recette](../conception/recette-parcours-2026-10-27.md). La suppression de réservation côté client et la confirmation UI de suppression de compte ne sont pas attestées dans le navigateur ; les API et les tests automatisés couvrent ces opérations. Cette limite de preuve n’annule pas l’acceptation générale de la recette Front.
- **Docker : le build du conteneur Front Nginx réussit.** Ce Dockerfile ne lance pas Symfony, et la configuration Nginx ne reverse-proxy pas `/api`. Le stack Docker complet n’est donc pas prêt à exécuter Front + API + MySQL ensemble.
- **SonarQube : audit final non effectué.** Les alertes IDE précédentes sont des recommandations de maintenabilité ; elles ne constituent ni une validation ni un audit de sécurité.

Ces résultats attestent les contrôles effectivement exécutés. Ils ne garantissent ni une note donnée, ni une conformité juridique, ni une certification de sécurité.

## Correspondance avec les cours backend

- **Documenter son API** : Nelmio/OpenAPI expose Swagger UI `/api/doc` et le JSON `/api/doc.json`. Les attributs PHP sont utilisés avec les versions installées. Le test de contrat vérifie la correspondance des opérations entre le routeur Symfony et OpenAPI ; il ne certifie pas à lui seul chaque description métier.
- **Base de données DataFixtures** : fixtures ordonnées par références et groupes. Un chargement a été effectué avec `--append` dans la base de développement, après essai isolé. La commande de chargement sans `--append` peut purger la base ciblée : contrôler `DATABASE_URL` avant toute exécution.
- **Tester une application web** : PHPUnit/BrowserKit utilisent SQLite en mémoire, protégée contre une réinitialisation d’une autre base. Résultat courant : 40 tests, 334 assertions. Les tests répétables complètent, sans remplacer, les vérifications manuelles du navigateur.
- **Sécuriser l’accès aux données** : authentification par jeton, hash de mot de passe, rôle admin contrôlé côté serveur, limiteur de tentatives, expiration et révocation de jeton, validation des entrées. Le navigateur garde le jeton dans `sessionStorage`, accessible à un script injecté ; réévaluer le stockage et renforcer la politique XSS avant production.

Le projet adapte le cours au modèle Quai Antique : un restaurant, des catégories/plats/menus, des réservations, des photos et un compte client/admin. Les exemples du cours qui supposent des écrans ou entités différents ne sont pas copiés à l’identique ; les écarts fonctionnels sont consignés dans les rapports.

## Résultats techniques vérifiés

- `vendor/bin/phpunit --colors=never` : **40 tests, 334 assertions, réussite**.
- `php bin/console doctrine:schema:validate` : mappings corrects et schéma synchronisé.
- `php bin/console lint:container` : réussi.
- `composer validate --no-check-publish`, syntaxe PHP/JavaScript et `git diff --check` : réussis lors de l’audit.
- `docker compose config -q` et `docker compose build --quiet` à la racine : réussis. Ce build ne porte que sur l’image Front Nginx.
- Le test isolé de concurrence MySQL a refusé l’une de deux réservations simultanées sur la dernière place (`201` et `409`) et a préservé les invariants métier. Ce résultat n’est pas une preuve d’une capacité de charge de production.

## Dossier professionnel — preuves encore à consolider

- **Exemple 4 — base relationnelle** : MCD/MLD/MPD, entités, migrations et anciennes captures existent. Les captures Workbench déjà enregistrées ne montrent pas toutes le schéma actuel. Ajouter une capture actuelle des tables/relations et conserver les sorties datées `doctrine:migrations:status`, `doctrine:schema:validate` et une structure SQL comme `SHOW CREATE TABLE menu_categorie`.
- **Exemple 5 — SQL et NoSQL** : Quai Antique illustre Doctrine/SQL. Vite & Gourmand contient une implémentation MongoDB et des agrégations dans un autre projet. Conserver une preuve observable pour chaque technologie et demander confirmation à la formation que deux projets sont recevables dans un seul exemple ; ne pas attribuer MongoDB à Quai Antique.
- **Exemple 6 — composants serveur Symfony** : contrôleurs, services, sécurité et tests sont présents. Le dossier local doit contenir les sorties de tests, des réponses HTTP de succès/refus et le résultat du test de concurrence. La mise en production peut rester en attente, conformément au choix convenu.

La grille n’impose pas un nombre exact de captures ; les preuves doivent démontrer chacune des affirmations. Le dépôt des preuves est un dépôt Git local distinct sans remote : ne pas le pousser.

## Blocages avant déploiement et Docker complet

1. Compléter le compose de production/démo avec Symfony et une base, des healthchecks, secrets d’environnement et volume persistant pour les images.
2. Ajouter et tester le reverse proxy HTTPS de `/api` et `/uploads`; le Nginx courant ne traite que les fichiers Front.
3. Confirmer l’URL publique avec `src/api-config.js`, la configuration CORS, cookies/jetons, HTTPS, en-têtes de sécurité, exposition Swagger, sauvegarde/restauration et stratégie de migrations.
4. Vérifier les coordonnées et mentions légales réelles avant publication.
5. Rejouer les deux actions UI client encore non attestées et exécuter un audit SonarQube complet.

## Historique Git à la date de cette note

Les PR #3 (base de données), #4 (CRUD initial), #5 (sécurité), #6 (API complète) et #7 (Front API) sont fusionnées sur GitHub. La copie locale `main` doit être mise à jour par `git fetch origin` puis `git pull --ff-only origin main`. Le dossier des preuves DP demeure indépendant et local.
