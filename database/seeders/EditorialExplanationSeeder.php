<?php

namespace Database\Seeders;

use App\Models\EditorialExplanation;
use Illuminate\Database\Seeder;

class EditorialExplanationSeeder extends Seeder
{
    public function run(): void
    {
        $texts = [
            'api.home.headline' => 'Une vue d’ensemble des finances publiques françaises et du budget de l’État.',
            'api.home.public_spending.title' => 'Dépenses publiques :year',
            'api.home.public_spending.description' => 'Total des dépenses de l’ensemble des administrations publiques.',
            'api.home.public_spending.methodology' => 'Comptes nationaux INSEE, périmètre consolidé des administrations publiques.',
            'api.home.who_spends.title' => 'Qui dépense ?',
            'api.home.who_spends.description' => 'Répartition des dépenses entre les principaux sous-secteurs des administrations publiques.',
            'api.home.who_spends.methodology' => 'Les sous-secteurs sont présentés à partir de leurs tableaux INSEE dédiés. Ils ne sont pas additionnés pour reconstruire le total APU.',
            'api.home.what_for.title' => 'À quoi sert l’argent ?',
            'api.home.what_for.description' => 'Les dix fonctions COFOG des dépenses des administrations publiques.',
            'api.home.what_for.methodology' => 'COFOG consolidée : les pourcentages ont pour dénominateur le total des dépenses COFOG compatible.',
            'api.home.state_budget.title' => 'Budget de l’État',
            'api.home.state_budget.description' => 'Le budget de l’État est un périmètre distinct des dépenses de toutes les administrations publiques.',
            'api.home.state_budget.methodology' => 'Comptabilité budgétaire : crédits de paiement exécutés du PLRG/RAP 2024. Navigation : mission → programme → action.',
            'api.home.revenues.title' => 'D’où vient l’argent ?',
            'api.home.revenues.description' => 'Les recettes publiques et les recettes budgétaires de l’État sont deux périmètres comptables distincts.',
            'api.home.revenues.methodology' => 'INSEE fournit les recettes des APU en comptabilité nationale ; le PLRG fournit les recettes exécutées du budget de l’État en comptabilité budgétaire.',
            'api.home.revenues.quality_reason' => 'Les deux sources sont exposées séparément et ne sont pas additionnées.',
            'state_expenditure.percentage_denominator' => 'Total du même exercice, périmètre, statut, mesure, classification et ensemble de composantes budgétaires.',
            'cofog.default' => 'Cette catégorie décrit les dépenses publiques consacrées à « :label ».',
            'cofog.GF01' => 'Les services généraux couvrent le fonctionnement des institutions publiques, les services financiers et fiscaux, les affaires étrangères ainsi que les opérations liées à la dette publique.',
            'cofog.GF02' => 'La défense regroupe les dépenses consacrées à la défense militaire, à la protection du territoire et aux infrastructures et équipements militaires.',
            'cofog.GF03' => 'L’ordre et la sécurité publics comprennent notamment la police, la justice, les établissements pénitentiaires, les tribunaux et les services de secours.',
            'cofog.GF04' => 'Les affaires économiques rassemblent les politiques qui soutiennent l’activité économique : transports, agriculture, énergie, recherche, emploi et développement des entreprises.',
            'cofog.GF05' => 'La protection de l’environnement couvre la gestion des déchets, la lutte contre les pollutions, la protection de la biodiversité et la gestion des ressources naturelles.',
            'cofog.GF06' => 'Les logements et équipements collectifs comprennent le logement, l’aménagement urbain, l’eau, l’éclairage public et les infrastructures collectives.',
            'cofog.GF07' => 'La santé regroupe les services hospitaliers, les soins ambulatoires, les médicaments et les politiques de prévention et de santé publique.',
            'cofog.GF08' => 'Les loisirs, la culture et le culte comprennent les activités sportives, culturelles, les médias, les bibliothèques, les musées et la protection du patrimoine.',
            'cofog.GF09' => 'L’enseignement couvre les différents niveaux d’éducation, de la maternelle à l’enseignement supérieur, ainsi que les services qui les accompagnent.',
            'cofog.GF10' => 'La protection sociale regroupe notamment les retraites, les prestations liées à la maladie, au handicap, à la famille, au chômage, au logement et à l’exclusion sociale.',
            'cofog.01.1' => 'Le fonctionnement des organes exécutifs et législatifs, les affaires financières et fiscales, les affaires étrangères et les services généraux des administrations.',
            'cofog.01.2' => 'Les aides et transferts économiques vers l’extérieur, notamment la coopération internationale et l’aide publique au développement lorsqu’ils sont classés dans cette fonction.',
            'cofog.01.3' => 'Le fonctionnement courant des services généraux des administrations publiques qui ne relève pas d’une fonction plus précise : administration, gestion des bâtiments et services communs.',
            'cofog.01.4' => 'Les dépenses consacrées à la recherche fondamentale, c’est-à-dire la recherche visant à produire de nouvelles connaissances sans application immédiate déterminée.',
            'cofog.01.5' => 'La recherche et le développement appliqués au fonctionnement des services généraux des administrations publiques.',
            'cofog.01.6' => 'Les autres services généraux des administrations publiques qui ne peuvent pas être classés dans les sous-fonctions précédentes.',
            'cofog.01.7' => 'Les opérations liées à la dette publique, notamment les intérêts et les frais de gestion de la dette. Ce poste ne signifie pas que le remboursement du capital est une dépense de fonctionnement classique.',
            'cofog.01.8' => 'Les transferts de caractère général entre administrations publiques, lorsqu’ils ne peuvent pas être rattachés à une politique publique plus précise.',
            'budget_state.default' => 'Cette ligne décrit les crédits du budget de l’État consacrés à « :label ».',
            'distribution.default' => 'Cette catégorie regroupe les dépenses publiques classées sous « :label » dans le périmètre affiché.',
            'revenue.default' => 'Cette ligne décrit une recette du budget de l’État dans le périmètre de la comptabilité budgétaire. Elle est fournie par le fichier d’exécution et ne doit pas être additionnée aux sous-totaux ou aux totaux affichés ailleurs.',
            'revenue.registration_stamp' => 'Cette catégorie regroupe les droits d’enregistrement, les droits de timbre et diverses contributions et taxes indirectes. Elle est distincte de la TVA et de la taxe intérieure sur les produits énergétiques.',
            'revenue.income_tax' => 'L’impôt sur le revenu est prélevé sur les revenus des ménages et des personnes physiques.',
            'revenue.corporate_tax' => 'L’impôt sur les sociétés est acquitté par les entreprises et personnes morales sur leurs bénéfices.',
            'revenue.vat' => 'La TVA est une taxe indirecte incluse dans le prix des biens et services. Elle est collectée par les entreprises puis reversée à l’État.',
            'revenue.energy_tax' => 'La TICPE est une taxe indirecte appliquée principalement aux produits énergétiques, notamment les carburants.',
            'revenue.fines' => 'Cette catégorie regroupe les amendes et pénalités versées au budget de l’État.',
            'revenue.dividends' => 'Cette catégorie correspond aux revenus versés à l’État au titre de ses participations et placements.',
        ];

        foreach ($texts as $key => $body) {
            EditorialExplanation::query()->updateOrCreate(
                ['key' => $key, 'locale' => 'fr'],
                ['body' => $body],
            );
        }
    }
}
