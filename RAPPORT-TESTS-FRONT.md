# Rapport de tests Front — Quai Antique

Date d'exécution : 12 octobre 2026

## Environnement

- Projet : Quai Antique Front
- URL de test : `http://localhost:8765/src/index.html`
- Serveur utilisé : serveur HTTP local
- Navigateur : navigateur intégré Codex
- Viewports observés : 1410 x 1344 px et 390 x 844 px
- Commit testé : `952cb3b`
- API Symfony : non branchée
- Docker : non exécuté pendant cette session, Docker Desktop n'étant pas démarré

## Résumé

| Domaine | Résultat |
|---|---|
| Chargement de la page | Conforme |
| Navigation principale | Conforme |
| Galerie et dialogue | Conforme |
| Filtre de carte | Conforme |
| Formulaire vide | Conforme |
| Formulaire valide | Conforme en mode démonstration |
| Connexion | Conforme en mode simulé |
| Console navigateur | Conforme, aucune erreur ou alerte |
| Responsive réel | Conforme sur viewport desktop et mobile observés |
| Appel API réel | Non testable avant le Back |
| Docker | À refaire avec Docker Desktop démarré |

## Tests exécutés

| ID | Scénario | Résultat obtenu | Statut |
|---|---|---|---|
| T01 | Charger l'accueil | Hero, navigation, image locale et actions visibles | Conforme |
| T02 | Vérifier les sections | Accueil, galerie, carte, réservation, connexion, inscription, compte et administration présentes | Conforme |
| T03 | Ouvrir la galerie | Le dialogue affiche l'image et le titre « La salle d'accueil » | Conforme |
| T04 | Fermer la galerie | Le dialogue se ferme avec le bouton prévu | Conforme |
| T05 | Filtrer « Entrées » | Les entrées sont affichées et le plat n'est pas affiché | Conforme |
| T06 | Filtrer « Plats » | « Fondue du Quai » est affichée | Conforme |
| T07 | Envoyer la réservation vide | Message général et erreurs par champ | Conforme |
| T08 | Vérifier l'erreur e-mail | « Indiquez une adresse e-mail valide. » | Conforme |
| T09 | Vérifier la date et les couverts | Les règles de date future et de 1 à 12 couverts sont appliquées | Conforme par le code et le scénario de validation |
| T10 | Envoyer une réservation valide | Chargement, puis confirmation de démonstration | Conforme en mode démonstration |
| T11 | Tester la connexion vide | Message d'erreur sur e-mail et mot de passe | Conforme |
| T12 | Tester la connexion avec données valides | Message de connexion simulée | Conforme en mode simulé |
| T13 | Examiner la console | Aucun message `error` ou `warn` | Conforme |
| T14 | Afficher le viewport mobile 390 px | Hero lisible, boutons empilés, contenu sans débordement visible | Conforme |
| T15 | Ouvrir le menu mobile | Le bouton Menu affiche les 5 liens de navigation | Conforme |

## Preuves visuelles à conserver

Les captures à intégrer au dossier de preuves sont :

1. Accueil desktop avec navigation et bouton de réservation.
2. Galerie ouverte dans le dialogue.
3. Carte filtrée sur « Plats ».
4. Formulaire de réservation vide avec erreurs.
5. Formulaire valide avec message de démonstration.
6. Connexion avec message d'erreur.
7. Connexion avec message de démonstration.
8. Affichage mobile avec menu burger.
9. Réservation mobile.
10. Projet servi avec Docker sur `localhost:82`.

## Tests à terminer avant de commencer le Back

### Responsive tablette

Le téléphone 390 px a été vérifié. Il reste à refaire T01, T03, T05 et T07 en
largeur tablette. Vérifier que :

- le menu devient un bouton ;
- les sections s'empilent ;
- aucun contenu ne déborde horizontalement ;
- les champs restent utilisables au doigt ;
- les boutons restent visibles et lisibles.

### Docker

Avec Docker Desktop démarré :

```bash
docker compose up --build
```

Puis ouvrir `http://localhost:82` et refaire au minimum T01, T03, T05 et T07.

### API

Ces tests attendent le Back :

- réponse JSON positive ;
- réservation refusée car créneau complet ;
- erreur serveur ;
- affichage dynamique de la carte ;
- affichage dynamique de la galerie ;
- connexion réelle ;
- récupération des réservations du client.

## Conclusion

Le Front est fonctionnel pour les comportements autonomes du navigateur. Les
tests de validation, de galerie, de filtre, de connexion simulée et de
réservation simulée sont conformes. Il reste à refaire les tests dans un
viewport mobile, à valider le conteneur Docker et à connecter l'API Symfony.
Ces derniers points ne doivent pas être présentés comme validés avant leur
exécution réelle.
