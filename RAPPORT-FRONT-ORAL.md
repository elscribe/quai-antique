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
| 2. Réaliser la partie Front responsive | Validée techniquement | 11 pages vérifiées à 390 px, 768 px et 1440 px sans débordement horizontal. |
| 3. Réaliser l'interface utilisateur | Validée avec contrôle visuel à poursuivre | Pages, navigation, titres, contenus, footer et liens d'évitement présents. La comparaison pixel par pixel avec Figma reste une revue visuelle distincte. |
| 4. Dynamiser la partie Front | Validée côté navigateur | Menu mobile, modale galerie et validations JavaScript testés ; les résultats sont annoncés comme simulés. |

## Périmètre des pages

Les pages testées sont : accueil, galerie, carte, réservation, connexion, espace client, administration, contact, mentions légales, confidentialité et CGU/CGV.

## Cas de test exécutés

| ID | Action | Résultat attendu | Résultat obtenu | Statut |
|---|---|---|---|---|
| T01 | Ouvrir les 11 pages | Une page possède une langue française, un titre principal, un contenu principal et un footer. | Les 11 pages comportent `lang="fr"`, un `<h1>`, un `<main>` et un footer. | Validé |
| T02 | Afficher chaque page à 390 px | Aucun défilement horizontal ; interface mobile utilisable. | Aucun débordement horizontal constaté sur les 11 pages. | Validé |
| T03 | Afficher chaque page à 768 px | Aucun défilement horizontal ; mise en page tablette cohérente. | Aucun débordement horizontal constaté sur les 11 pages. | Validé |
| T04 | Afficher chaque page à 1440 px | Aucun défilement horizontal ; mise en page desktop lisible. | Aucun débordement horizontal constaté sur les 11 pages. | Validé |
| T05 | Ouvrir le menu sur mobile | Le bouton Menu ouvre la navigation et met à jour son état. | Les quatre liens sont visibles et `aria-expanded` passe à `true`. | Validé |
| T06 | Ouvrir une image de la galerie | Une modale affiche l'image sélectionnée ; elle peut être fermée. | La galerie contient neuf cartes ; l'ouverture de la modale a été confirmée sur une carte. | Validé |
| T07 | Envoyer la réservation sans information | Un message demande de corriger les champs ; aucune réservation réelle n'est créée. | Message affiché : « Vérifiez les champs signalés. » | Validé |
| T08 | Envoyer une réservation complète | Une confirmation de démonstration est affichée. | Message affiché : « Créneau enregistré en mode démonstration. » | Validé Front |
| T09 | Envoyer la connexion vide puis complète | Une erreur est affichée puis une confirmation de démonstration. | Les deux messages attendus sont affichés. | Validé Front |
| T10 | Contrôler les liens d'évitement | Chaque page propose « Aller au contenu » vers `#contenu`. | Les 11 pages ont le lien et la cible `<main id="contenu">`. | Validé |
| T11 | Consulter les erreurs navigateur | Aucune erreur JavaScript bloquante. | Aucune erreur ni alerte détectée sur la page contrôlée après les parcours de test. | Validé |
| T12 | Comparer chaque page avec Figma | Les écrans doivent correspondre aux mockups finaux. | Revue visuelle à effectuer écran par écran ; ne pas déclarer une correspondance pixel parfaite avant cette étape. | À poursuivre |

## Limites assumées — dépend du Back Symfony

- authentification réelle et gestion de session ;
- mot de passe chiffré et droits administrateur ;
- disponibilité réelle des créneaux ;
- création, modification et annulation persistées ;
- données réelles de l'espace client et de l'administration ;
- contrôles de sécurité côté serveur.

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
