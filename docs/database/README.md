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

Les diagrammes sont confrontés au cahier des charges et documentent les règles de données retenues. Ils prouvent la conception, pas à eux seuls la création complète de la base. L’implémentation Doctrine comporte huit entités mappées et leurs relations. Quatre migrations sont enregistrées dans Doctrine; la dernière crée les tables `categorie`, `menu`, `menu_categorie`, `photo`, `plat`, `reservation` et `utilisateur`, avec leurs clés étrangères et index. La validation `doctrine:schema:validate` confirme que le schéma MySQL est synchronisé avec le mapping.

La base et les tables existent donc maintenant dans MySQL. La prochaine étape est de vérifier leur visibilité dans Workbench, puis de préparer des données d’essai et de tester les opérations métier/API. La génération automatique des UUID et la mise à jour automatique de `date_modification` restent à traiter.

Voir [`rapport-ecarts-cours.md`](rapport-ecarts-cours.md) pour les décisions, adaptations et limites de preuve.
