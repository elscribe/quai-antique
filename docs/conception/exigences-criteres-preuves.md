# Exigences, critères d’acceptation et preuves

Mise à jour : 29 octobre 2026.

Cette matrice relie chaque besoin du [cahier des charges](Quai%20Antique%20CDC.pdf) à un critère observable, puis aux preuves qui le couvrent :

- les **tests automatisés** (`back/tests/`, PHPUnit et BrowserKit sur SQLite en mémoire), cités sous la forme `Fichier::méthode` ;
- la **recette navigateur** ([recette historique du 27/10](recette-parcours-2026-10-27.md), puis la recette de la validation finale) ;
- les **contrôles Front automatisés** (Playwright avec API simulée, voir [RAPPORT-FRONT-ORAL.md](../../RAPPORT-FRONT-ORAL.md)).

Un test cité prouve la règle côté API. Il ne prouve ni le parcours dans le navigateur, ni le comportement sur MySQL (concurrence), ni la production.

| Exigence | Critère observable | Tests automatisés (back/tests) | Autres preuves |
|---|---|---|---|
| R1 Connexion client et admin | Identifiants valides : accès selon le rôle ; invalides : refus ; trop d’échecs : blocage ; déconnexion : jeton révoqué ; jeton expiré refusé. | `SecurityProfileTest::testLoginRejectsWrongPasswordAndRotatesTheApiToken`, `::testLoginThrottlingBlocksRepeatedFailedAttempts`, `::testLogoutRevokesToken`, `::testExpiredApiTokenIsRejectedByProtectedEndpoint` ; `ApiTokenAuthenticatorTest` (5 tests : jeton absent, inconnu, expiré, haché avant recherche) | Recette navigateur des deux rôles. |
| R2 Inscription et préférences | Un client fournit identité, e-mail, mot de passe, convives et allergies ; mot de passe faible ou e-mail en double refusé ; 6ᵉ inscription depuis la même IP dans l’heure refusée ; préférences préremplies à la réservation. | `SecurityProfileTest::testRegistrationHashesPasswordAndReturnsOneTimeApiToken`, `::testRegistrationRejectsInvalidPayloadAndDuplicateEmail`, `::testWeakPasswordAndOversizedNamesAreRejectedAtRegistration`, `::testRegistrationRateLimiterRejectsTheSixthAttemptFromSameIp` | Règle de mot de passe identique côté Front (`src/password.js`) ; préremplissage : recette navigateur. |
| R3 Horaires et capacité | Lecture publique ; modification réservée à l’admin ; valeur invalide refusée ; un réglage ne peut pas rendre invalides des réservations existantes ; initialisation possible sur une base neuve. | `SettingsAndGalleryApiTest::testRestaurantSettingsArePublicToReadAndAdminOnlyToChange`, `ReservationApiTest::testSettingsCannotInvalidateExistingReservations`, `InitRestaurantCommandTest` (3 tests : création, idempotence, refus des valeurs invalides) | Horaires publics affichés par `main.js` (pied de page et contact). |
| R4 Galerie | Visiteur voit photos et titres ; admin ajoute (chemin ou fichier), modifie et supprime ; chemins dangereux et faux fichiers image refusés ; client refusé. | `SettingsAndGalleryApiTest::testPhotoCrudEnforcesAdminAndRejectsUnsafePaths`, `::testAdministratorCanUploadAndDeleteAnImage`, `::testAdministratorCanUploadImageAtThreeMebibytes`, `::testUploadRejectsAClientAndAnImageWithFakeContents` | Front : URL d’images filtrées (`src/photo-url.js`). Persistance des fichiers après redémarrage : preuve Docker de la validation finale. |
| R5 Catégories | Admin crée, modifie, supprime ; corps invalide refusé ; suppression d’une catégorie utilisée refusée. | `CatalogApiTest::testCategoryCrudReturnsExpectedStatusesAndLocation`, `::testCategoryRejectsInvalidBodiesAndDeletingAUsedCategory`, `SecurityProfileTest::testCardIsPublicButOnlyAdministratorsCanChangeIt` | — |
| R6 Plats | Admin gère titre, description, prix et catégorie ; codes HTTP documentés ; lecture publique. | `CatalogApiTest::testPlatCrudReturnsExpectedStatusesLocationAndSerializedRelation`, `::testPlatValidationAndAuthorizationFailuresReturnDocumentedCodes` | Carte publique : recette navigateur. |
| R7 Menus | Admin gère titre, description, prix et catégories ; écritures non autorisées ou invalides refusées. | `MenuApiTest::testAdministratorCanManagePersistedMenus`, `::testMenusRejectUnauthorizedAndInvalidWrites` | — |
| R8 Carte | Visiteur, client et admin voient plats et menus. | Lecture publique couverte par `CatalogApiTest` et `MenuApiTest` | Rendu, états vides et erreurs : contrôles Front automatisés. |
| R9 Réservation | Créneaux de 15 min, jour de fermeture refusé, capacité respectée, date passée et entrée invalide refusées ; un visiteur est invité à se connecter. | `ReservationApiTest::testAvailabilityAndReservationRespectServiceCapacity`, `::testClosedDayAndInvalidQuarterHourAreRejected`, `::testNulBytesInDateAndTimeAreRejectedAsInvalidInput` | Concurrence sur la dernière place (201 puis 409) : uniquement sur MySQL, preuve Docker de la validation finale (le verrou est désactivé sous SQLite). |
| R10 Gestion admin des réservations | Admin consulte, filtre, modifie et supprime ; un client n’a pas accès aux opérations admin. | `ReservationApiTest::testOwnershipAndAdminAccessAreEnforced` | Recette navigateur de l’administration. |
| R11 Gestion client des réservations | Client voit, modifie et annule ses réservations ; celles d’autrui renvoient 404. | `ReservationApiTest::testOwnershipAndAdminAccessAreEnforced` | Annulation depuis l’espace client : recette navigateur. |
| R12 Profil et compte | Client modifie ses données ; changer d’e-mail ou de mot de passe demande le mot de passe actuel ; supprimer le compte demande le mot de passe et efface ses réservations. | `SecurityProfileTest::testUserCanUpdateTheirProfileAndPassword`, `::testChangingEmailRequiresTheCurrentPassword`, `::testChangingPasswordRequiresCurrentPasswordAndRevokesToken`, `::testInvalidProfileUpdateDoesNotChangeTheAccount`, `::testProfileEmailMustBeValidAndUnique`, `ReservationApiTest::testDeletingAccountRequiresPasswordAndDeletesItsReservations` | Confirmation de suppression dans l’interface : recette navigateur. |

## Exigences d’exploitation

| Exigence | Preuve |
|---|---|
| Créer un administrateur sans exposer le mot de passe | `CreateAdminCommandTest::testAdminCreationIsInteractiveAndDoesNotPrintThePassword` |
| Initialiser le restaurant en production | `InitRestaurantCommandTest` (3 tests) |
| Contrat d’API documenté et à jour | `ApiDocumentationTest::testOpenApiContractDocumentsAllRoutesAndSecurityRules`, `::testSwaggerUiIsPublicAndLoadsSuccessfully` |
| Sonde de santé | `ApiSmokeTest::testHealthEndpointReturnsTheExpectedServiceIdentity` |

## Règles de preuve

La suite PHPUnit compte 46 méthodes de test, soit 48 exécutions. Le nombre de tests et d’assertions n’est pas recopié ici : la référence est la sortie brute datée (`php bin/phpunit --testdox`), avec le hash du commit, conservée dans le dossier de validation finale.

Pour toute preuve nouvelle, conserver la date, le commit, l’environnement, la commande ou l’action, le résultat observé et le code de retour.

## Points à confirmer avec la formation

- Mise en page en CSS natif, sans Bootstrap, Sass ni routeur JavaScript.
- User stories attendues ou non, en plus de cette matrice.
- Niveau de déploiement attendu pour l’exemple 6 du Dossier professionnel.
