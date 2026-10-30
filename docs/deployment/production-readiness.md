# État de préparation à la production

> **Lecture au 29 octobre 2026.** Les essais décrits ci-dessous ont été rapportés le 28 octobre pour l’aperçu local ; ils ne constituent pas une preuve de déploiement. Les chiffres de tests cités (41 tests / 344 assertions) datent de ce contrôle : la suite compte aujourd’hui 46 méthodes de test (48 exécutions). Les sorties de référence, datées et liées au commit, sont dans le dossier de validation finale.

Date du contrôle initial : 28 octobre 2026. Cet audit décrit la préparation locale du projet. Il ne constitue pas une validation d’un hébergeur ni un déploiement.

## Résumé

La stack Docker locale de développement est opérationnelle. Un profil Docker de production distinct est maintenant construit et testé en local sur une base jetable, avec Symfony en production et sans dépendances de développement. **Cela ne constitue pas un déploiement** : aucun domaine, TLS, gestionnaire de secrets ni plan de sauvegarde/restauration d’un hébergeur réel n’a été configuré.

## Éléments vérifiés

- `docker compose --env-file .env.docker config -q` réussit ; les services `web`, `api` et `database` sont actifs et sains.
- Nginx passe `nginx -t` ; `/api/health` répond HTTP 200 sur le port local 82.
- La configuration limite l’exposition de la stack locale au loopback `127.0.0.1:82`, au lieu de toutes les interfaces réseau. La publication Internet reste à configurer séparément chez l’hébergeur.
- Le 28 octobre, la suite PHPUnit a été relancée dans une copie temporaire avec SQLite en mémoire puis dans l’image Docker de développement reconstruite : 41 tests et 344 assertions réussis à cette date. Le test ajouté envoie une image de 3 MiB à l’API, vérifie la réponse de succès et supprime immédiatement le fichier à nom aléatoire. Aucun test ne modifie la base MySQL de développement ; les fichiers de test ont été supprimés après usage.
- `composer audit --locked` exécuté dans `back/` signalait un avis sur PHPUnit 13.4.3 (dépendance de développement, versions affectées `<13.4.4`). PHPUnit est passé à 13.4.5. Les audits `composer audit --locked` et `composer audit --locked --no-dev` sont maintenant sans avis. L’avis documenté concerne un scénario local de couverture PHPT via un répertoire temporaire partagé ; il ne démontre pas une attaque distante contre l’application.
- Au début de l’audit, PHP appliquait `upload_max_filesize=2M`, `post_max_size=8M`, `memory_limit=128M`. Le contrôleur accepte des fichiers jusqu’à 3 MiB : PHP pouvait rejeter un fichier avant la validation métier. La configuration définit maintenant PHP à 4M/5M et Nginx à 5m ; les valeurs effectives sont vérifiées dans le conteneur démarré. Un test fonctionnel a téléversé une image de 3 MiB, obtenu 201 puis supprimé le fichier à nom aléatoire ; la base utilisée est SQLite en mémoire. Le proxy de production devra lui aussi autoriser au moins la taille correspondante.
- Le dépôt conserve les images téléversées dans un volume Docker local. Ce volume n’est pas une sauvegarde ; aucune restauration n’a été exécutée en environnement de production.
- Le profil séparé `compose.production.yaml` et ses deux Dockerfiles dédiés ont été construits dans un projet Compose distinct, lié uniquement à `127.0.0.1:8086`. L’aperçu a démarré sur des volumes neufs séparés de la stack de développement ; les six migrations ont été appliquées et Doctrine a confirmé le schéma synchronisé. `APP_ENV=prod`, `APP_DEBUG=false`, le cache prod est construit au démarrage et PHPUnit est absent de l’image.
- Les noms de projet Compose sont explicites : `quai-antique` pour le développement (préservant les noms de volumes existants) et `quai-antique-prod-preview` pour la prévisualisation. Le contexte PHP de production emploie `back/Dockerfile.production.dockerignore` pour exclure les tests et fichiers PHPUnit sans enlever ces fichiers du contexte de développement; l’image exécute ensuite `composer dump-autoload --no-dev --classmap-authoritative` après la copie du code.
- Dans cet aperçu, la page racine renvoie 200 avec `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` et `Permissions-Policy`. `/api/health` renvoie 200 ; `/api/account/me` sans jeton renvoie 401 ; le précontrôle CORS accepte l’origine et l’en-tête `X-AUTH-TOKEN`. `X-Powered-By` est masqué. Ces vérifications ne remplacent pas une recette hébergée sous HTTPS.
- Le volume d’uploads neuf a été monté puis testé : l’utilisateur `www-data` peut y créer et supprimer un fichier temporaire ; Nginx monte ce même volume en lecture seule. Le test est limité à l’écriture système et ne remplace pas le parcours d’upload authentifié dans un navigateur.
- Le healthcheck de l’API vérifie seulement que PHP-FPM écoute sur son port. Le healthcheck du service Web appelle `/api/health` via Nginx ; ce dernier vérifie le routage et l’application, mais `/api/health` ne contrôle pas MySQL. Un healthcheck qui vérifie les dépendances métier reste à définir selon l’hébergeur.
- `.env.production.example` ne contient que des valeurs factices. L’aperçu utilise un fichier `.env.production` local ignoré par Git, avec des secrets aléatoires temporaires ; ils ne sont pas les secrets d’un hébergeur.

## Bloqueurs avant publication

1. **Choisir l’hébergeur et l’architecture réseau.** Décider où se termine TLS (plateforme, proxy géré ou reverse proxy) et comment seul le trafic prévu atteint Nginx. Aucun choix de fournisseur n’est enregistré.
2. **Adapter le profil au fournisseur.** Le runtime prod et son test local sont réalisés ; adapter le binding réseau, les services DB, le stockage média, les sondes et le proxy selon l’hébergeur avant publication. Le contrôle PHP-FPM seul n’est pas une sonde applicative ; le contrôle HTTP actuel de Nginx couvre la route de santé, pas la disponibilité MySQL.
3. **Configurer les secrets et origines publiques.** Fournir `APP_SECRET`, `DATABASE_URL`, origine CORS et `DEFAULT_URI` depuis le gestionnaire de secrets de l’hébergeur ; ne pas réutiliser `.env.docker` ni les valeurs factices du fichier exemple.
4. **Valider HTTPS et les protections Web.** Tester certificats, redirection HTTPS, en-têtes de sécurité, cookies, domaines, proxy de confiance et politique CSP. Examiner le risque lié au jeton d’authentification conservé dans `sessionStorage`, accessible à un script injecté en cas de XSS. Si un proxy est placé devant Nginx, déclarer son adresse dans `framework.trusted_proxies` : sans cela, Symfony voit l’adresse du proxy pour toutes les requêtes, et les limiteurs de connexion et d’inscription bloqueraient tous les utilisateurs ensemble.
5. **Choisir le stockage de production des médias.** La configuration règle PHP à 4M/5M et Nginx à 5m ; un téléversement API authentifié de 3 MiB réussit dans le test isolé. Choisir le stockage persistant de l’hébergeur et aligner sa limite proxy ; vérifier la conservation après redémarrage.
6. **Préparer les données récupérables.** Définir sauvegardes chiffrées et hors machine pour MySQL et les médias, rétention et accès ; réaliser une restauration d’essai. Les fixtures locales peuvent purger les données et ne doivent jamais servir à initialiser une base réelle.
7. **Préparer une livraison réversible.** Définir l’exécution contrôlée des migrations, health checks qui couvrent les dépendances requises, journaux/surveillance et retour arrière. Le health endpoint actuel est statique et ne confirme pas la connexion MySQL.
8. **Répéter les parcours en préproduction.** Vérifier inscription/connexion, accès client/admin, réservation et capacité concurrente, gestion de contenu, OpenAPI selon la politique retenue, et restauration avant ouverture au public.
9. **Compléter les obligations du service.** Vérifier mentions légales, politique de confidentialité, conservation des données, courriels transactionnels et contacts de support avec les informations réelles du propriétaire.

## Initialiser le restaurant sur une base neuve

Les fixtures servent uniquement aux environnements de démonstration et de test. Sur une base de production neuve, appliquer les migrations puis créer la configuration métier avec la commande idempotente `app:init-restaurant` :

```sh
php bin/console doctrine:migrations:migrate --env=prod --no-interaction
php bin/console app:init-restaurant --env=prod
```

Par défaut, la commande configure une capacité de 40 couverts, le service du midi de 12:00 à 14:00 et celui du soir de 19:00 à 21:00, du mardi au dimanche. Les options `--capacite-max`, `--debut-midi`, `--fin-midi`, `--debut-soir` et `--fin-soir` permettent d’adapter ces valeurs. Chaque service doit durer deux heures, la capacité doit être comprise entre 1 et 65 535, et le service du midi doit finir avant le début du soir. Si un restaurant existe déjà, la commande réussit sans le modifier.

Exemple :

```sh
php bin/console app:init-restaurant --env=prod --capacite-max=48 --debut-midi=11:30 --fin-midi=13:30 --debut-soir=18:30 --fin-soir=20:30
```

Créer ensuite le compte administrateur. Le mot de passe est demandé de façon masquée et n’apparaît ni dans l’historique ni dans la liste des processus :

```sh
php bin/console app:create-admin --env=prod --email=adresse@domaine --prenom=Prénom --nom=Nom
```

Vérifier ensuite `GET /api/restaurant` avant d’ouvrir les réservations et la gestion de contenu.

## Séquence de mise en œuvre recommandée

1. Choisir l’hébergeur et obtenir ses contraintes réseau, de volumes, de base, de secrets et de terminaison TLS.
2. Adapter le profil d’aperçu validé à l’hébergeur ; produire ses secrets et valeurs d’origine dans son gestionnaire de secrets.
3. Déployer en préproduction avec domaine de test et base jetable.
4. Valider HTTPS, migrations, limites d’upload, parcours, sauvegarde/restauration, sécurité et retour arrière.
5. Seulement après ces validations, planifier la mise en production.

## Faits et limites

- Les corrections de sécurité locale, de dépendances et de documentation décrites dans ce rapport ont été fusionnées dans `main` via une pull request.
- L’état Docker local et le profil de production de prévisualisation ont été vérifiés. Le profil n’est pas un déploiement et ne connaît pas encore l’hébergeur cible.
- Le port 82 est l’accès HTTP local et ne fournit pas TLS.
- Les avis Composer reflètent la base d’avis consultée le 28 octobre 2026 ; les réexaminer au moment de la construction de l’image.
- Le déploiement n’est pas réalisé et aucun service public n’a été créé.
