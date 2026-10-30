# Rapport de conception — modèle de données et cours Studi

> **Lecture au 29 octobre 2026.** Les sections datées du 26 octobre décrivent l’état de la base à cette date (cinq migrations). Depuis, une sixième migration a été ajoutée (expiration du jeton d’API) : le code contient **six migrations**. Les résultats de commandes cités dans ces sections sont historiques ; la preuve actuelle du schéma est la sortie datée de `doctrine:schema:validate` conservée dans le dossier de validation finale. Voir [la matrice de critères](../conception/exigences-criteres-preuves.md).


**État au 26 octobre 2026 :** les cinq vues de données et d’UML ont été relues ensemble après la mise à jour des cardinalités du MCD. Les différences conservées par rapport au MPD Studi sont justifiées ci-dessous; les règles tarifaires de la maquette restent à confirmer. Les huit entités Doctrine et leurs relations de base sont mappées; cinq migrations étaient alors appliquées à `quai_antique` (une sixième a été ajoutée depuis), dont une qui ajoute `Utilisateur.apiTokenHash`; `doctrine:schema:validate` confirmait alors la synchronisation du schéma.
**Sources :** cahier des charges Quai Antique, maquette de la carte, modèle Draw.io et cours Studi « Créer base de données application » (notamment « Pour aller plus loin »).

## Décisions de modélisation

| Sujet | Décision retenue | Justification / écart au cours |
|---|---|---|
| Catégorie et plat | Une catégorie peut contenir plusieurs plats; chaque plat appartient à une seule catégorie. Le MLD place `id_categorie` dans `PLAT`. | Le cours propose une relation N-N. Le CDC demande des plats rangés par catégories sans dire qu’un plat peut appartenir à plusieurs catégories. La relation 1-N est retenue pour le périmètre actuel; une règle différente nécessiterait une table de jointure. |
| Menu et catégories | `MENU` et `CATEGORIE` sont en N-N via `MENU_CATEGORIE`, avec un identifiant propre et deux clés étrangères, comme dans le MPD du cours. | Cette relation suit le cours et correspond aux choix décrits (« entrée + plat », « plat + dessert »). Les plats précis inclus dans chaque menu ne sont pas encore définis; ils ne sont donc pas inventés dans le modèle. La description du menu conserve actuellement le détail textuel des choix. |
| Restaurant et données liées | `RESTAURANT` porte `horaire_midi`, `horaire_soir` et `capacite_max`, et reste relié à `MENU` et `PHOTO`. Les jours d’ouverture (mardi à dimanche) sont fixes et ne sont pas stockés comme lignes de service. | Le MPD du cours regroupe les horaires réglables et la capacité dans `RESTAURANT`. Cette structure répond au CDC : deux services de deux heures, horaires modifiables, capacité maximale paramétrable. Les horaires sont représentés en JSON dans le MLD pour suivre le MPD du cours. |
| Menus du restaurant | Le Quai Antique conserve au moins un `MENU`; chaque menu appartient à ce restaurant. | Le CDC demande de gérer et d’afficher les menus. Le minimum d’un menu est une règle de périmètre décidée pour ce restaurant unique; le CDC ne formule pas explicitement cette cardinalité minimale. Le MCD la formalise et l’application devra empêcher la suppression du dernier menu. La FK `MENU.id_restaurant` garantit qu’un menu appartient au restaurant, mais ne garantit pas à elle seule qu’il reste au moins un menu. |
| Réservation | Une réservation référence un seul `UTILISATEUR` et un seul `RESTAURANT`; elle conserve date, heure prévue, nombre de convives et allergies. | Cette relation directe suit le MPD du cours. Le service choisi est déduit de la date et de l’heure par les règles métier ; ses horaires sont ceux enregistrés dans `RESTAURANT`. |
| Utilisateur et sécurité | `UTILISATEUR` porte `roles` (JSON), un hash de mot de passe et un jeton API selon le parcours du cours. Il n’y a pas d’entité `ROLE` séparée. | Le CDC distingue un client et un administrateur; le visiteur n’a pas de compte. Le cours stocke les rôles dans `User`. Ce choix correspond aux profils fixes du projet et évite une table de gestion des rôles dont le CDC n’exprime pas le besoin. Le mot de passe reste stocké sous forme de hash, jamais en clair. Le cours stocke son jeton API dans `User`; Quai Antique prévoit `api_token_hash`, empreinte SHA-256 hexadécimale de 64 caractères, nullable et unique, dans l’entité déjà existante `Utilisateur`. Le jeton brut est remis au client lors de l’inscription ou du login, puis seule son empreinte est conservée en base. C’est un écart de sécurité documenté, pas une nouvelle entité. Le champ et sa migration sont appliqués; l’émission et la vérification du jeton sont implémentées. |
| Nommage | Les entités et colonnes restent nommées en français dans les diagrammes. | C’est une convention de projet; la correspondance avec les noms des classes Symfony sera documentée lors de l’implémentation Doctrine. |

## Comparaison avec le MPD du cours

| Point | MPD Studi | Quai Antique retenu / état | Décision et justification |
|---|---|---|---|
| Horaires et capacité | Propriétés du restaurant | `horaire_midi`, `horaire_soir` et `capacite_max` dans `RESTAURANT` | Aligné. Le CDC prévoit deux services de deux heures, des horaires réglables et une capacité maximale paramétrable. Les jours restent fixes. |
| Réservation et restaurant | Réservation reliée directement au restaurant | `RESERVATION.id_restaurant` | Aligné dans les diagrammes révisés. Le choix du service se déduit de la date et de l’heure. |
| Description du restaurant | `description` dans `RESTAURANT` | Ajoutée au MCD, MLD, diagramme de classes et MPD ; déjà présente dans l’entité Doctrine actuelle | Aligné avec le MPD du cours et l’implémentation existante. |
| Propriétaire du restaurant | Lien 1-1 `Restaurant`–`Owner` | Pas de lien propriétaire direct | Écart volontaire. Le CDC décrit un compte administrateur pour l’hôte d’accueil, qui gère les informations du site; il ne demande pas d’enregistrer un propriétaire du restaurant. Le rôle administrateur suffit pour ce périmètre à restaurant unique. |
| Jeton API | Propriété `apiToken` prévue pour l’authentification par jeton | `api_token_hash VARCHAR(64) NULL UNIQUE` prévu dans `UTILISATEUR` | Adaptation de sécurité : stockage de l’empreinte SHA-256 hexadécimale plutôt que du secret brut. Le champ est ajouté au mapping Doctrine et sa migration est appliquée; l’inscription et le login émettent un jeton brut dont seule l’empreinte est stockée. Chaque login renouvelle le jeton et invalide le précédent. Un seul jeton actif par compte est retenu pour le périmètre actuel; une table séparée serait à envisager pour gérer plusieurs appareils, expirations ou révocations indépendantes. |
| Rôles utilisateur | Tableau `roles` au format JSON dans `User` | Attribut `roles` au format JSON dans `UTILISATEUR` | Aligné sur le cours. Le CDC prévoit des comptes client et administrateur, sans besoin de créer, modifier ou administrer dynamiquement une liste de rôles. Le visiteur reste un utilisateur non authentifié, pas une ligne de rôle ou un compte. Si le besoin évolue vers des rôles configurables ou plusieurs rôles gérés en base, une table dédiée pourra être réévaluée. |
| Plats et catégories | Relation N-N (`Food_Category`) | Une catégorie classe plusieurs plats ; chaque plat a une catégorie | Choix conservé. Le CDC dit que les plats sont rangés par catégories, sans demander qu’un même plat soit présent dans plusieurs catégories. |
| Menus et catégories | N-N via `Menu_Category` | N-N via `MENU_CATEGORIE` | Aligné sur le cours pour l’identifiant propre et les deux clés étrangères; une contrainte unique sur la paire interdit de répéter le même lien menu-catégorie. |
| Clés et champs techniques | Identifiants `INT` et `UUID`, horodatages de création/modification | `id` entier comme clé primaire, `uuid CHAR(36)` unique, `date_creation DATETIME` et `date_modification DATETIME NULL` sur les entités métier | Les UUID et horodatages suivent le MPD du cours et facilitent l’identification et le suivi des modifications. Ils ne sont pas explicitement exigés par le CDC. |
| Photo et prix | `slug` pour l’image; prix `SMALLINT` | `url_image`; prix `DECIMAL(6,2)` | `url_image` décrit directement la ressource image. Le type décimal conserve la possibilité de prix avec centimes. |

Le MPD ajouté dans Draw.io est donc un **MPD physique MySQL adapté au MLD Quai Antique**, et non une copie à l’identique du MPD d’exercice. Les différences conservées sont justifiées par le périmètre du cahier des charges ou par les besoins métier retenus.

## Point tarifaire à confirmer

La maquette de la carte présente des plats « à partager » à un prix par personne et indique un minimum de deux convives. Le MCD/MLD actuel stocke un prix, mais ne distingue pas son unité et ne représente pas ce minimum. Les montants sont des données de maquette, pas des tarifs imposés par le CDC. Si ces règles sont conservées dans l’application, il faudra ajouter au modèle les attributs adaptés, probablement au niveau de la catégorie « À partager » ou du plat selon que la règle est commune ou variable.

## Accès à la carte dans le diagramme de cas d’utilisation

Le cas « Consulter la carte et les menus » est relié à l’acteur commun « Utilisateur du site (tous profils) ». Il couvre le visiteur, le client connecté et l’administrateur, conformément à la consultation de la carte prévue pour tous les utilisateurs dans le CDC.

## Contrôle MCD → MLD

- Les relations 1-N sont traduites par une clé étrangère dans la table du côté N : `RESERVATION.id_utilisateur`, `RESERVATION.id_restaurant`, `PLAT.id_categorie`, `MENU.id_restaurant` et `PHOTO.id_restaurant`.
- Les relations N-N `MENU`–`CATEGORIE` sont traduites par `MENU_CATEGORIE`, dotée de son propre identifiant, de deux clés étrangères et d’une contrainte unique sur la paire menu-catégorie.
- Les horaires et la capacité sont des attributs de `RESTAURANT`; il n’y a plus de tables `SERVICE` ni `PARAMETRE_RESTAURANT`.
- Les cinq vues du fichier Draw.io sont conservées. Le lien Restaurant–Menu est représenté dans le diagramme de classes avec une multiplicité 1..* côté Menu. Le MLD/MPD exprime la FK et la règle minimale est contrôlée par l’application, car une FK seule ne peut pas imposer un nombre minimal de lignes enfants.

## Implémentation Doctrine vérifiée

Le diagramme de classes relie `RESTAURANT` à `MENU` et `MENU` à `CATEGORIE`, conformément au MCD/MLD; il ne modélise pas un lien direct menu–plat. La relation Menu–Catégorie exige au moins une catégorie par menu dans le MCD; cette participation minimale reste une règle applicative à valider.

Les entités mappées sont `Restaurant`, `Reservation`, `Utilisateur`, `Categorie`, `Plat`, `Menu`, `Photo` et l’entité d’association `MenuCategorie`. Les relations configurées suivent le MCD/MLD : Restaurant–Reservation, Utilisateur–Reservation, Restaurant–Menu, Restaurant–Photo, Categorie–Plat, et Menu–Categorie via `MenuCategorie`. Les clés étrangères et la contrainte unique sur la paire (`id_menu`, `id_categorie`) figurent dans la migration appliquée. La table `restaurant` était déjà créée par les migrations précédentes; la migration `Version20261025222456` a créé les sept autres tables.

**Adaptation de nommage Doctrine :** dans la base générée, Doctrine nomme les clés primaires techniques `id` (y compris dans `menu_categorie`), alors que le MPD les nomme par entité (`id_restaurant`, `id_menu_categorie`, etc.). Les colonnes UUID restent présentes et uniques. Ce choix conserve une clé technique simple pour Doctrine; les différences de noms sont une convention d’implémentation, pas une modification des relations métier. Le MPD décrit la cible conceptuelle physique et cette différence doit être expliquée lors de la présentation.

Les colonnes de la table `restaurant` (`id`, `nom`, `description`, `uuid`, `horaire_midi`, `horaire_soir`, `capacite_max`, `date_creation` et `date_modification`) ainsi que les autres tables sont créées dans la base `quai_antique`. La cinquième migration ajoute la colonne nullable `api_token_hash` et son index unique. Au 26 octobre, `doctrine:migrations:status` indiquait cinq migrations exécutées et aucune en attente, et `doctrine:schema:validate` confirmait la synchronisation ; une sixième migration (expiration du jeton) a été ajoutée ensuite.

## Adaptation du cours « Sécuriser accès données » — jeton API

Le cours ajoute un jeton à son entité `User`. Quai Antique conserve son entité métier `Utilisateur`, qui porte déjà l’email, les rôles et le hash du mot de passe. La propriété `apiTokenHash` (colonne prévue `api_token_hash`, `VARCHAR(64)`, nullable et unique) est ajoutée à cette entité existante : aucun doublon `User` n’est créé. Une valeur de 64 caractères correspond à une empreinte SHA-256 représentée en hexadécimal.

**État réel au 26 octobre 2026 :** le champ et sa migration sont en place, et Doctrine confirme la synchronisation. L’inscription crée un compte et hache le mot de passe; inscription et login renvoient un jeton aléatoire, tandis que seule son empreinte SHA-256 est stockée. Le login JSON utilise l’email et le mot de passe. L’authentificateur vérifie `X-AUTH-TOKEN`. L’inscription et le login sont publics; les lectures de la carte sont publiques; les écritures de catégories et de plats exigent `ROLE_ADMIN`; les routes du compte et les autres routes `/api` exigent `ROLE_USER`. Le profil connecté peut être lu et modifié à `/api/account/me`, avec validation, hash du nouveau mot de passe et mise à jour de la date de modification. La validation Postman a réussi (16 requêtes, 23 assertions); 5 tests PHPUnit indépendants (29 assertions) couvrent aussi les rôles. Voir le [rapport de validation Postman](../security/rapport-tests-postman.md). Le provider seul ne réalise pas l’authentification : ce sont `json_login` et l’authentificateur personnalisé qui traitent les requêtes.

**Justification de l’écart :** le cours illustre le flux avec le jeton stocké directement. Le projet choisit de ne conserver que son empreinte, afin qu’une fuite de la base ne divulgue pas directement les jetons utilisables. À chaque requête protégée, l’authentificateur calcule l’empreinte du jeton présenté et cherche l’utilisateur correspondant. Le périmètre actuel prévoit un jeton actif par compte; la gestion de plusieurs appareils et de révocations indépendantes demanderait une entité séparée ultérieurement.

Les champs de profil du cours (« prénom », « nom », nombre de convives par défaut et allergies) existent déjà sous les noms `prenom`, `nom`, `nbConvivesDefaut` et `allergies`; ils ne sont pas à recréer.

## Adaptation du cours « Interface utilisateur » — CRUD API

Le cours fait créer un CRUD REST en JSON et propose ensuite `Food` et `Category` comme ressources d’exercice sans relation. Dans Quai Antique, `Food` correspond à `Plat`, et le MLD impose une clé étrangère `PLAT.id_categorie` non nullable. Le CRUD de `Plat` accepte donc l’UUID d’une catégorie existante (`categorie_uuid`) à la création et à la modification, puis expose son UUID et son titre dans la réponse. Cette adaptation applique la relation définie par le MLD au lieu de créer des plats sans catégorie.

Le cours utilise l’identifiant primaire numérique dans les URLs; l’API Quai Antique utilise les UUID uniques du MLD comme identifiants publics. Le CRUD de `Categorie` refuse également la suppression si des plats ou des menus y sont encore liés, afin de préserver les références du modèle et le contenu de la carte. Le contrôleur hérite d’`AbstractController`, les actions sont déclarées par attributs de route et les réponses sont en JSON.

Les CRUD de `Categorie` et `Plat` ont été vérifiés localement sur les routes HTTP : création `201`, liste/lecture `200`, modification `200`, suppression `204`, refus des données invalides (`400`), catégorie inexistante pour un plat (`422`), suppression d’une catégorie encore liée (`409`) et lecture d’un UUID supprimé (`404`). Le premier parcours Catégorie a été réalisé dans Postman; les vérifications complémentaires ont été faites avec `curl`. Les lignes temporaires de catégorie et de plat ont été supprimées après les essais; les listes sont revenues à `data: []`.

## Limite de preuve et suite

Les diagrammes constituent une preuve de conception de données pour l’exemple 4, et les migrations/synchronisation prouvent la création actuelle du schéma. L’inscription, le login, le profil, la rotation du jeton et les autorisations ont été validés par Postman CLI et PHPUnit; les résultats et limites sont consignés dans le rapport de test. Il reste à provisionner un compte administrateur par une opération d’exploitation contrôlée avant de publier les fonctions d’administration. Aucune capture de l’interface Postman Desktop n’a été prise. Au 26/10, les routes métier de réservation et d’autres ressources restaient à construire ; elles ont depuis été implémentées, leur preuve actuelle restant à établir selon la matrice. La sauvegarde/restauration de la base reste également à préparer et vérifier.

## Complément du 28 octobre — adaptations Front et règles à clarifier

| Sujet | Choix ou écart | Justification et preuve restante |
|---|---|---|
| Bootstrap et Sass | CSS natif, Grid et Flexbox | Adaptation aux maquettes ; conformité aux attendus du cours à confirmer par la formation. |
| Routeur | Pages HTML distinctes et navigation par liens | Les URL et l’historique sont gérés par le navigateur ; acceptation de l’écart au routeur du cours à confirmer. |
| Responsive | Paliers CSS à 760 et 1100 px ; maquettes mobile et bureau | Le contrôle historique à 375/768/1440 px concerne le débordement avec API simulée ; lisibilité, contraste et tablette restent à vérifier. |
| Pied de page | Horaires publics absents ou statiques lors de l’audit | Écart avec le cours et besoin d’informations à jour ; affichage dynamique et preuve à produire. |
| Galerie et carte administrateur | Pages de gestion distinctes plutôt que boutons intégrés à la galerie | Séparation des outils d’administration ; vérifier les parcours, permissions et fidélité aux maquettes. |
| Navigation réservation | Lien public absent de l’en-tête lors de l’audit | Accès par d’autres appels à l’action ; vérifier sa visibilité sur chaque parcours. |
| Mot de passe | Modification proposée dans l’espace client, hors maquette et CDC | Fonction complémentaire ; règles, confirmation et cohérence Front/API à vérifier. |
| Statut de réservation | `confirmee` renseigné ; suppression physique rapportée | État technique ajouté au modèle, sans preuve que les autres badges Figma existent ; arbitrer le besoin avant de leur attribuer un comportement. |
| Réponses invalides | L’audit relève des réponses 400 et 422 selon les cas | Documenter et tester la convention retenue par route ; ne pas déduire une règle uniforme sans vérification. |

Les observations datées du 26/10 ci-dessus sont historiques. Le projet contient désormais six migrations ; l’expiration et la révocation du jeton sont présentes dans les versions ultérieures selon l’audit. Une preuve actuelle de schéma reste à capturer.
