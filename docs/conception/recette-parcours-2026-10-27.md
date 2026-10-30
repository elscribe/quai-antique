# Checklist de recette des parcours — Quai Antique

> **Lecture au 29 octobre 2026.** Les résultats ci-dessous ont été obtenus le 27 et le 28 octobre, avec le serveur Front local et Symfony sur une base SQLite jetable, pas avec la stack Docker. Ils restent valables comme recette historique ; la recette de référence est celle rejouée sur la stack Docker et conservée dans le dossier de validation finale. Voir [la matrice de critères](exigences-criteres-preuves.md) pour les tests automatisés qui couvrent chaque exigence.


Mise à jour : 28 octobre 2026. Cette checklist reprend le document `parcours-utilisateur.md`. Elle sert à vérifier l'application comme un visiteur, un client ou l'administrateur, puis à consigner les écarts. Les tests PHPUnit valident principalement l'API ; ils ne prouvent pas à eux seuls le fonctionnement de chaque page du navigateur. Les parcours ont été exercés dans une base isolée ; cette checklist ne constitue pas un compte-rendu de déploiement. Le 28/10, le propriétaire du projet a revu le site et accepté la recette Front comme suffisante ; aucun nouveau passage global Front n'est prévu. Les points ci-dessous signalés comme non attestés restent des limites précises de preuve, pas des tâches bloquant cette acceptation.

## Visiteur

| Étape | État vérifié | Preuve / reste à faire |
|---|---|---|
| Ouvrir l'accueil et comprendre le restaurant | Validé au navigateur local | Page servie en HTTP et navigation générale vérifiées lors de la recette locale. |
| Ouvrir la carte et voir plats et menus | Validé dans le navigateur le 27/10 | Affichage des catégories, plats, prix et trois menus vérifié sur le serveur Front local. |
| Ouvrir la galerie et voir les photos et leurs titres | Validé dans le navigateur le 27/10 | Neuf photos présentes ; les images et libellés figurent dans l'arbre d'accessibilité. |
| Depuis la galerie, suivre le bouton de réservation | Validé au navigateur local | Le bouton ouvre le parcours de réservation. |
| Choisir date et couverts, consulter les disponibilités | Validé dans le navigateur le 27/10 | Choix de deux couverts et d'une date ouverte : créneaux affichés de 15 minutes, du midi et du soir. Tests API couvrent aussi jour fermé et capacité. |
| Sans session, tenter de confirmer une réservation | Validé dans le navigateur le 27/10 | Après sélection d'un créneau, la page demande de se connecter ou créer un compte. Aucune réservation n'a été créée pendant ce contrôle. |

## Client

| Étape | État vérifié | Preuve / reste à faire |
|---|---|---|
| Créer un compte et se connecter | Validé dans le navigateur sur SQLite jetable | Connexion du compte client de démonstration testée : redirection effective vers `espace-client.html`, profil chargé et aucun accès administrateur affiché. Le code envoie les comptes admin vers le tableau de bord ; les clients vont vers leur espace, sauf destination interne autorisée explicitement demandée, dont la réservation. Aucun compte réel ni aucune donnée MySQL utilisés. |
| Préremplir convives et allergies dans le formulaire | Validé dans le navigateur sur SQLite jetable | Les préférences du profil de démonstration ont été enregistrées puis reprises dans le formulaire. |
| Créer une réservation et rejoindre l’espace client | Validé dans le navigateur isolé | Après une réponse de création réussie, la page réservation redirige directement vers `espace-client.html?reservation=confirmee`. Le tableau de bord affiche le message de confirmation et recharge les réservations. Le parcours a été rejoué après la correction ; aucun nom de compte ni identifiant de réservation n'est conservé ici. |
| Consulter et modifier son profil et sa réservation | Validé dans le navigateur le 27/10, couverture API présente | Profil modifié et réservation déplacée ; l’espace client affiche les nouvelles valeurs. Le bloc de session a été corrigé : connecté, il n’affiche que « Se déconnecter » ; sans session, il affiche « Se connecter ». |
| Se déconnecter depuis l’espace client | Validé dans le navigateur de recette | Le bouton appelle `/api/logout`, retire le jeton du navigateur puis ramène à Connexion avec « Vous êtes déconnecté(e). ». Le bouton de déconnexion ne s'affiche plus après la déconnexion. Le endpoint de révocation est aussi couvert par PHPUnit. |
| Supprimer une réservation | API et tests automatisés validés ; interface client non attestée | La suppression depuis l'espace client n'a pas été rejouée dans le navigateur ; ne pas la présenter comme validée manuellement. |
| Supprimer le compte | API et PHPUnit validés ; confirmation navigateur non attestée | La confirmation native n'a pas été validée dans le navigateur ; mentionner cette limite. |

## Administrateur

| Étape | État vérifié | Preuve / reste à faire |
|---|---|---|
| Se connecter avec un compte administrateur | Validé dans le navigateur sur SQLite jetable | Le compte fixture de recette permet la connexion et le rôle admin est reconnu. Aucun compte administrateur réel n'a été créé. Initialisation par `php bin/console app:create-admin` à faire avec les identifiants choisis par l'exploitant. |
| Connexion admin et accès aux pages | Validé dans le navigateur sur SQLite jetable | Le rôle admin est reconnu, la connexion redirige vers le tableau de bord, le lien « Connexion administrateur » est masqué après authentification sur les pages admin, et les liens « Tableau de bord » ramènent au bon écran. Le bloc latéral affiche le nom du compte et le bouton « Déconnexion » révoque le jeton. |
| Modifier horaires et capacité | Validé dans le navigateur le 27/10 sur SQLite jetable | La capacité a été enregistrée avec une valeur temporaire, puis remise à la valeur initiale ; message de succès vérifié. |
| Gérer catégories, plats et menus | Validé dans le navigateur le 27/10 sur SQLite jetable | Édition d’une catégorie, puis création d’une catégorie, d’un plat lié et d’un menu lié vérifiées. Les formulaires vides et le menu sans catégorie sont refusés avec la validation native. Les entrées de démonstration restent uniquement dans la base jetable. |
| Gérer les photos | Validé dans le navigateur le 27/10 sur SQLite jetable | Ajout de `terrasse.png` avec un titre, affichage du message de succès et de la vignette, puis suppression avec message « Photo supprimée ». Le premier envoi échouait car la limite PHP du serveur temporaire était de 2 Mo alors que l’application accepte 3 Mo ; le serveur temporaire a été relancé avec une limite cohérente. La photo de test a ensuite été supprimée ; la base isolée contient de nouveau les neuf photos de départ. `SettingsAndGalleryApiTest` couvre aussi l’upload, le remplacement et le nettoyage (4 tests, 22 assertions). |
| Filtrer et modifier des réservations | Validé dans le navigateur le 27/10 sur SQLite jetable | Filtre par date avec une seule réservation attendue, puis message « Réservation modifiée » vérifiés. Les premiers états DOM observés étaient périmés car ils précédaient le rafraîchissement asynchrone ; l'API puis la liste actualisée confirment le filtre correct. |
| Supprimer une réservation depuis l’admin | Validé dans le navigateur sur SQLite jetable | Une réservation de démonstration a été supprimée manuellement avec une seule confirmation. Le message « Réservation supprimée » est apparu et la ligne a disparu. Après l'essai, la base isolée ne contenait plus de réservation ; la base MySQL du projet n’a pas été utilisée. |
| Déconnexion et accès sans jeton | Validé dans le navigateur le 27/10 sur SQLite jetable | Le bouton « Se déconnecter de cette session » confirme la déconnexion. Après accès direct au Tableau de bord, à Carte & menus et à Galerie, les outils sont masqués et le lien « Connexion administrateur » apparaît. Le lien du menu conduit d’abord à la page de connexion, où se trouve le bouton de déconnexion effectif. |
| Rejet des réglages invalides | Validé dans le navigateur le 27/10 sur SQLite jetable | La capacité zéro est bloquée par le navigateur ; un service trop court est rejeté par l’API avec un message visible. La capacité et les horaires ont été remis à leurs valeurs initiales puis enregistrés. |
| Apparence des boutons admin | Corrigée et vérifiée visuellement le 27/10 | Les boutons créés dynamiquement n’avaient pas les classes CSS `.button`. Les actions Enregistrer/Modifier/Supprimer et Nouveau sont maintenant stylées ; Enregistrer est doré et les actions secondaires utilisent le contour. |
| Bloc « Gestion des contenus » | Corrigé et vérifié en mise en page bureau le 27/10 | Deux liens-boutons de même largeur sont alignés sur deux colonnes ; le mobile repasse sur une colonne. |

## Comment rejouer la recette

1. Démarrer la stack Docker (voir le [README](../../README.md)) et ouvrir `http://localhost:82`. Dans l’onglet Réseau, vérifier que les appels partent vers `localhost:82/api/…`.
2. Partir d’une base Docker jetable : migrations puis fixtures `quai-antique-demo`. Ne jamais charger les fixtures dans une base contenant des données à conserver.
3. Utiliser des données de test reconnaissables. Pour chaque étape, noter la date, le rôle, la page, l’action, le message visible et le résultat ; joindre une capture pour les étapes clés.
4. En cas d’échec, corriger puis rejouer uniquement l’étape concernée, en notant le nouveau commit.
5. Les tests PHPUnit restent le contrôle répétable de l’API ; la recette navigateur vérifie la liaison entre pages, JavaScript, API et retours visibles.

## Résultat actuel

La recette admin est validée sur une base SQLite isolée : authentification, navigation, réglages, catalogue, galerie, filtres, modification et suppression d’une réservation. Les formulaires invalides sont rejetés. L’upload et la suppression d’une photo ont été exécutés manuellement dans le navigateur ; la suppression d’une réservation admin a affiché le message de réussite après une confirmation. Après nettoyage, la base isolée contenait zéro réservation et les neuf photos initiales. La suite globale alors rapportée passait (40 tests, 334 assertions), dont les tests d’upload, remplacement et révocation de jeton. Le parcours client connexion, redirection, profil, réservation et déconnexion a été contrôlé dans le navigateur. Restent à attester manuellement dans l’espace client la suppression d’une réservation et la confirmation UI de suppression du compte. Aucun compte administrateur réel n’a été créé ; il faudra le provisionner avant la mise en ligne. La nécessité de user stories supplémentaires reste à confirmer auprès de la formation.
