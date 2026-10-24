# Rapport de conception — modèle de données et cours Studi

**État au 25 octobre 2026 :** MCD et MLD contrôlés et mis à jour pour le périmètre de conception actuel. Les règles de tarification affichées dans la maquette restent à confirmer avant de figer les champs tarifaires détaillés.
**Sources :** cahier des charges Quai Antique, maquette de la carte, modèle Draw.io et cours Studi « Créer base de données application » (notamment « Pour aller plus loin »).

## Décisions de modélisation

| Sujet | Décision retenue | Justification / écart au cours |
|---|---|---|
| Catégorie et plat | Une catégorie peut contenir plusieurs plats; chaque plat appartient à une seule catégorie. Le MLD place `id_categorie` dans `PLAT`. | Le cours propose une relation N-N. Le CDC demande des plats rangés par catégories sans dire qu’un plat peut appartenir à plusieurs catégories. La relation 1-N est retenue pour le périmètre actuel; une règle différente nécessiterait une table de jointure. |
| Menu et catégories | `MENU` et `CATEGORIE` sont en N-N via `MENU_CATEGORIE`, dont la clé primaire est composée de `id_menu` et `id_categorie`. | Cette relation suit le cours et correspond aux choix décrits (« entrée + plat », « plat + dessert »). Les plats précis inclus dans chaque menu ne sont pas encore définis; ils ne sont donc pas inventés dans le modèle. La description du menu conserve actuellement le détail textuel des choix. |
| Restaurant et données liées | `RESTAURANT` est relié à `SERVICE`, `MENU`, `PHOTO` et `PARAMETRE_RESTAURANT`. `PARAMETRE_RESTAURANT.id_restaurant` est unique pour représenter le lien 1-1. | Le restaurant est unique dans le CDC. Les liens explicites sont conservés pour structurer les données et éviter de recopier ses informations dans chaque table. |
| Réservation | Une réservation référence un seul utilisateur et un seul service. Le restaurant est accessible par le service; aucune seconde FK restaurant n’est ajoutée dans `RESERVATION`. | Le CDC décrit un seul restaurant; une FK supplémentaire serait redondante dans le périmètre actuel. |
| Utilisateur et sécurité | `UTILISATEUR` et `ROLE` restent dans la conception; le mot de passe est nommé `mot_de_passe_hash` dans le MCD et le MLD. | Le cours reporte l’entité User à la partie sécurité. Le champ représente bien un hash, jamais un mot de passe en clair. |
| Nommage | Les entités et colonnes restent nommées en français dans les diagrammes. | C’est une convention de projet; la correspondance avec les noms des classes Symfony sera documentée lors de l’implémentation Doctrine. |

## Point tarifaire à confirmer

La maquette de la carte présente des plats « à partager » à un prix par personne et indique un minimum de deux convives. Le MCD/MLD actuel stocke un prix, mais ne distingue pas son unité et ne représente pas ce minimum. Les montants sont des données de maquette, pas des tarifs imposés par le CDC. Si ces règles sont conservées dans l’application, il faudra ajouter au modèle les attributs adaptés, probablement au niveau de la catégorie « À partager » ou du plat selon que la règle est commune ou variable.

## Contrôle MCD → MLD

- Les relations 1-N sont traduites par une clé étrangère dans la table du côté N : notamment `PLAT.id_categorie`, `SERVICE.id_restaurant`, `MENU.id_restaurant` et `PHOTO.id_restaurant`.
- Les relations N-N `MENU`–`CATEGORIE` sont traduites par `MENU_CATEGORIE`.
- La relation 1-1 Restaurant–Paramètre est représentée par `PARAMETRE_RESTAURANT.id_restaurant` avec une contrainte d’unicité.
- Les quatre vues du fichier Draw.io sont conservées. La validation porte sur la cohérence de conception des MCD et MLD; les vues UML ne remplacent pas cette vérification.

## Limite de preuve et suite

Le diagramme constitue une preuve de conception de données pour l’exemple 4. Il ne prouve pas encore la création ni le fonctionnement d’une base de données. Aucune entité Doctrine, migration, table SQL, donnée d’essai ou sauvegarde/restauration n’est encore créée dans le projet. Ces preuves seront ajoutées au fur et à mesure de l’implémentation et des validations réelles.
