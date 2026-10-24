# Quai Antique — Rapport de tests Front

**Date de l'audit :** 19 octobre 2026
**Périmètre :** les quatre premières leçons du projet fil rouge Front.
**Environnement :** navigateur, serveur HTTP local et maquettes finales Figma.

## Objectif

Vérifier que l'interface visible de Quai Antique est structurée, responsive et interactive avant le branchement du Back Symfony. Une réussite dans ce rapport ne signifie jamais qu'une donnée a été enregistrée en base : les réservations et connexions testées sont explicitement des simulations Front.

## État des leçons

| Leçon | État | Preuve présente dans le projet |
|---|---|---|
| 1. Modéliser la partie Front depuis un brief | Validée | Brief, parcours, wireframes, design system et mockups finaux disponibles dans Figma ; écrans correspondants créés dans le dépôt. |
| 2. Réaliser la partie Front responsive | Validée techniquement | 14 pages vérifiées à 390 px, 768 px et 1440 px sans débordement horizontal. |
| 3. Réaliser l'interface utilisateur | Validée avec contrôle visuel à poursuivre | Pages, navigation, titres, contenus, footer, liens d'évitement, tableau de bord et quatre écrans de gestion issus de mockups Figma présents. La comparaison pixel par pixel avec Figma reste une revue visuelle distincte. |
| 4. Dynamiser la partie Front | Validée côté navigateur | Menu mobile, modale galerie, validation immédiate des champs de compte, validation à l'envoi et modale d'annulation testés ; les résultats sont annoncés comme simulés. |

## Périmètre des pages

Les pages testées sont : accueil, galerie, carte, réservation, connexion, espace client, administration, contact, mentions légales, confidentialité, CGU/CGV, modification de réservation, gestion de galerie et gestion de carte.

## Cas de test exécutés

| ID | Action | Résultat attendu | Résultat obtenu | Statut |
|---|---|---|---|---|
| T01 | Ouvrir les 14 pages | Une page possède une langue française, un titre principal, un contenu principal et un footer. | Les 14 pages comportent `lang="fr"`, un `<h1>`, un `<main>` et un footer. | Validé |
| T02 | Afficher les 14 pages à 390 px | Aucun défilement horizontal ; interface mobile utilisable. | Aucun débordement horizontal constaté. Les nouveaux écrans de gestion affichent un formulaire ou une liste de 358 px dans une fenêtre de 390 px. | Validé |
| T03 | Afficher les 14 pages à 768 px | Aucun défilement horizontal ; mise en page tablette cohérente. | Aucun débordement horizontal constaté, y compris pour les trois écrans de gestion. | Validé |
| T04 | Afficher les 14 pages à 1440 px | Aucun défilement horizontal ; mise en page desktop lisible. | Aucun débordement horizontal constaté, y compris pour les trois écrans de gestion. | Validé |
| T05 | Ouvrir le menu sur mobile | Le bouton Menu ouvre la navigation et met à jour son état. | Les quatre liens sont visibles et `aria-expanded` passe à `true`. | Validé |
| T06 | Ouvrir une image de la galerie | Une modale affiche l'image sélectionnée ; elle peut être fermée. | La galerie contient neuf cartes ; l'ouverture de la modale a été confirmée sur une carte. | Validé |
| T07 | Envoyer la réservation sans information | Un message demande de corriger les champs ; aucune réservation réelle n'est créée. | Message affiché : « Vérifiez les champs signalés. » | Validé |
| T08 | Envoyer une réservation complète | Une confirmation de démonstration est affichée. | Message affiché : « Créneau enregistré en mode démonstration. » | Validé Front |
| T09 | Envoyer la connexion vide puis complète | Une erreur est affichée puis une confirmation de démonstration. | Les deux messages attendus sont affichés. | Validé Front |
| T10 | Contrôler les liens d'évitement | Chaque page propose « Aller au contenu » vers `#contenu`. | Les 11 pages ont le lien et la cible `<main id="contenu">`. | Validé |
| T11 | Consulter les erreurs navigateur | Aucune erreur JavaScript bloquante. | Aucune erreur ni alerte détectée sur la page contrôlée après les parcours de test. | Validé |
| T12 | Comparer chaque page avec Figma | Les écrans doivent correspondre aux mockups finaux. | Revue visuelle à effectuer écran par écran ; ne pas déclarer une correspondance pixel parfaite avant cette étape. | À poursuivre |
| T13 | Enregistrer la modification d'une réservation | Le formulaire conserve son rôle de démonstration et affiche un retour clair. | Message affiché : « Modification vérifiée en mode démonstration. Symfony enregistrera ensuite les données après contrôle des droits. » | Validé Front |
| T14 | Demander puis confirmer l'annulation d'une réservation | Une modale demande confirmation ; aucune donnée réelle n'est effacée. | La modale « Annuler cette réservation ? » s'ouvre. Après confirmation, le message indique qu'aucune donnée n'a été supprimée. | Validé Front |
| T15 | Ouvrir la gestion de galerie et de carte | Les écrans sont accessibles depuis l'administration et leurs actions informent du mode démonstration. | Les liens de navigation atteignent les deux écrans. Les actions « Ajouter », « Modifier » et « Supprimer » affichent le message de préparation Symfony. | Validé Front |

## Limites assumées — dépend du Back Symfony

- authentification réelle et gestion de session ;
- mot de passe chiffré et droits administrateur ;
- disponibilité réelle des créneaux ;
- création, modification et annulation persistées ;
- données réelles de l'espace client et de l'administration ;
- contrôles de sécurité côté serveur.

## Décisions Front à expliquer au jury

- **Pas de Bootstrap :** les maquettes imposent une identité et des espacements précis. Le projet utilise donc CSS Grid pour les grilles et Flexbox pour les alignements. Bootstrap aurait été possible, notamment pour un prototype ou un back-office standard, mais aurait ajouté des styles génériques à adapter.
- **Site multipage plutôt que mini-routeur JavaScript :** chaque lien mène vers une vraie page HTML. C'est cohérent pour un Front statique, assure naturellement l'URL et l'historique du navigateur, et préparera la migration vers les routes Symfony. Un mini-routeur SPA n'est pas nécessaire ici.
- **Galerie mobile à une colonne :** le support de cours illustre une grille responsive générique, mais les mockups Figma finaux de Quai Antique montrent une colonne verticale sur mobile et trois colonnes sur desktop. Le code suit donc la maquette finale, qui reste la référence du projet.
- **Pas de loader ni d'animation décorative :** ce choix est volontaire. Le site charge sans attente significative ; ajouter une animation uniquement pour en avoir une ne rendrait pas le parcours plus utile. Les interactions qui ont une fonction sont conservées : menu mobile, ouverture/fermeture de la galerie et retours de validation.
- **Validation Front sans fausse sécurité :** les champs de compte sont contrôlés avec `input`, les listes et dates avec `change`, et le formulaire est revérifié avec `submit`. Cela améliore l'expérience utilisateur. Symfony devra répéter toutes ces règles côté serveur.
- **Pas de faux cookie, token, rôle ou garde de route JavaScript :** ils seraient contournables et seraient remplacés par Symfony. L'authentification, les rôles client/administrateur, le hachage du mot de passe, CSRF et l'autorisation doivent être imposés côté serveur.
- **Administration préparée, non persistée :** le tableau de bord et les boutons « Modifier » / « Gérer » représentent le parcours prévu par les mockups. Ils affichent un retour de démonstration ; les écrans détaillés, données et opérations CRUD seront réellement reliés par Symfony, car ils n'existent pas comme écrans séparés dans les mockups finaux.
- **Interfaces de gestion complémentaires :** après conception des mockups, le Front comprend un formulaire de modification de réservation, une confirmation d'annulation par modale, une gestion de galerie et une gestion de carte/menu. Les messages précisent « mode démonstration » : Symfony devra contrôler l'identité, les droits, les règles métier et la persistance avant toute opération réelle.

## Captures à conserver pour la preuve jury

Ajouter dans le dossier de preuve, après une démonstration manuelle, une capture par famille de test :

1. accueil à 1440 px ;
2. menu ouvert à 390 px ;
3. galerie avec modale ouverte ;
4. réservation invalide ;
5. réservation en mode démonstration ;
6. lien « Aller au contenu » affiché après appui sur la touche Tab ;
7. comparaison d'un écran Figma et de sa page web correspondante.

## Formulation pour l'oral

« J'ai séparé le contrôle de l'interface et le contrôle des données. Avant Symfony, j'ai vérifié les onze pages à trois largeurs d'écran, le menu mobile, la galerie et les validations de formulaires. Les messages indiquent volontairement le mode démonstration : le Front améliore le parcours utilisateur, mais le Back devra ensuite vérifier les droits, la disponibilité et enregistrer les données. J'ai aussi ajouté un lien d'évitement sur chaque page afin qu'une navigation au clavier puisse accéder directement au contenu principal. »
