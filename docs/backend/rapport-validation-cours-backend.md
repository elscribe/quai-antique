# Rapport de validation — documentation API, fixtures et tests

> **Archive du 26 octobre 2026 — ne décrit plus l’état actuel.** Depuis cette date : le travail a été commité et fusionné ; la suite PHPUnit compte 46 méthodes de test (48 exécutions) au lieu de 23 ; le code contient six migrations au lieu de cinq ; les menus sont lus et écrits via Doctrine ; les réservations, la galerie (avec téléversement), l’expiration et la révocation des jetons, les limiteurs de connexion et d’inscription et la commande `app:create-admin` sont implémentés. Pour l’état actuel, consulter [l’audit global actualisé](audit-global-quai-antique-2026-10-27.md).

**Date de vérification :** 26 octobre 2026
**Projet :** Quai Antique — API Symfony 7.4
**Branche :** `feature/back-api-security`
**État Git :** travail local non commité; aucun commit ni push effectué.

## Synthèse

Les trois supports ont été appliqués au projet Quai Antique en reprenant leur démarche et en adaptant le modèle métier existant. Le contrat OpenAPI décrit les 16 opérations actuellement implémentées. Les fixtures de démonstration ont été chargées dans une base SQLite dédiée sous `/tmp`, jamais dans la base de développement MySQL. La suite PHPUnit comporte 23 tests et 177 assertions; elle passe sans erreur, avertissement, dépréciation ni notification PHPUnit.

Ces validations couvrent les fonctionnalités présentes. Elles ne signifient pas que toutes les fonctions prévues dans le cahier des charges sont terminées ni que l’application est prête à être mise en production.

## 1. Cours « Documenter son API » — validé pour les routes présentes

### Mise en œuvre

- Nelmio expose publiquement Swagger UI sur `/api/doc` et le document JSON OpenAPI sur `/api/doc.json`.
- Le contrat est produit avec les attributs PHP OpenAPI compatibles avec les versions installées (NelmioApiDocBundle 5.13 et swagger-php 6). Le support pédagogique emploie des annotations de commentaire; les deux formes décrivent OpenAPI, mais les attributs sont adaptés aux dépendances actuelles.
- Les 16 opérations actuellement disponibles ont un résumé, des réponses documentées et, selon le cas, des paramètres de chemin, des corps JSON et des en-têtes `Location`.
- Le schéma de sécurité `ApiToken` décrit l’en-tête `X-AUTH-TOKEN`. Les opérations protégées héritent de ce schéma; les lectures publiques, l’inscription, la connexion et le health check le désactivent explicitement.
- Les réponses et champs décrits sont ceux des contrôleurs actifs : catégories, plats, menus, inscription, connexion, profil et health check.

### Validation effectuée

- `php bin/console nelmio:apidoc:dump --format=json` a produit un OpenAPI 3.0.0 valide, avec 9 chemins et 16 opérations, des résumés et des codes de réponse spécifiques.
- Le test BrowserKit vérifie la présence de Swagger UI, le contrat JSON, le schéma de sécurité, les routes publiques et protégées, le corps de la requête d’inscription et `Location` sur la création d’une catégorie.
- Swagger UI a été utilisée de bout en bout dans le navigateur : `GET /api/health` a répondu `200`; `POST /api/registration` a répondu `201`; après saisie du jeton dans « Authorize », `GET /api/account/me` a répondu `200` avec le profil expurgé des secrets.
- Le compte de démonstration temporaire a ensuite été supprimé de MySQL (une ligne supprimée) et l’autorisation contenant le jeton a été retirée de la page Swagger. Les essais d’écriture du catalogue restent couverts par PHPUnit.

**Statut :** la partie du cours « Pour aller plus loin » liée aux routes de modification/suppression, aux erreurs, au profil et à l’authentification est décrite pour les routes réellement présentes. La documentation ne prétend pas qu’une route inexistante existe.

## 2. Cours « Base de données DataFixtures » — validé pour le jeu de démonstration

### Mise en œuvre

Les dépendances de développement Doctrine Fixtures et Faker sont installées. Les fixtures sont réparties par entité et reliées par références Doctrine :

- 1 restaurant Quai Antique;
- 2 comptes locaux de démonstration (client et administrateur);
- 4 catégories et 5 plats;
- 3 menus avec 9 liens `menu_categorie`;
- 3 photos;
- 1 réservation future.

Les dépendances des fixtures expriment l’ordre nécessaire (par exemple, les catégories avant les plats et menus; le restaurant et l’utilisateur avant la réservation). Faker est configuré en français avec une graine pour rendre les titres d’images reproductibles.

### Validation effectuée

Une base SQLite neuve, `/tmp/quai-antique-fixtures-audit-20261026.sqlite`, a été créée avec le schéma Doctrine, puis chargée avec le groupe `quai-antique-demo`. Les volumes vérifiés après chargement sont : `restaurant=1`, `utilisateur=2`, `categorie=4`, `plat=5`, `menu=3`, `menu_categorie=9`, `photo=3`, `reservation=1`.

**Précaution :** `doctrine:fixtures:load` purge la base cible avant de charger les données. L’essai a utilisé exclusivement la base SQLite dédiée ci-dessus; aucune fixture n’a été chargée dans MySQL de développement. Les comptes et mots de passe inclus dans les fixtures sont uniquement des données de démonstration locales et ne sont pas des comptes de production.

### Mise à jour des fixtures — 28 octobre 2026

Le relevé de volumes du 26 octobre ci-dessus est historique et ne correspond plus aux fixtures actuelles. `ReservationFixtures` utilise désormais une date calculée au prochain mardi à 19 h 30, cohérente avec les jours d’ouverture du restaurant; la date et l’heure proviennent du même objet date. La fixture vide `AppFixtures`, générée par la recette Symfony et sans rôle dans le jeu de démonstration, a été supprimée.

Le jeu `quai-antique-demo` a été rechargé sur une base SQLite temporaire dédiée le 28 octobre. Le contrôle observé est : `restaurant=1`, `utilisateur=2`, `categorie=5`, `plat=11`, `menu=3`, `menu_categorie=9`, `photo=9`, `reservation=1`. La réservation obtenue est datée du `2026-11-03` à `19:30` (mardi). Cette vérification n’a touché ni MySQL ni les volumes Docker actifs.

## 3. Cours « Tester application web » — validé pour la couverture implémentée

### Mise en œuvre

La suite utilise PHPUnit, `WebTestCase`/BrowserKit pour simuler des requêtes HTTP, des tests unitaires pour l’authentificateur et le générateur UUID, ainsi qu’un mock du repository afin de vérifier que le jeton brut est transformé en empreinte avant la recherche.

Les tests fonctionnels réinitialisent le schéma via Doctrine SchemaTool uniquement après avoir vérifié que la connexion de test est SQLite en mémoire. Le client empêche le redémarrage du noyau entre les requêtes d’un même test afin que cette base en mémoire soit conservée. Un lancement sur une autre base est refusé avant toute opération destructive.

### Couverture

- documentation publique Swagger UI et contrat OpenAPI;
- health check et réponse de liste de menus;
- CRUD Catégorie et Plat : codes HTTP, corps, `Location`, relations sérialisées, validation, droits, éléments introuvables et suppression d’une catégorie liée;
- inscription : JSON mal formé, données invalides, email en doublon, hash du mot de passe, rôle côté serveur et émission d’un jeton dont seule l’empreinte est stockée;
- connexion : mauvais mot de passe, renouvellement du jeton, refus de l’ancien jeton et acceptation du nouveau;
- profil : accès protégé, champs sensibles masqués, modification des champs permis, mot de passe haché, date de modification et refus d’une tentative d’auto-attribution de `ROLE_ADMIN`;
- authentificateur API : présence/absence du header, hash du jeton avant accès repository, jeton inconnu;
- format UUID v4 avec test piloté par un DataProvider.

### Résultats

Commande : `php bin/phpunit --no-progress --colors=never --display-all-issues`

Résultat final : **OK — 23 tests, 177 assertions**. Aucun avertissement, aucune dépréciation et aucune notification PHPUnit.

## Vérifications techniques globales

- `composer validate --no-check-publish` : réussi.
- `composer audit --no-interaction` : aucun avis de vulnérabilité connu pour les dépendances verrouillées au moment du contrôle.
- `php -l` sur tous les fichiers PHP de `src/` et `tests/` : réussi.
- `php bin/console lint:yaml config --env=test` : 15 fichiers YAML valides.
- `php bin/console lint:container --env=test` : conteneur valide.
- `php bin/console doctrine:schema:validate` : mappings corrects, schéma MySQL synchronisé.
- `php bin/console doctrine:migrations:status` : 5 migrations exécutées, 0 nouvelle, à la dernière version.
- `git diff --check` : réussi.

## SonarQube pour IDE

Le panneau VS Code affiche encore **6 problèmes**, tous classés comme maintenabilité : quatre suggestions `php:S1066` sur des `if` imbriqués dans `Restaurant.php` et `Utilisateur.php`, puis deux `php:S1142` sur le nombre de retours de `register()` et `edit()` dans `SecurityController.php`. Ce ne sont pas des échecs de syntaxe ou des alertes de sécurité. Les `S1066` apparaissent toutefois dans les méthodes générées qui retirent des enfants d’une collection et affectent `null` à une relation obligatoire; ces méthodes ne doivent pas être utilisées pour détacher des données encore persistées. Les six alertes n’ont pas empêché les tests; elles restent à revoir séparément pour décider si une simplification améliore réellement la lecture sans changer la logique métier.

## Limites produit et préparation au déploiement

Les cours sont validés au regard des fonctionnalités déjà implémentées. Le projet complet n’est pas encore prêt à être déployé sans réserve :

- la lecture des menus est actuellement une réponse de démonstration codée dans le contrôleur; elle n’est pas encore lue depuis Doctrine;
- les opérations d’écriture des menus, les routes de réservation et la gestion complète des photos ne sont pas encore implémentées;
- l’inscription publique crée uniquement des clients `ROLE_USER`; le compte `ROLE_ADMIN` doit être provisionné par une procédure d’exploitation privée avant de déployer les fonctions d’administration;
- les paramètres de CORS, secrets, URL de base de données, transport HTTPS, migrations en production et stratégie d’exposition de Swagger doivent être décidés pour l’hébergement réel.

Ces sujets relèvent des fonctions produit et du déploiement. Ils ne sont pas couverts par les trois cours validés ici. La politique d’expiration et de révocation des jetons, la limitation des tentatives de connexion et des règles de longueur minimale/maximale pour les données d’inscription doivent aussi être définies avant la mise en production. Il reste aussi à examiner les six points Sonar avant de considérer le contrôle de qualité entièrement terminé.

## Étapes pour rejouer les validations

```sh
cd back
php bin/phpunit --no-progress --colors=never --display-all-issues
php bin/console nelmio:apidoc:dump --format=json
php bin/console doctrine:schema:validate
php bin/console doctrine:migrations:status
```

Pour les fixtures, fournir explicitement une base SQLite jetable neuve avec `DATABASE_URL`, créer son schéma, puis seulement exécuter `doctrine:fixtures:load --group=quai-antique-demo --no-interaction`. Ne pas lancer cette commande avec la `DATABASE_URL` de développement ou de production.

## Sources de cours et documentation

- Supports Studi consultés : *Documenter son API*, *Base de données DataFixtures* et *Tester application web* (dossier de cours Backend).
- [Documentation officielle DoctrineFixturesBundle](https://symfony.com/bundles/DoctrineFixturesBundle/current/index.html).
- [Documentation officielle Symfony — Tests](https://symfony.com/doc/current/testing.html).
- [NelmioApiDocBundle](https://github.com/nelmio/NelmioApiDocBundle) et [swagger-php](https://github.com/zircote/swagger-php).
