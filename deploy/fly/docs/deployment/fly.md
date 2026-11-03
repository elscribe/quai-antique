# Mise en ligne sur Fly.io

Quai Antique est déployé sur Fly.io sous la forme de deux applications :

- `quai-antique-jmf` : une image unique (`Dockerfile.fly`) qui contient Nginx et PHP-FPM. Nginx sert le Front et transmet `/api` à Symfony sur la même machine. Fly.io fournit le domaine `https://quai-antique-jmf.fly.dev` et le certificat HTTPS.
- `quai-antique-jmf-mysql` : MySQL 8.4 avec un volume persistant. Elle n'a aucun port public ; l'application web la joint par le réseau privé de Fly.io (`quai-antique-jmf-mysql.internal`).

## Ce qui se passe à chaque déploiement

1. Fly.io construit l'image : dépendances Composer sans outils de développement, sans tests ni Composer dans l'image finale.
2. La commande de release (`docker/fly/release.sh`) applique les migrations Doctrine puis lance `app:init-restaurant`, qui ne modifie rien si le restaurant existe déjà.
3. La nouvelle machine démarre (`docker/fly/entrypoint.sh`) : cache Symfony de production, PHP-FPM, puis Nginx sur le port 8080. Fly.io vérifie `/api/health` avant d'envoyer du trafic.

## Secrets

Les secrets ne sont jamais écrits dans le dépôt. Ils sont enregistrés avec `fly secrets set` :

- application web : `APP_SECRET`, `DATABASE_URL` ;
- base MySQL : `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`.

Les autres variables (`APP_ENV=prod`, `DEFAULT_URI`, `CORS_ALLOW_ORIGIN`) sont dans `fly.toml`.

## Sécurité

- HTTPS forcé par Fly.io, en-tête `Strict-Transport-Security` ajouté par Nginx, ainsi que `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` et `Permissions-Policy`.
- L'adresse réelle du visiteur est lue dans l'en-tête `Fly-Client-IP` (module realip de Nginx). Sans cela, les limiteurs de connexion et d'inscription verraient tous les visiteurs avec l'adresse du proxy de Fly.io.
- `APP_DEBUG=0` et `expose_php=Off` : aucune trace technique n'est renvoyée.

## Premier déploiement et compte administrateur

Le script `_claude-sync/deploiement-fly.sh` (hors dépôt) enchaîne : test local de l'image, création des deux applications et de leurs volumes, secrets générés aléatoirement, déploiement de MySQL puis de l'application, et vérifications HTTP. Le compte administrateur se crée ensuite par SSH, mot de passe saisi de façon masquée :

```sh
fly ssh console --app quai-antique-jmf --pty -C "su -s /bin/sh www-data -c 'php bin/console app:create-admin --env=prod --email=... --prenom=... --nom=...'"
```

## Mettre à jour

```sh
fly deploy --config fly.toml
```

## Sauvegarde de la base

```sh
fly ssh console --app quai-antique-jmf-mysql -C "sh -c 'mysqldump -uroot -p\"\$MYSQL_ROOT_PASSWORD\" quai_antique'" > sauvegarde-quai-antique.sql
```

Les photos téléversées sont sur le volume `uploads` de l'application web ; Fly.io prend des instantanés quotidiens des volumes.
