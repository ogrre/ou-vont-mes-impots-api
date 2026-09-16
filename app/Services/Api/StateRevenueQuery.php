<?php

namespace App\Services\Api;

use App\Models\BudgetComponent;
use App\Models\ClassificationItem;
use App\Models\FinancialObservation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StateRevenueQuery
{
    public function __construct(private readonly DatasetProvenancePresenter $provenance) {}

    /**
     * @return array{
     *     period: int,
     *     scope: array{code: string, label: string, budget_component: string|null},
     *     status: string,
     *     flow_type: 'revenue',
     *     classification: 'revenue',
     *     currency: 'EUR',
     *     aggregation_warning: string,
     *     items: list<array{
     *         slug: string,
     *         label: string,
     *         description: string,
     *         amount: string,
     *         is_aggregate: bool,
     *         is_deduction: bool,
     *         source_row_number: int|null
     *     }>,
     *     source: array<string, mixed>
     * }
     */
    public function get(int $year, string $status): array
    {
        $observations = FinancialObservation::query()
            ->with([
                'accountingScope',
                'budgetComponent',
                'classificationItem.classification',
                'classificationItem.parent.parent.parent',
                'dataset.source',
                'datasetFile',
                'importBatch',
            ])
            ->where('year', $year)
            ->where('status', $status)
            ->where('flow_type', 'revenue')
            ->whereHas('accountingScope', fn ($query) => $query->where('code', 'french_state_budget'))
            ->whereHas('classificationItem.classification', fn ($query) => $query->where('code', 'state_budget_revenue'))
            ->whereHas('importBatch', fn ($query) => $query->where('status', 'completed'))
            ->orderBy('source_row_number')
            ->get();

        if ($observations->isEmpty()) {
            throw new NotFoundHttpException('Aucune recette importée ne correspond à ces filtres.');
        }

        $first = $observations->first();

        return [
            'period' => $year,
            'scope' => [
                'code' => $first->accountingScope->code,
                'label' => $first->accountingScope->name,
                'budget_component' => $this->budgetComponentCode($first->budgetComponent),
            ],
            'status' => $status,
            'flow_type' => 'revenue',
            'classification' => 'revenue',
            'currency' => 'EUR',
            'aggregation_warning' => 'Les lignes comprennent des détails, sous-totaux, prélèvements et totaux : elles ne doivent pas être additionnées entre elles.',
            'items' => $observations->map(fn (FinancialObservation $observation) => [
                'code' => $observation->classificationItem->code,
                'slug' => $observation->classificationItem->slug,
                'label' => $observation->classificationItem->official_label,
                'description' => $this->revenueDescription($observation->classificationItem->official_label),
                'level' => $observation->classificationItem->metadata['csv_level'] ?? null,
                'parent_code' => $observation->classificationItem->parent?->code,
                'breadcrumb' => $this->breadcrumb($observation->classificationItem),
                'amount' => $observation->amount,
                'is_aggregate' => $this->isAggregate($observation->classificationItem),
                'is_deduction' => $this->isDeduction($observation),
                'source_row_number' => $observation->source_row_number,
            ])->all(),
            'source' => $this->provenance->present($first->dataset, $first->datasetFile, $first->importBatch),
        ];
    }

    /** @return list<string> */
    private function breadcrumb(ClassificationItem $item): array
    {
        $labels = [];
        while ($item !== null) {
            array_unshift($labels, $item->official_label);
            $item = $item->parent;
        }

        return $labels;
    }

    private function revenueDescription(string $label): string
    {
        $normalized = mb_strtolower($label);

        return match (true) {
            str_contains($normalized, 'enregistrement, timbre') => 'Cette catégorie regroupe les droits d’enregistrement, les droits de timbre et diverses contributions et taxes indirectes. Elle est distincte de la TVA et de la taxe intérieure sur les produits énergétiques.',
            str_contains($normalized, 'impôt sur le revenu') => 'L’impôt sur le revenu est prélevé sur les revenus des ménages et des personnes physiques.',
            str_contains($normalized, 'impôt sur les sociétés') => 'L’impôt sur les sociétés est acquitté par les entreprises et personnes morales sur leurs bénéfices.',
            str_contains($normalized, 'taxe sur la valeur ajoutée') || str_contains($normalized, 'tva') => 'La TVA est une taxe indirecte incluse dans le prix des biens et services. Elle est collectée par les entreprises puis reversée à l’État.',
            str_contains($normalized, 'taxe intérieure sur les produits énergétiques') || str_contains($normalized, 'ticpe') => 'La TICPE est une taxe indirecte appliquée principalement aux produits énergétiques, notamment les carburants.',
            str_contains($normalized, 'amendes') => 'Cette catégorie regroupe les amendes et pénalités versées au budget de l’État.',
            str_contains($normalized, 'dividendes') => 'Cette catégorie correspond aux revenus versés à l’État au titre de ses participations et placements.',
            default => 'Cette ligne décrit une recette du budget de l’État dans le périmètre de la comptabilité budgétaire. Elle est fournie par le fichier d’exécution et ne doit pas être additionnée aux sous-totaux ou aux totaux affichés ailleurs.',
        };
    }

    private function budgetComponentCode(?BudgetComponent $component): ?string
    {
        return $component?->code;
    }

    private function isAggregate(ClassificationItem $item): bool
    {
        return ($item->metadata['aggregation_role'] ?? null) === 'aggregate'
            || (($item->metadata['csv_level'] ?? 0) >= 2);
    }

    private function isDeduction(FinancialObservation $observation): bool
    {
        return (bool) ($observation->metadata['is_deduction'] ?? str_starts_with(mb_strtolower($observation->classificationItem->official_label), 'à déduire'));
    }
}
