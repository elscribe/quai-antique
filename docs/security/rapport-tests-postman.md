# Rapport de validation Postman — sécurité de l’API

**Date :** 26 octobre 2026

**Branche :** `feature/back-api-security`

**Base URL testée :** `http://127.0.0.1:8000`
**Outil :** Postman CLI 1.76.0, avec une collection Postman temporaire exécutée localement.

## Périmètre

Cette passe valide le parcours d’inscription et de connexion, l’émission et la rotation du jeton API, ainsi que l’accès à une route protégée. Les échanges ont ciblé l’API locale du projet Quai Antique. Les adresses et mots de passe temporaires ainsi que les jetons ne sont pas consignés dans ce rapport.

La collection a été exécutée avec Postman CLI. La mise à jour de Postman Desktop empêchait de modifier sa requête ouverte de façon fiable pendant cette session; le CLI Postman a donc servi à exécuter les requêtes et assertions. Ce rapport ne prétend pas qu’une démonstration dans l’interface graphique ou une capture d’écran a été réalisée.

## Scénarios et résultats

### Authentification et jeton

| Nº | Requête | Résultat attendu | Résultat obtenu | Statut |
|---:|---|---:|---:|---|
| 1 | `POST /api/registration`, données valides | 201 Created | 201 Created | Réussi |
| 2 | `POST /api/registration`, même adresse une seconde fois | 409 Conflict | 409 Conflict | Réussi |
| 3 | `POST /api/registration`, champs invalides et adresse mal formée | 400 Bad Request | 400 Bad Request | Réussi |
| 4 | `POST /api/login`, mot de passe incorrect | 401 Unauthorized | 401 Unauthorized | Réussi |
| 5 | `POST /api/login`, identifiants valides | 200 OK | 200 OK | Réussi |
| 6 | `GET /api/health`, sans `X-AUTH-TOKEN` | 401 Unauthorized | 401 Unauthorized | Réussi |
| 7 | `GET /api/health`, jeton invalide | 401 Unauthorized | 401 Unauthorized | Réussi |
| 8 | `GET /api/health`, jeton d’inscription après une nouvelle connexion | 401 Unauthorized | 401 Unauthorized | Réussi |
| 9 | `GET /api/health`, nouveau jeton de connexion | 200 OK et `status: ok` | 200 OK et `status: ok` | Réussi |

**Bilan d’exécution :** 9 requêtes, 9 scripts de test, 12 assertions réussies, 0 échec. Temps total rapporté par Postman : environ 2 secondes.

### Profil et autorisations

| Nº | Requête | Résultat attendu | Résultat obtenu | Statut |
|---:|---|---:|---:|---|
| 1 | `POST /api/registration`, création du compte de test | 201 Created, rôle client | 201 Created, rôle `ROLE_USER` | Réussi |
| 2 | `GET /api/account/me`, sans jeton | 401 Unauthorized | 401 Unauthorized | Réussi |
| 3 | `GET /api/account/me`, jeton valide | 200 OK, profil sans secrets | 200 OK, profil sans secrets | Réussi |
| 4 | `PUT /api/account/me`, profil valide et tentative d’ajout de `ROLE_ADMIN` | 200 OK, rôle inchangé | 200 OK, le compte reste `ROLE_USER` | Réussi |
| 5 | `PUT /api/account/me`, prénom vide | 400 Bad Request | 400 Bad Request | Réussi |
| 6 | `GET /api/categories`, visiteur non connecté | 200 OK | 200 OK | Réussi |
| 7 | `POST /api/categories`, compte client `ROLE_USER` | 403 Forbidden en JSON | 403 Forbidden en JSON | Réussi |

**Bilan d’exécution :** 7 requêtes, 7 scripts de test, 11 assertions réussies, 0 échec.

## Vérifications de contenu

- L’inscription renvoie les données publiques du compte et un jeton API.
- La réponse ne contient ni mot de passe ni hash du mot de passe.
- La connexion réussie renouvelle le jeton; celui de l’inscription est refusé ensuite.
- La route protégée refuse l’absence de jeton et un jeton invalide, puis accepte le nouveau jeton.
- Le compte peut modifier ses informations et son mot de passe; ce dernier reste hashé et la date de modification est renseignée.
- Le client consulte la carte sans connexion, mais ne peut pas en modifier le contenu.
- Les jetons n’ont pas été recopiés dans le rapport. Le compte créé pour cette passe a été supprimé après les essais.

## Permissions appliquées

- `POST /api/registration` et `POST /api/login` restent publics.
- Les lectures `GET` de `/api/categories`, `/api/plats` et `/api/menus` sont publiques, conformément au parcours de consultation de la carte prévu pour les visiteurs.
- Les créations, modifications et suppressions de catégories et de plats exigent `ROLE_ADMIN`.
- Les routes d’écriture des menus devront également exiger `ROLE_ADMIN` lorsqu’elles seront créées; à ce jour, seule la lecture des menus existe.
- Les autres routes `/api`, dont `/api/account/me`, exigent une authentification et `ROLE_USER`.
- L’inscription attribue uniquement `ROLE_USER`. Le DTO de profil n’accepte pas les rôles; une requête client ne peut donc pas s’accorder `ROLE_ADMIN`.
- Un test PHPUnit en base isolée confirme qu’un administrateur peut créer une catégorie. Aucun compte administrateur réel n’a été ajouté à la base de développement.

## Interprétation et limites

Les scénarios Postman confirment le comportement HTTP local du flux d’authentification et du profil client. La suite PHPUnit séparée (`php bin/phpunit`) comporte 5 tests et 29 assertions; elle vérifie aussi l’accès administrateur sur une base SQLite dédiée aux tests. Les tests Postman ont été exécutés avec Postman CLI; aucune capture de l’interface Postman Desktop n’est jointe. Avant déploiement, un compte administrateur devra être provisionné par une opération réservée à l’exploitant; l’inscription publique ne peut pas lui attribuer ce rôle. Les autres parcours métier restent à construire.

**Bilan total Postman :** 16 requêtes et 23 assertions réussies, 0 échec. Pour expliquer le stockage du jeton : le serveur remet le jeton brut au client, mais conserve en base son empreinte SHA-256. La collection a vérifié la conséquence fonctionnelle de la rotation en montrant que l’ancien jeton ne permet plus d’accéder à la route protégée après la connexion.
