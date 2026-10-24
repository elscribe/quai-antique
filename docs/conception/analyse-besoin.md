# Analyse du besoin

## Besoin principal

Présenter le restaurant Quai Antique et permettre au visiteur de se diriger
rapidement vers les informations importantes : galerie, carte, menus et
réservation.

## Utilisateurs

- visiteur non connecté ;
- client qui souhaite réserver ;
- administrateur qui gérera les informations du restaurant, la galerie, la
  carte, les menus et les réservations.

## Fonctionnalités visibles dans la maquette

- navigation principale ;
- page d'accueil ;
- aperçu galerie ;
- accès à la carte ;
- accès à la réservation ;
- accès au compte ou à la connexion ;
- écrans de principe pour l'inscription, l'espace client et l'administration.

## Contenus à prévoir

- présentation courte du restaurant ;
- mise en avant des produits frais ;
- galerie de photos avec titres visibles au survol ;
- catégories de plats ;
- plats avec titre, description et prix ;
- menus avec titre, description et prix ;
- bloc réservation ;
- horaires d'ouverture des services ;
- nombre maximum de convives ;
- adresse et contact ;
- images pour l'ambiance et la galerie.

## Contraintes de conception

- ne pas surcharger l'accueil ;
- garder des boutons d'action visibles ;
- prévoir une navigation simple ;
- conserver une structure réutilisable sur les autres pages ;
- faire comprendre que la réservation peut nécessiter une connexion ;
- préparer les futures données dynamiques.

## Choix responsive

Mobile : les blocs sont empilés et le menu passe en bouton.

Desktop : les contenus peuvent être affichés en grille, avec une galerie plus
large et une navigation complète.

## Choix d'accessibilité

- textes alternatifs sur les images ;
- bouton de menu avec `aria-expanded` ;
- liens et boutons lisibles ;
- titres de sections structurés ;
- états visibles pour les formulaires ;
- messages clairs quand une réservation nécessite une connexion.
