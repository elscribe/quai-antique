# Conception des données — Quai Antique

## Provenance des documents

La première version des documents de conception a été reprise du dépôt local du Dossier professionnel, branche `exemple-04-base-de-donnees`, commit `5995b7fdb27f9d79ce8409fc676cb3045c9c71f6`. Le modèle est ensuite adapté au projet Quai Antique dans ce dépôt.

Le dépôt du Dossier professionnel est distinct et local, avec ses propres commits. Le fichier `modelisation-quai-antique.drawio` de ce dépôt est la référence du projet ; la copie du Dossier professionnel peut en différer et doit être réexportée depuis ce fichier avant d’être utilisée comme preuve.

## Fichiers repris

- `../conception/Quai Antique CDC.pdf` : cahier des charges client.
- `../conception/brief-client.md` : synthèse du brief.
- `../conception/analyse-besoin.md` : besoins et contenus repérés.
- `../conception/parcours-utilisateur.md` : parcours visiteur, client et administrateur.
- `modelisation-quai-antique.drawio` : MCD, MLD relationnel, cas d’utilisation, diagramme de classes et MPD physique MySQL.

## État de la modélisation — 29 octobre 2026

Les cinq vues documentent la conception ; elles ne prouvent pas à elles seules la base en fonctionnement. Le code contient huit entités Doctrine et **six migrations** (`back/migrations/`). La preuve que le schéma réel correspond au mapping est la sortie datée de `doctrine:migrations:status` et de `doctrine:schema:validate` (code de retour 0), conservée dans le dossier de validation finale.

Le [rapport des écarts](rapport-ecarts-cours.md) conserve les observations du 26/10 et les adaptations ultérieures. Les résultats Postman historiques figurent dans le [rapport de sécurité](../security/rapport-tests-postman.md). La [matrice exigences et preuves](../conception/exigences-criteres-preuves.md) distingue implémentation et validation.
