# API HTTP v1

L’API est publique, sans authentification et strictement en lecture seule. Son
préfixe est `/api/v1`. La documentation interactive et la spécification
OpenAPI générée sont disponibles sur `/docs/api` et `/docs/api.json`.

Les montants monétaires sont des chaînes décimales en EUR. Une valeur `null`
signifie qu’elle est absente, non calculable ou non importable ; elle ne doit
jamais être convertie en `0` par un client.

## Principes comptables

Les réponses exposent leur contexte lorsqu’il est connu :

- `accounting_basis` : `national_accounts` ou `budgetary` ;
- `scope` : périmètre institutionnel ou budgétaire ;
- `measurement_type` : dépense, recette, CP ou autre mesure ;
- `stage` : exécution ou budget initial ;
- `consolidation` : consolidé ou non consolidé ;
- `quality` / `quality_status` : `validated`, `review_required` ou
  `not_importable` ;
- `provenance` / `source` : source, dataset, URL et page lorsque disponibles.

Les AE (`commitment_authorization`) et les CP (`payment_credit`) sont des
mesures différentes. Pour répondre à « ce qui a réellement été payé », le
client doit utiliser les CP exécutés. Le budget de l’État n’est jamais présenté
comme l’ensemble des dépenses des administrations publiques.

## Version et disponibilité

### `GET /api/v1/version`

Retourne le nom public de l’application, la version applicative et la version
du contrat API.

### `GET /api/v1/years`

Retourne les années possédant au moins une observation issue d’un import
terminé :

```json
{ "years": [1978, 1979, 2024, 2025] }
```

Cette route ne garantit pas que tous les blocs existent pour chaque année. Le
client doit consulter `quality`, `coverage` et les valeurs `null` du bloc
concerné.

### `GET /api/v1/sources`

Retourne les datasets publiés par les imports terminés, avec leur organisme,
base comptable, périmètre et première/dernière année connues.

## Vues globales

### `GET /api/v1/home/{year}`

Retourne le payload éditorial de la page d’accueil. Il est construit à partir
de la vue d’ensemble et contient notamment les blocs de dépenses publiques,
la distribution institutionnelle, le COFOG, le zoom budget de l’État, les
recettes et la méthodologie.

### `GET /api/v1/overview/{year}`

Retourne les blocs canoniques suivants :

| Bloc | Périmètre | Base comptable |
| --- | --- | --- |
| `public_finances` | APU | `national_accounts` |
| `institutional_distribution` | APU par secteur | `national_accounts` |
| `functional_distribution` | APU par fonction COFOG | `national_accounts` |
| `state_budget` | Budget de l’État | `budgetary` |
| `revenues.public_revenues` | Recettes APU | `national_accounts` |
| `revenues.state_budget_revenues` | Recettes budgétaires de l’État | `budgetary` |

Les blocs indisponibles conservent leur contexte mais renvoient `amount: null`,
`items: []` et une qualité `not_importable`.

### `GET /api/v1/methodology`

Expose les règles pédagogiques utilisées par le frontend : séparation des
recettes et dépenses, séparation AE/CP, et séparation entre comptes nationaux
et comptabilité budgétaire.

## Historique

### `GET /api/v1/history`

Paramètres :

| Paramètre | Obligatoire | Valeurs |
| --- | --- | --- |
| `metric` | oui | `expenditure`, `revenue`, `tax`, `social_contribution`, `deficit`, `debt` |
| `from` | oui | entier entre 1949 et 2200 |
| `to` | oui | entier entre `from` et 2200 |
| `classification` | non | code de classification canonique |
| `category` | non | slug ou code d’une catégorie |
| `scope` | non | code de périmètre comptable |
| `accounting_basis` | non | `national_accounts`, `budgetary` |

Exemple :

```http
GET /api/v1/history?metric=expenditure&accounting_basis=national_accounts&from=1978&to=2024
```

Réponse :

```json
{
  "metric": "expenditure",
  "from": 1978,
  "to": 2024,
  "accounting_basis": "national_accounts",
  "items": [
    { "year": 2023, "amount": "1680000000000.00" },
    { "year": 2024, "amount": "1700000000000.00" }
  ]
}
```

L’API renvoie actuellement des montants annuels. Elle ne doit pas être utilisée
pour mélanger des séries ayant des bases comptables, périmètres ou
classifications incompatibles.

## Recherche globale

### `GET /api/v1/search`

Recherche dans les `ClassificationItem` canoniques, sans charger toutes les
actions côté client. La recherche est insensible à la casse et aux accents et
porte sur le code, le libellé officiel et `raw_label`.

Paramètres :

| Paramètre | Obligatoire | Valeurs |
| --- | --- | --- |
| `q` | oui | chaîne de 2 à 120 caractères |
| `year` | non | entier entre 1949 et 2200 |
| `scope` | non | code de classification |
| `types` | non | types séparés par des virgules |
| `limit` | non | entier de 1 à 50, défaut 20 |

Les types actuellement retournés sont `mission`, `programme`, `action`,
`sub_action`, `cofog`, `revenue` et `classification`, selon la classification
de l’élément. Les résultats sont triés par pertinence :

```json
{
  "query": "enseignement",
  "year": 2024,
  "items": [
    {
      "type": "mission",
      "code": "EDU",
      "label": "Enseignement scolaire",
      "year": 2024,
      "scope": "state_budget_programme_action",
      "classification": "state_budget_programme_action",
      "parent": null,
      "breadcrumb": [
        { "type": "mission", "code": "EDU", "label": "Enseignement scolaire" }
      ],
      "amount": "...",
      "quality_status": "validated"
    }
  ]
}
```

La réponse ne contient pas de route frontend. Le frontend construit sa
navigation à partir de `type`, `code`, des parents et de l’année.

## Classifications et COFOG

### `GET /api/v1/categories/{classification}`

Retourne les catégories racines d’une classification : `id`, `code`, `slug`,
`name`, `description` et `parent_id`.

### `GET /api/v1/categories/{classification}/{category}/children`

Retourne les enfants d’une catégorie identifiée par son slug ou son code.

### `GET /api/v1/cofog/{year}/{category}`

Retourne une fonction COFOG et ses sous-fonctions, avec description pédagogique,
montant, pourcentage, qualité et provenance. Exemple :

```http
GET /api/v1/cofog/2024/GF10
```

Les sous-fonctions sont déjà comprises dans le montant de leur fonction parente
et ne doivent pas être additionnées une seconde fois côté client.

Les catégories COFOG utilisent `national_accounts`, le périmètre
`general_government`, une mesure de dépense, le stade d’exécution et des
données consolidées lorsqu’elles sont disponibles.

## Budget de l’État

Les routes `budget-state` concernent le budget de l’État, et non l’ensemble des
administrations publiques. Le modèle hiérarchique est :

```text
Mission → Programme → Action → Sous-action
```

### Routes de détail

```http
GET /api/v1/budget-state/{year}/missions
GET /api/v1/budget-state/{year}/missions/{mission}
GET /api/v1/budget-state/{year}/programmes/{programme}
GET /api/v1/budget-state/{year}/programmes/{programme}/actions
GET /api/v1/budget-state/{year}/actions/{action}
```

Les nœuds contiennent notamment `code`, `label`, `hierarchy_level`, `ae`, `cp`,
`children`, `quality` et `provenance`. Chaque mesure contient `lfi` et
`execution`. Les actions sont recherchées par code ou slug ; le code action
doit donc être utilisé avec son programme dans le contexte frontend lorsqu’une
ambiguïté est possible.

### Routes de distribution

```http
GET /api/v1/budget-state/{year}/distribution
GET /api/v1/budget-state/{year}/missions/{mission}/distribution
GET /api/v1/budget-state/{year}/programmes/{programme}/distribution
```

Paramètres :

| Paramètre | Défaut | Valeurs |
| --- | --- | --- |
| `measurement` | `payment_credit` | `payment_credit`, `commitment_authorization` |
| `stage` | `executed` | `executed`, `initial_budget` |
| `unit` | `per_100` | `amount`, `percent`, `per_100` |

Chaque item fournit `amount`, `percent`, `per_100`, `quality_status`, `quality`
et `provenance`. Les sous-actions ou lignes exclues ne doivent pas être
réintroduites dans un total parent déjà calculé par l’API.

## Dépenses et recettes budgétaires

### `GET /api/v1/state-expenditure`

Paramètres :

| Paramètre | Défaut | Valeurs |
| --- | --- | --- |
| `year` | `2025` | entier entre 2000 et 2100 |
| `classification` | `mission` | `mission`, `ministry`, `nature` |
| `measure` | `cp` | `ae`, `cp`, `commitment_authorization`, `payment_credit` |

Les CP et AE sont exposés séparément. La réponse contient le dénominateur
compatible avec la requête et les composantes budgétaires.

### `GET /api/v1/state-revenue`

Paramètres :

| Paramètre | Défaut | Valeurs |
| --- | --- | --- |
| `year` | `2025` | entier entre 2000 et 2100 |
| `status` | `revised_estimate` | `executed`, `initial_estimate`, `revised_estimate`, `budget_bill` |

Pour les recettes budgétaires, une réponse peut contenir simultanément lignes
détaillées, déductions, sous-totaux et totaux officiels. Les champs
`is_aggregate` et `is_deduction` indiquent les lignes qui ne doivent pas être
additionnées naïvement côté client. Chaque ligne comporte également une
`description` pédagogique générée par l’API.

## Erreurs et lecture seule

- `422 Unprocessable Content` : paramètre absent ou invalide ;
- `404 Not Found` : dataset, catégorie ou nœud indisponible ;
- `405 Method Not Allowed` : tentative d’écriture sur une route GET.

Les requêtes CORS de lecture depuis le frontend Vue séparé sont acceptées par la
configuration Laravel actuelle. La référence OpenAPI générée par Scramble est
la source de vérité pour les schémas détaillés ; ce fichier explique les
principes et les contrats importants pour l’intégration frontend.
