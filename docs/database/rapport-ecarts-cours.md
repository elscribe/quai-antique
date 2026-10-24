# Rapport initial — modèle de données et cours Studi

**État :** analyse préliminaire, décisions à valider avant Doctrine.
**Sources :** cahier des charges Quai Antique, modèle Draw.io copié, cours Studi « Créer base de données application » (section « Pour aller plus loin »).

## Ce qui existe déjà

Le Draw.io contient quatre vues : MCD Merise, MLD relationnel, UML des cas d’utilisation et diagramme de classes. Le modèle couvre les comptes et rôles, réservations, services, restaurant et capacité, photos, catégories, plats et menus. Des règles métier sont déjà inscrites : réservation liée à un utilisateur et à un service, créneaux de 15 minutes et limite de capacité.

## Écarts à vérifier avec le cours

| Sujet | Modèle déjà préparé | Demande du cours | Point à trancher |
|---|---|---|---|
| Réservation | `RESERVATION` est liée à `UTILISATEUR` et `SERVICE`; `SERVICE` est lié à `RESTAURANT`. | `Booking` est lié en Many-To-One à `Restaurant`. | Le lien indirect par le service suffit-il pour le restaurant unique du brief, ou faut-il une FK directe ? Éviter de stocker deux fois la même information sans règle métier qui le justifie. |
| Catégorie et plat | `PLAT` porte `id_categorie` dans le MLD, soit une relation directe de type plusieurs plats vers une catégorie. | `Category` et `Food` en Many-To-Many. | Le brief dit que les plats sont rangés par catégories. Vérifier si un plat peut réellement appartenir à plusieurs catégories avant d’ajouter une table de jointure. |
| Menu et éléments | Le MCD relie `MENU` et `PLAT` par l’association `COMPOSER`. | `Menu` et `Category` en Many-To-Many. | Le brief décrit un menu par ses choix possibles. Une relation menu-plats peut mieux exprimer ces choix ; vérifier sa cardinalité et sa table de jointure dans le MLD. |
| Utilisateur et rôle | `UTILISATEUR` et `ROLE` figurent déjà dans le modèle. | Le cours indique que l’entité `User` sera traitée plus tard dans le contexte de la sécurité. | On peut conserver ces concepts dans la conception, puis implémenter l’entité et l’authentification au cours sécurité. |
| Nommage | `RESERVATION`, `PLAT`, `CATEGORIE`, `UTILISATEUR`. | `Booking`, `Food`, `Category`, `User`. | Choisir une convention cohérente avec le code PHP et le projet; le nommage français ou anglais est un choix de projet à documenter. |

## Contrôles nécessaires avant de générer les entités

1. Vérifier chaque cardinalité du MCD à partir des règles du cahier des charges.
2. Vérifier que le MLD traduit chaque relation correctement : clés étrangères pour 1-N, tables de jointure pour N-N.
3. En particulier, confirmer la cardinalité de `COMPOSER` et la représentation de cette relation dans le MLD : aucune table de jointure n’est visible dans la version copiée.
4. Décider si `SERVICE` appartient à un seul restaurant et si la réservation référence un service daté ou seulement une définition d’horaires. Le CDC demande des horaires configurables, des créneaux de 15 minutes et une capacité maximale par service.
5. Mettre à jour le modèle du projet Quai Antique et consigner les choix retenus ici, sans altérer le modèle source du Dossier professionnel.

## État technique

Le socle Symfony est versionné sur une branche distincte. Aucune entité Doctrine, migration, schéma SQL ni jeu d’essai n’est encore créé dans ce projet. L’installation de Doctrine doit commencer après validation des relations conceptuelles et logiques.
