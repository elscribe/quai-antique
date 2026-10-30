# Mise en ligne sur alwaysdata et Cloudflare

Ce document décrit la mise en ligne réelle de Quai Antique. Il ne contient aucun secret : les mots de passe et la clé `APP_SECRET` restent sur le serveur, dans `.env.local`.

## Architecture

| Élément | Hébergeur | Adresse | Rôle |
|---|---|---|---|
| Front (HTML, CSS, JS, images) | Cloudflare Workers (fichiers statiques) | `https://quai-antique.jordan-mflouison.workers.dev` | Pages publiques et espace administrateur |
| API (Symfony 7.4) | alwaysdata, site PHP | `https://elscribe.alwaysdata.net` | Données, authentification, réservations |
| Base de données | alwaysdata, MariaDB 11.8.9 | `mysql-elscribe.alwaysdata.net:3306`, base `elscribe_quai_antique` | Données de l'application |

Le Front et l'API ont **deux origines différentes** : le navigateur applique donc CORS, et l'API n'accepte que l'adresse du Front. C'est un choix volontaire, pour garder la configuration CORS du cours.

Le choix d'alwaysdata pour l'API tient à l'offre gratuite, sans carte bancaire. Les sous-domaines de `alwaysdata.net` ne peuvent pas être créés librement sur l'offre gratuite : l'API utilise donc l'adresse principale du compte, et le Front est hébergé ailleurs.

## Préparation du Front

Le Front n'est pas déployé depuis Git. Il est copié dans un dossier séparé, avec la bonne adresse d'API :

1. Copier `src/` dans `front-alwaysdata/src/`.
2. Copier `assets/` dans `front-alwaysdata/assets/`. Les pages utilisent `../assets/`, donc cette structure est obligatoire.
3. Remplacer `src/api-config.js` par une version qui fixe `apiOrigin` à `https://elscribe.alwaysdata.net`.
4. Ajouter un `index.html` à la racine, qui redirige vers `src/index.html`.
5. Téléverser le dossier `front-alwaysdata` dans Cloudflare (Workers & Pages, option de dépôt direct).

Le dossier `assets/` pèse environ 57 Mo, et aucun fichier ne dépasse 25 Mo.

## Préparation de l'API sur alwaysdata

Le répertoire racine du site PHP est `quai-antique/back/public/`, à l'intérieur du dossier personnel. Les dossiers ont été créés par SSH, car alwaysdata ne les crée pas automatiquement.

1. Copier le code de `back/` dans `~/quai-antique/back/`, **sans** `vendor/`, `var/` et les fichiers `.env*`.
2. Installer les dépendances de production : `composer install --no-dev --optimize-autoloader`. `vendor/` pèse alors environ 47 Mo.
3. Créer `~/quai-antique/back/.env.local` (droits `600`), avec les variables décrites ci-dessous.
4. Créer `public/.htaccess` pour la réécriture des URL :
   ```apache
   RewriteEngine On
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteRule ^ index.php [L]
   ```
5. Lancer les migrations en production : `php bin/console doctrine:migrations:migrate --env=prod`.
6. Créer le compte administrateur : `php bin/console app:create-admin --env=prod`. Le mot de passe est saisi dans le terminal.
7. Initialiser la fiche du restaurant : `php bin/console app:init-restaurant --env=prod`. La commande ne modifie rien si une fiche existe déjà.

Les fixtures (données de démonstration) ne sont **pas** chargées en production.

## Variables de `.env.local`

| Variable | Valeur attendue | Remarque |
|---|---|---|
| `APP_ENV` | `prod` | |
| `APP_DEBUG` | `0` | Aucune trace d'erreur affichée |
| `APP_SECRET` | généré avec `openssl rand -hex 32` | Ne jamais versionner |
| `DEFAULT_URI` | `https://elscribe.alwaysdata.net` | Sert à générer les URL absolues |
| `CORS_ALLOW_ORIGIN` | `'^https://quai-antique\.jordan-mflouison\.workers\.dev$'` | Expression régulière, point d'échappement, pas de `/` final |
| `DATABASE_URL` | `mysql://elscribe_qa:<mot de passe>@mysql-elscribe.alwaysdata.net:3306/elscribe_quai_antique?serverVersion=11.8.9-MariaDB&charset=utf8mb4` | Utilisateur dédié, droits sur cette base uniquement |

## Vérifications

| Test | Résultat attendu | Résultat |
|---|---|---|
| `GET /api/health` | `200` | Vérifié |
| `GET /api/restaurant` | `200` après initialisation | Vérifié |
| `GET /api/account/me` sans jeton | `401` | Vérifié |
| Requête `OPTIONS` depuis l'origine du Front | en-tête `Access-Control-Allow-Origin` présent | Vérifié |
| Requête depuis une origine étrangère | aucun en-tête CORS | Vérifié |
| Limite d'inscription : 6 tentatives depuis le Wi-Fi | 5 fois `201`, puis `429` | Vérifié une fois |
| Limite d'inscription : tentatives depuis une seconde adresse IP | `201` puis `429` | **Non concluant** : 6 inscriptions acceptées de suite depuis une autre adresse, sans `429` |
| Carte et galerie du Front | contenu affiché | Contenu à ajouter depuis l'espace administrateur |

Diagnostic réalisé : l'application reçoit l'adresse réelle du client, sans proxy intermédiaire (`REMOTE_ADDR`, aucun en-tête `X-Forwarded-For`). Pas besoin de `SYMFONY_TRUSTED_PROXIES`.

Un même ordinateur peut toutefois être vu avec **deux adresses** : une IPv4 et une IPv6. La limite compte chaque adresse séparément. Pendant le test, les requêtes ont probablement utilisé les deux familles, ce qui a éparpillé le compteur. Le résultat « 6 inscriptions acceptées » n'est donc pas une preuve que la limite est partagée entre utilisateurs.

Test refait en forçant IPv4 (`curl -4`) : 4 inscriptions acceptées, puis `429` dès la 5e tentative. Hypothèse, non vérifiée : le compteur gardait probablement un essai fait depuis la même adresse IPv4 dans l'heure précédente, ce qui expliquerait 4 inscriptions au lieu de 5. Conclusion : la limite s'applique bien par adresse IP, et elle n'est pas partagée entre utilisateurs différents.

Les 12 comptes de test ont été supprimés de la base de production après le diagnostic.

## Points à vérifier dans le tableau de bord

- La redirection automatique de HTTP vers HTTPS (option « Forcer le HTTPS » du site).
- Le quota disque réel du compte gratuit.
- Les sauvegardes proposées par alwaysdata pour la base et les fichiers.

## Retour arrière

- **Code de l'API :** remettre l'ancienne version du dossier `~/quai-antique/back/`, puis relancer `composer install --no-dev`.
- **Base de données :** restaurer la sauvegarde avant la mise en ligne. Les migrations ne sont pas réversibles automatiquement : utiliser `doctrine:migrations:migrate prev` seulement si nécessaire.
- **Front :** redéployer la version précédente du dossier `front-alwaysdata` sur Cloudflare.

## Ce qui n'est pas dans le dépôt Git

- `.env.local` et tout secret.
- `vendor/` et `var/`.
- Le dossier `front-alwaysdata`, qui contient une adresse d'API spécifique au déploiement.
