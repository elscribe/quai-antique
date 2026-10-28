# Conception des données — Quai Antique

## Provenance des documents

La première version des documents de conception a été reprise du dépôt local du Dossier professionnel, branche `exemple-04-base-de-donnees`, commit `5995b7fdb27f9d79ce8409fc676cb3045c9c71f6`. Le modèle est ensuite adapté au projet Quai Antique dans ce dépôt.

Le dépôt du Dossier professionnel est distinct et local. Sa copie de preuve est synchronisée avec le fichier de ce dépôt, mais ses commits restent indépendants.

## Fichiers repris

- `../conception/Quai Antique CDC.pdf` : cahier des charges client.
- `../conception/brief-client.md` : synthèse du brief.
- `../conception/analyse-besoin.md` : besoins et contenus repérés.
- `../conception/parcours-utilisateur.md` : parcours visiteur, client et administrateur.
- `modelisation-quai-antique.drawio` : MCD, MLD relationnel, cas d’utilisation, diagramme de classes et MPD physique MySQL.

## État de la modélisation

Les diagrammes sont confrontés au cahier des charges et documentent les règles de données retenues. Ils prouvent la conception, pas à eux seuls la création complète de la base. L’implémentation Doctrine comporte huit entités mappées et leurs relations de base. Quatre migrations initiales ont créé les tables `categorie`, `menu`, `menu_categorie`, `photo`, `plat`, `reservation` et `utilisateur`, avec leurs clés étrangères et index. Sur la branche de sécurisation, le mapping de `Utilisateur` inclut maintenant le champ nullable et unique `apiTokenHash`; une cinquième migration a ajouté sa colonne nullable et son index unique; `doctrine:schema:validate` confirme la synchronisation. L’inscription, le login et la vérification du jeton sont implémentés; leur validation HTTP locale est documentée dans le rapport. Voir celui-ci pour les limites restantes.

La base et les tables issues des quatre migrations existent dans MySQL. La migration du champ `api_token_hash` est appliquée et la synchronisation Doctrine est validée. L’authentification, le profil et les autorisations ont été vérifiés avec 16 requêtes et 23 assertions réussies dans Postman CLI. Cinq tests PHPUnit persistants (29 assertions) utilisent une base SQLite séparée. Le détail figure dans [`../security/rapport-tests-postman.md`](../security/rapport-tests-postman.md). La génération automatique des UUID et la mise à jour automatique de `date_modification` restent à traiter pour les autres entités.

Voir [`rapport-ecarts-cours.md`](rapport-ecarts-cours.md) pour les décisions, adaptations et limites de preuve.
