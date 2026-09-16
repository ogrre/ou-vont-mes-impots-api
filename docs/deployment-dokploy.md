# Déploiement de l’API avec Dokploy

Cette procédure cible un unique conteneur API Laravel et un service PostgreSQL
privé hébergés dans la même instance Dokploy. Le frontend reste déployé
séparément.

## Architecture

- Dokploy construit le [`Dockerfile`](../Dockerfile) depuis `main` en production et `dev` en staging.
- Chaque application utilise son webhook GitHub `push` et filtre sa branche cible ; un merge déclenche donc le déploiement de cet environnement.
- Le conteneur expose Nginx sur le port `8080` et exécute PHP-FPM 8.4.
- Le contrôle de santé est disponible sur `GET /up`.
- PostgreSQL doit être créé comme service distinct dans Dokploy et ne doit pas
  publier son port sur Internet.
- Les fichiers officiels pris en charge sont inclus dans l’image via les règles
  de `.dockerignore` et importés au démarrage si `RUN_DATA_IMPORTS=true`.

## Création de l’application

Dans Dokploy, créez une application depuis le dépôt GitHub de l’API avec les
paramètres suivants :

| Paramètre | Valeur |
| --- | --- |
| Branche | `main` |
| Méthode de construction | `Dockerfile` |
| Chemin du Dockerfile | `Dockerfile` |
| Port du conteneur | `8080` |
| Healthcheck | `/up` |

Activez le déploiement automatique après un push sur `main`. Cette branche est
protégée sur GitHub : les changements doivent donc passer par une pull request
et réussir les contrôles CI avant leur fusion.

## PostgreSQL

Créez un service PostgreSQL avec un volume persistant et placez-le sur le même
réseau privé que l’application. Utilisez le nom d’hôte interne fourni par
Dokploy pour `DB_HOST`. N’exposez pas le port `5432` publiquement.

Configurez des sauvegardes régulières hors du VPS et testez leur restauration.
Un volume persistant protège des redémarrages de conteneur, mais ne remplace pas
une sauvegarde.

## Variables d’environnement

Les valeurs suivantes doivent être définies dans Dokploy. Elles sont données à
titre de structure : aucune valeur secrète ne doit être ajoutée au dépôt.

```dotenv
APP_NAME="Mais où vont mes impôts ? API"
APP_VERSION=0.1.0
APP_ENV=production
APP_KEY=<secret généré avec php artisan key:generate --show>
APP_DEBUG=false
APP_URL=https://<domaine-api>

LOG_CHANNEL=stderr
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=<hôte-postgresql-interne>
DB_PORT=5432
DB_DATABASE=<nom-base>
DB_USERNAME=<utilisateur-base>
DB_PASSWORD=<mot-de-passe-base>

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database

RUN_MIGRATIONS=true
RUN_SEEDERS=true
RUN_DATA_IMPORTS=true
DATA_IMPORT_PATH=data
```

`APP_KEY`, `DB_PASSWORD` et les autres secrets doivent être créés directement
dans Dokploy. Pour générer `APP_KEY` hors production :

```bash
docker compose -f docker-compose-dev.yml exec app php artisan key:generate --show
```

`RUN_MIGRATIONS=true` applique les migrations au démarrage de l’unique réplique
du MVP. `RUN_SEEDERS=true` ajoute ou met à jour les petits
référentiels et descripteurs d’import ; aucun montant financier officiel n’est
inséré par ces seeders. Les référentiels sont seedés à chaque déploiement pour enregistrer les nouveaux
descripteurs avant les imports.

Sur une future installation à plusieurs réplicas, les migrations devront être
déplacées vers une tâche de déploiement unique.

## Domaine et HTTPS

Associez le domaine public de l’API au port `8080` dans Dokploy et activez le
certificat TLS. Définissez ensuite la même URL HTTPS dans `APP_URL` et dans la
variable d’environnement correspondante du frontend.

## Imports idempotents

Définissez `RUN_DATA_IMPORTS=true` pour examiner au démarrage les fichiers du
dossier `data` (ou de `DATA_IMPORT_PATH`). Seuls les fichiers correspondant à
un descripteur connu sont considérés. Un contenu déjà importé pour ce
descripteur est reconnu par son checksum SHA-256 et ignoré sans faire échouer
le déploiement : les observations existantes ne sont ni recréées ni écrasées.

Un fichier modifié possède un nouveau checksum. Il est alors traité comme un
nouvel import traçable ; toute collision avec les observations normalisées
existantes provoque une erreur explicite plutôt qu’un écrasement silencieux.
Les formats différés ou insuffisamment documentés présents dans `data` ne sont
pas importés.

L’image embarque les six CSV PLRG, le classeur de recettes, les fichiers INSEE
2024 et historiques, COFOG 2024, les CSV d’exécution et de recettes de l’État
2024, ainsi que les PDF RAP et leurs fichiers JSON disponibles. Les RAP ne sont
pas importés par `dataset:import-known` ; leur traitement reste distinct.

Les caches de configuration, routes et événements sont générés avant les
migrations, le seed et les imports, exécutés dans cet ordre. Un échec du seed
ou d’un import critique arrête le démarrage avec un code non nul. Un checksum
déjà enregistré n’est ignoré que si son import est terminé avec succès ; un
import échoué ou en cours bloque le démarrage sans lancer une nouvelle tentative.

Conserver une seule réplique et une stratégie de mise à jour `stop-first` :
`start-first` peut faire coexister deux conteneurs pendant un déploiement.
Avec plusieurs réplicas, déplacer migrations, seed et imports dans un job
unique et désactiver ces commandes dans les conteneurs API.

La même opération peut être lancée manuellement :

```bash
php artisan dataset:import-known data
```

Ne publiez pas un dataset tant que sa provenance critique reste incomplète.

Vérifiez ensuite :

```bash
php artisan data:validate state-expenditure-2025
```

## Vérifications après déploiement

```bash
curl --fail https://<domaine-api>/up
curl --fail https://<domaine-api>/api/v1/version
curl --fail 'https://<domaine-api>/api/v1/state-expenditure?year=2025&classification=mission&measure=cp'
```

La dernière requête ne réussira qu’après l’import des observations
correspondantes.
