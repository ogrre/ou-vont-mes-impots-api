# Plan projet — Mais où vont mes impôts ?

Ce fichier donne à Codex et aux contributeurs le contexte opérationnel du
backend. Il complète le README et ne remplace pas la documentation détaillée
des datasets ou de l’API.

## Projet

« Mais où vont mes impôts ? » est un outil pédagogique open source qui permet
de comprendre :

1. combien dépensent les administrations publiques ;
2. où vont ces dépenses, notamment par fonction COFOG ;
3. quelles administrations dépensent ;
4. comment le budget de l’État se détaille jusqu’à l’action et la sous-action ;
5. d’où viennent les recettes ;
6. quelle est la source et la qualité de chaque chiffre.

Le frontend Vue est dans le dépôt voisin `../ou-vont-mes-impots-front`. Ce
dépôt est le backend Laravel et doit rester indépendant du frontend.

## Stack et commandes essentielles

- PHP 8.4, Laravel 13, PostgreSQL 16 ;
- Docker Compose local : `docker-compose-dev.yml` ;
- OpenAPI : Scramble (`/docs/api`, `/docs/api.json`) ;
- qualité : PHPUnit, Pint, PHPStan/Larastan, Infection.

Commandes locales recommandées :

```bash
docker compose -f docker-compose-dev.yml up -d --build
docker compose -f docker-compose-dev.yml exec app composer install
docker compose -f docker-compose-dev.yml exec app php artisan migrate --seed
docker compose -f docker-compose-dev.yml exec app composer test
docker compose -f docker-compose-dev.yml exec app composer analyse
docker compose -f docker-compose-dev.yml exec app ./vendor/bin/pint --test
docker compose -f docker-compose-dev.yml exec app composer test:mutation
```

Pour une boucle plus rapide, la configuration CI utilise Infection avec la
sélection des tests couvrant les classes mutées (`--map-source-class-to-test`
et `--only-covering-test-cases`). La commande exacte de la CI est la référence
pour la validation finale.

## Architecture métier

Le modèle canonique suit cette chaîne :

```text
Source
  → Dataset → DatasetFile → ImportBatch
                         → FinancialObservation
                               ↘ AccountingScope
                               ↘ Classification → ClassificationItem
```

Les observations portent notamment l’année, le montant, la mesure, la base
comptable, le stade budgétaire, la consolidation, le statut d’import et les
métadonnées de provenance.

Les services importants sont :

- `PublicFinanceQuery` : vues globales, COFOG, historique, recherche et budget
  de l’État ;
- `StateExpenditureQuery` : dépenses budgétaires par mission/ministère/nature ;
- `StateRevenueQuery` : recettes budgétaires avec agrégats et déductions ;
- `HomePagePresenter` : contrat éditorial de `/home/{year}` ;
- `app/Services/Imports` : importeurs officiels ;
- `app/Services/Rap` : téléchargement, extraction et contrôle des RAP.

## Contrats API

Toutes les routes publiques sont sous `/api/v1` et en lecture seule. La liste
actuelle est documentée dans [`docs/api.md`](docs/api.md) et vérifiable avec :

```bash
php artisan route:list --path=api/v1
```

Routes centrales :

- `/years`, `/sources`, `/home/{year}`, `/overview/{year}` ;
- `/history`, `/search`, `/methodology` ;
- `/categories/...`, `/cofog/{year}/{category}` ;
- `/budget-state/{year}/...` pour mission → programme → action → sous-action ;
- `/state-expenditure`, `/state-revenue` pour les contrats budgétaires plats.

OpenAPI est généré depuis les routes, les Form Requests et les attributs ou
descriptions Scramble. Une modification de contrat doit mettre à jour les
tests HTTP et `docs/api.md`, puis vérifier `/docs/api.json`.

## Données disponibles et limites

- INSEE T_3201 et tables associées : comptes nationaux et séries historiques ;
- INSEE T_3301 à T_3307 : dépenses fonctionnelles COFOG, notamment le détail
  2024 ;
- RAP/PLRG 2024 : budget de l’État jusqu’aux actions et sous-actions ;
- recettes budgétaires de l’État 2024 : hiérarchie catégorie → section → ligne ;
- certaines distributions institutionnelles sont `review_required` lorsque le
  total sectoriel de référence n’est pas disponible ;
- une année disponible ne signifie pas que toutes les familles de données sont
  disponibles pour cette année.

Ne jamais inventer un historique mission/programme/action ou une affectation
TVA → santé. Les recettes publiques INSEE et les recettes budgétaires de l’État
PLRG sont deux périmètres distincts.

## Invariants à préserver

1. Ne jamais mélanger `national_accounts` et `budgetary` dans un total.
2. Ne jamais additionner AE et CP.
3. Ne jamais confondre LFI/budget initial et exécution.
4. Ne jamais transformer `null` en zéro.
5. Ne jamais masquer `validated`, `review_required` ou `not_importable`.
6. Ne jamais additionner une sous-action une seconde fois dans son parent.
7. Ne pas recalculer côté client un ratio fourni par l’API.
8. Chaque nouveau chiffre publié doit conserver source, dataset, période et
   contexte comptable.

## Imports et déploiement

Les référentiels et descripteurs sont créés par les seeders. Les montants sont
importés par les commandes suivantes :

```bash
php artisan dataset:import-known data
php artisan dataset:import-rap 2024
php artisan data:validate state-expenditure-2025
```

`dataset:import-known` est prévu pour les fichiers reconnus présents dans
`data/`. Les imports doivent être idempotents et rejeter les doublons par
checksum/descripteur. En production, les migrations et imports sont à exécuter
selon le workflow Dokploy documenté dans
[`docs/deployment-dokploy.md`](deployment-dokploy.md), après sauvegarde et
contrôle du statut de l’application.

## Méthode de travail Codex

Avant toute modification :

1. lire `README.md`, `docs/api.md` et les documents de données concernés ;
2. exécuter `php artisan route:list --path=api/v1` ;
3. inspecter le contrôleur, le Form Request et le service réellement utilisés ;
4. vérifier les tests existants avant de modifier un contrat ;
5. ne modifier le frontend voisin que si la tâche le demande explicitement.

Après une modification backend :

1. ajouter ou mettre à jour les tests unitaires/HTTP ;
2. mettre à jour `docs/api.md` et les attributs Scramble si le contrat change ;
3. lancer tests, PHPStan, Pint et la validation Infection de la CI ;
4. vérifier `git diff --check` ;
5. produire des commits atomiques au format `type(scope): short message` en
   anglais et proposer une PR vers `dev`, jamais directement vers `main`.

## Prochaines étapes

- renforcer la couverture mutation des services métier encore peu testés ;
- documenter et publier les statuts de couverture par année si le frontend en
  a besoin ;
- étendre les historiques uniquement avec des séries comptablement compatibles ;
- compléter les datasets officiels manquants sans dégrader la provenance ;
- maintenir la synchronisation entre le contrat API, OpenAPI et le frontend.
