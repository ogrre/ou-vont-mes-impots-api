# Réparation RAP 2024

Le programme 200 contient des colonnes de plus de 100 milliards d’euros. Le pas automatique de `pdftotext -layout` séparait certaines colonnes par un seul espace. Le parseur les concaténait comme des groupes de milliers ; le cast PHP en entier saturait alors à `9223372036854775807`.

L’extraction utilise désormais un pas fixe de 3 points. Chaque colonne est validée intégralement et normalisée en chaîne décimale à deux chiffres. Une cellule dépassant 13 chiffres entiers (borne métier de 10 000 milliards d’euros, sans lien avec la plateforme PHP) ou de syntaxe inconnue reste `null`. Les totaux utilisent une arithmétique décimale ; une somme partiellement inconnue reste inconnue. L’import contrôle indépendamment les montants avant toute écriture transactionnelle. Les JSON antérieurs ne sont pas des entrées d’import : les PDF sont toujours relus.

Valeurs de référence des PDF archivés :

- P200 CP consommés : 141 568 330 580,00 €.
- P201 CP consommés : 4 955 165 410,00 €.
- Mission Remboursements et dégrèvements : 146 523 495 990,00 €.

Avant reconstruction, sauvegarder PostgreSQL, inventorier les PDF avec SHA-256 et conserver les anciens JSON à part. Monter un dossier de données persistant via Dokploy sur `/var/www/data`. Il doit contenir `raw/rap/2024`, `processed/rap/2024/catalog.json` et les CSV 2024 nécessaires à la correspondance des missions. Ne pas recopier les JSON contenant des montants saturés dans le dossier des nouveaux résultats.

Après validation et reconstruction de la staging, vérifier les empreintes du code dans le nouveau conteneur, sa santé et la présence de `pdftotext`, puis exécuter :

```sh
php artisan migrate --force
php artisan db:seed --class=EditorialExplanationSeeder --force
php artisan optimize:clear
php artisan cache:clear
php artisan dataset:import-rap 2024 --parse-only --force
php artisan cache:clear
```

Lire le rapport d’import même si la commande retourne un échec : des formats institutionnels peuvent rester non importables. Ne pas leur fabriquer de montants. Vérifier le nombre d’observations et l’absence de doublons après un second import, les montants proches de PHP_INT_MAX, les métadonnées de provenance, la qualité et les endpoints publics. Les caches doivent être purgés après l’import pour ne pas servir la réponse antérieure.

Aucune action de production n’est autorisée par ce document. Elle nécessite une confirmation explicite après le rapport staging.
