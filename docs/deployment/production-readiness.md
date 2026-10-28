# État de préparation à la production

Date du contrôle : 28 octobre 2026. Cet audit décrit la préparation locale du projet. Il ne constitue pas une validation d’un hébergeur ni un déploiement.

## Résumé

La stack Docker locale est opérationnelle et le dépôt `main` contient le code correspondant. **Ne pas publier la configuration actuelle telle quelle** : Compose démarre Symfony en développement, l’image inclut les dépendances de développement et aucun domaine, TLS, stockage de secrets ni plan de sauvegarde/restauration en environnement cible n’a été configuré.

## Éléments vérifiés

- `docker compose --env-file .env.docker config -q` réussit ; les services `web`, `api` et `database` sont actifs et sains.
- Nginx passe `nginx -t` ; `/api/health` répond HTTP 200 sur le port local 82.
- La branche de préparation limite l’exposition de la stack locale au loopback `127.0.0.1:82`, au lieu de toutes les interfaces réseau. La publication Internet reste à configurer séparément chez l’hébergeur.
- La suite PHPUnit a été relancée dans une copie temporaire avec SQLite en mémoire puis dans l’image Docker reconstruite : **41 tests et 344 assertions réussis**. Le test ajouté envoie une image de 3 MiB à l’API, vérifie la réponse de succès et supprime immédiatement le fichier à nom aléatoire. Aucun test ne modifie la base MySQL de développement ; les fichiers de test ont été supprimés après usage.
- `composer audit --locked` exécuté dans `back/` signalait un avis sur PHPUnit 13.4.3 (dépendance de développement, versions affectées `<13.4.4`). PHPUnit est passé à 13.4.5 sur la branche de préparation. Les audits `composer audit --locked` et `composer audit --locked --no-dev` sont maintenant sans avis. L’avis documenté concerne un scénario local de couverture PHPT via un répertoire temporaire partagé ; il ne démontre pas une attaque distante contre l’application.
- Au début de l’audit, PHP appliquait `upload_max_filesize=2M`, `post_max_size=8M`, `memory_limit=128M`. Le contrôleur accepte des fichiers jusqu’à 3 MiB : PHP pouvait rejeter un fichier avant la validation métier. La branche de préparation définit maintenant PHP à 4M/5M et Nginx à 5m ; les valeurs effectives sont vérifiées dans le conteneur démarré. Un test fonctionnel a téléversé une image de 3 MiB, obtenu 201 puis supprimé le fichier à nom aléatoire ; la base utilisée est SQLite en mémoire. Le proxy de production devra lui aussi autoriser au moins la taille correspondante.
- Le dépôt conserve les images téléversées dans un volume Docker local. Ce volume n’est pas une sauvegarde ; aucune restauration n’a été exécutée en environnement de production.

## Bloqueurs avant publication

1. **Choisir l’hébergeur et l’architecture réseau.** Décider où se termine TLS (plateforme, proxy géré ou reverse proxy) et comment seul le trafic prévu atteint Nginx. Aucun choix de fournisseur n’est enregistré.
2. **Construire un runtime de production séparé.** Passer à `APP_ENV=prod`, `APP_DEBUG=0`, installer sans dépendances de développement, préparer le cache/assets et les permissions, puis construire et tester une image dédiée sans remplacer la stack locale.
3. **Configurer les secrets et origines publiques.** Fournir `APP_SECRET`, `DATABASE_URL`, origine CORS et `DEFAULT_URI` depuis le gestionnaire de secrets de l’hébergeur ; ne pas réutiliser `.env.docker`.
4. **Valider HTTPS et les protections Web.** Tester certificats, redirection HTTPS, en-têtes de sécurité, cookies, domaines, proxy de confiance et politique CSP. Examiner le risque lié au jeton d’authentification conservé dans `sessionStorage`, accessible à un script injecté en cas de XSS.
5. **Choisir le stockage de production des médias.** La branche de préparation configure PHP à 4M/5M et Nginx à 5m ; un téléversement API authentifié de 3 MiB réussit dans le test isolé. Choisir le stockage persistant de l’hébergeur et aligner sa limite proxy ; vérifier la conservation après redémarrage.
6. **Préparer les données récupérables.** Définir sauvegardes chiffrées et hors machine pour MySQL et les médias, rétention et accès ; réaliser une restauration d’essai. Les fixtures locales peuvent purger les données et ne doivent jamais servir à initialiser une base réelle.
7. **Préparer une livraison réversible.** Définir l’exécution contrôlée des migrations, health checks qui couvrent les dépendances requises, journaux/surveillance et retour arrière. Le health endpoint actuel est statique et ne confirme pas la connexion MySQL.
8. **Répéter les parcours en préproduction.** Vérifier inscription/connexion, accès client/admin, réservation et capacité concurrente, gestion de contenu, OpenAPI selon la politique retenue, et restauration avant ouverture au public.
9. **Compléter les obligations du service.** Vérifier mentions légales, politique de confidentialité, conservation des données, courriels transactionnels et contacts de support avec les informations réelles du propriétaire.

## Séquence de mise en œuvre recommandée

1. Choisir l’hébergeur et obtenir ses contraintes réseau, de volumes, de base et de secrets.
2. Créer un profil/image de production distincts et tester leur construction localement.
3. Déployer en préproduction avec secrets temporaires, domaine de test et base jetable.
4. Valider migrations, limites d’upload, parcours, sauvegarde/restauration, sécurité et retour arrière.
5. Seulement après ces validations, planifier la mise en production.

## Faits et limites

- Les corrections de sécurité locale, de dépendances et de documentation décrites dans ce rapport ont été fusionnées dans `main` via une pull request. La suite de 41 tests et 344 assertions a passé dans l’image Docker reconstruite.
- L’état Docker local a été vérifié ; aucun profil ni fichier de production n’existe encore dans le dépôt.
- Le port 82 est l’accès HTTP local et ne fournit pas TLS.
- Les avis Composer reflètent la base d’avis consultée le 28 octobre 2026 ; les réexaminer au moment de la construction de l’image.
- Le déploiement n’est pas réalisé et aucun service public n’a été créé.
