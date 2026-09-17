<?php

namespace App\Services\Api;

use App\Support\DecimalMoney;

class HomePagePresenter
{
    public function __construct(private readonly EditorialExplanationCatalog $editorial) {}

    /** @param array<string,mixed> $overview
     * @return array<string,mixed>
     */
    public function present(array $overview): array
    {
        $year = (int) $overview['year'];
        $spending = (array) $overview['public_finances']['expenditure'];
        $institutional = (array) $overview['institutional_distribution'];
        $functional = (array) $overview['functional_distribution'];
        $state = (array) $overview['state_budget'];
        $revenues = (array) $overview['revenues'];

        return [
            'data_year' => $year,
            'reference_year' => $year,
            'generated_at' => now()->toIso8601String(),
            'methodology_version' => '2024.1',
            'headline' => [
                'title' => 'Où vont les dépenses publiques ?',
                'description' => $this->editorial->text('api.home.headline', 'Une vue d’ensemble des finances publiques françaises et du budget de l’État.'),
                'amount' => $spending['amount'] ?? null, 'unit' => 'EUR', 'items' => [],
                'percentage' => null, 'per_100' => null,
                'quality_status' => $overview['public_finances']['quality']['status'] ?? 'not_importable',
                'quality' => $overview['public_finances']['quality'] ?? [],
                'methodology' => 'Le montant principal est celui des dépenses des administrations publiques en comptes nationaux.',
                'provenance' => $this->provenance($overview['public_finances']),
            ],
            'public_spending' => $this->block(
                str_replace(':year', (string) $year, $this->editorial->text('api.home.public_spending.title', 'Dépenses publiques :year')),
                $this->editorial->text('api.home.public_spending.description', 'Total des dépenses de l’ensemble des administrations publiques.'),
                $spending['amount'] ?? null, [], $overview['public_finances']['quality'] ?? [],
                $this->editorial->text('api.home.public_spending.methodology', 'Comptes nationaux INSEE, périmètre consolidé des administrations publiques.'), $this->provenance($overview['public_finances']),
            ),
            'who_spends' => $this->block(
                $this->editorial->text('api.home.who_spends.title', 'Qui dépense ?'),
                $this->editorial->text('api.home.who_spends.description', 'Répartition des dépenses entre les principaux sous-secteurs des administrations publiques.'),
                $institutional['amount'] ?? null,
                $this->ratioItems((array) ($institutional['items'] ?? []), $institutional['denominator'] ?? null),
                $institutional['quality'] ?? [],
                $this->editorial->text('api.home.who_spends.methodology', 'Les sous-secteurs sont présentés à partir de leurs tableaux INSEE dédiés. Ils ne sont pas additionnés pour reconstruire le total APU.'),
                $this->provenance($institutional),
            ),
            'what_for' => $this->block(
                $this->editorial->text('api.home.what_for.title', 'À quoi sert l’argent ?'),
                $this->editorial->text('api.home.what_for.description', 'Les dix fonctions COFOG des dépenses des administrations publiques.'),
                $functional['amount'] ?? null,
                $this->sortedItems((array) ($functional['items'] ?? [])), $functional['quality'] ?? [],
                $this->editorial->text('api.home.what_for.methodology', 'COFOG consolidée : les pourcentages ont pour dénominateur le total des dépenses COFOG compatible.'),
                $this->provenance($functional),
            ),
            'state_budget' => $this->block(
                $this->editorial->text('api.home.state_budget.title', 'Budget de l’État'),
                $this->editorial->text('api.home.state_budget.description', 'Le budget de l’État est un périmètre distinct des dépenses de toutes les administrations publiques.'),
                $state['amount'] ?? null, $this->sortedItems((array) ($state['distribution'] ?? [])), $state['quality'] ?? [],
                $this->editorial->text('api.home.state_budget.methodology', 'Comptabilité budgétaire : crédits de paiement exécutés du PLRG/RAP 2024. Navigation : mission → programme → action.'),
                ['source' => $state['source'] ?? null, 'dataset' => $state['dataset'] ?? null, 'source_url' => null, 'source_page' => null],
            ),
            'revenues' => $this->revenuesBlock($revenues),
        ];
    }

    /** @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private function ratioItems(array $items, ?string $denominator): array
    {
        return array_map(function (array $item) use ($denominator): array {
            $amount = $item['amount'] ?? null;
            // Compute the percentage directly at its displayed precision,
            // avoiding an unnecessary intermediate division and truncation.
            $ratio = $amount === null || $denominator === null || $denominator === '0.00' ? null : bcdiv(bcmul((string) $amount, '100', 2), $denominator, 2);
            $item['percentage'] = $ratio;
            $item['per_100'] = $ratio;

            return $item;
        }, $items);
    }

    /** @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private function sortedItems(array $items): array
    {
        usort($items, fn (array $a, array $b): int => DecimalMoney::compare((string) ($b['amount'] ?? '0.00'), (string) ($a['amount'] ?? '0.00')));

        return $items;
    }

    /** @param array<string,mixed> $quality
     * @param  list<array<string,mixed>>  $items
     * @param  array<string,mixed>  $provenance
     * @return array<string,mixed>
     */
    private function block(string $title, string $description, mixed $amount, array $items, array $quality, string $methodology, array $provenance): array
    {
        return ['title' => $title, 'description' => $description, 'amount' => $amount, 'unit' => 'EUR', 'items' => $items, 'percentage' => null, 'per_100' => null, 'quality_status' => $quality['status'] ?? 'not_importable', 'quality' => $quality, 'methodology' => $methodology, 'provenance' => $provenance];
    }

    /** @param array<string,mixed> $revenues
     * @return array<string,mixed>
     */
    private function revenuesBlock(array $revenues): array
    {
        $public = (array) ($revenues['public_revenues'] ?? []);
        $state = (array) ($revenues['state_budget_revenues'] ?? []);
        $items = [
            ['code' => 'public_revenues', 'label' => 'Recettes publiques', 'amount' => $public['amount'] ?? null, 'percentage' => null, 'per_100' => null, 'quality_status' => $public['quality']['status'] ?? 'not_importable', 'accounting_basis' => $public['accounting_basis'] ?? 'national_accounts', 'provenance' => $this->provenance($public)],
            ['code' => 'state_budget_revenues', 'label' => 'Recettes budgétaires de l’État', 'amount' => $state['amount'] ?? null, 'percentage' => null, 'per_100' => null, 'quality_status' => $state['quality']['status'] ?? 'not_importable', 'accounting_basis' => $state['accounting_basis'] ?? 'budgetary', 'provenance' => $this->provenance($state)],
        ];

        return ['title' => $this->editorial->text('api.home.revenues.title', 'D’où vient l’argent ?'), 'description' => $this->editorial->text('api.home.revenues.description', 'Les recettes publiques et les recettes budgétaires de l’État sont deux périmètres comptables distincts.'), 'amount' => null, 'unit' => 'EUR', 'items' => $items, 'percentage' => null, 'per_100' => null, 'quality_status' => $this->combinedStatus($items), 'quality' => ['status' => $this->combinedStatus($items), 'reason' => $this->editorial->text('api.home.revenues.quality_reason', 'Les deux sources sont exposées séparément et ne sont pas additionnées.'), 'included_amount' => null, 'excluded_amount' => null, 'excluded_items' => []], 'methodology' => $this->editorial->text('api.home.revenues.methodology', 'INSEE fournit les recettes des APU en comptabilité nationale ; le PLRG fournit les recettes exécutées du budget de l’État en comptabilité budgétaire.'), 'provenance' => ['sources' => array_values(array_filter([$public['source'] ?? null, $state['source'] ?? null])), 'datasets' => array_values(array_filter([$public['dataset'] ?? null, $state['dataset'] ?? null]))], 'sub_blocks' => ['public_revenues' => $public, 'state_budget_revenues' => $state]];
    }

    /** @param list<array<string,mixed>> $items */
    private function combinedStatus(array $items): string
    {
        foreach (['not_importable', 'review_required', 'validated'] as $status) {
            if (collect($items)->contains(fn (array $item): bool => ($item['quality_status'] ?? null) === $status)) {
                return $status;
            }
        }

        return 'not_importable';
    }

    /** @param array<string,mixed> $block
     * @return array<string,mixed>
     */
    private function provenance(array $block): array
    {
        return ['source' => $block['source'] ?? null, 'source_url' => $block['source_url'] ?? null, 'dataset' => $block['dataset'] ?? null, 'source_page' => $block['source_page'] ?? null];
    }
}
