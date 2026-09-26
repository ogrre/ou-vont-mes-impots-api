<?php

namespace Tests\Unit;

use App\Enums\BudgetStage;
use App\Enums\FinancialMeasure;
use App\Models\ClassificationItem;
use App\Models\Dataset;
use App\Models\FinancialObservation;
use App\Models\Source;
use App\Services\Api\PublicFinanceQuery;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PublicFinanceQueryTest extends TestCase
{
    public function test_it_builds_quality_and_unavailable_blocks(): void
    {
        $query = app(PublicFinanceQuery::class);
        $invoke = $this->invoker($query);

        $this->assertSame(['status' => 'validated', 'reason' => 'ok', 'coverage_percent' => '100.00'], $invoke('availabilityQuality', true, 'ok'));
        $this->assertSame(['status' => 'not_importable', 'reason' => 'missing', 'coverage_percent' => '0.00'], $invoke('availabilityQuality', false, 'missing'));
        $this->assertSame([
            'scope' => 'scope', 'basis' => 'basis', 'measurement' => 'measure', 'stage' => 'stage',
            'consolidation' => 'consolidated', 'dataset' => 'dataset', 'source' => 'source',
            'quality' => ['status' => 'validated'], 'accounting_basis' => 'basis', 'measurement_type' => 'measure',
        ], $invoke('blockMetadata', 'scope', 'basis', 'measure', 'stage', 'consolidated', 'dataset', 'source', ['status' => 'validated']));

        $unavailable = $invoke('unavailableBlock', 2024, 'scope', 'basis', 'measure', 'stage', 'missing');
        $this->assertSame(2024, $unavailable['year']);
        $this->assertNull($unavailable['amount']);
        $this->assertNull($unavailable['denominator']);
        $this->assertSame([], $unavailable['items']);
        $this->assertSame('not_importable', $unavailable['quality']['status']);
    }

    public function test_it_maps_distribution_rows_and_percentages(): void
    {
        $query = app(PublicFinanceQuery::class);
        $source = new Source(['name' => 'Source', 'homepage_url' => 'https://example.test']);
        $dataset = new Dataset(['slug' => 'dataset']);
        $dataset->setRelation('source', $source);
        $item = new ClassificationItem(['code' => 'GF01', 'official_label' => 'Fonction 1']);
        $row = new FinancialObservation(['amount' => '25.00', 'metadata' => ['source_page' => 4]]);
        $row->setRelation('classificationItem', $item);
        $row->setRelation('dataset', $dataset);

        $result = $this->invoker($query)('distributionBlock', 2024, 'scope', 'national_accounts', 'expenditure', 'execution', 'consolidated', new Collection([$row]), '100.00');

        $this->assertSame('100.00', $result['amount']);
        $this->assertSame('100.00', $result['denominator']);
        $this->assertSame('GF01', $result['items'][0]['code']);
        $this->assertSame('Fonction 1', $result['items'][0]['label']);
        $this->assertSame('25.00', $result['items'][0]['amount']);
        $this->assertSame('25.00', $result['items'][0]['percent']);
        $this->assertSame('25.00', $result['items'][0]['per_100']);
        $this->assertSame('validated', $result['items'][0]['quality_status']);
        $this->assertSame('dataset', $result['items'][0]['provenance']['dataset']);
        $this->assertSame('Source', $result['items'][0]['provenance']['source']);
        $this->assertSame(4, $result['items'][0]['provenance']['source_page']);
        $this->assertSame('validated', $result['quality']['status']);
        $this->assertSame('100.00', $result['quality']['coverage_percent']);
        $this->assertSame([], $result['quality']['excluded_items']);
    }

    public function test_it_preserves_distribution_and_observation_metadata_and_handles_zero_denominators(): void
    {
        $query = app(PublicFinanceQuery::class);
        $source = new Source(['name' => 'Source', 'homepage_url' => 'https://example.test']);
        $dataset = new Dataset(['slug' => 'dataset']);
        $dataset->setRelation('source', $source);
        $item = new ClassificationItem(['code' => 'GF01', 'official_label' => '  Fonction 1  ']);
        $row = new FinancialObservation(['amount' => '25.00', 'year' => 2024, 'metadata' => ['source_page' => 4, 'raw_label' => 'Libellé brut']]);
        $row->setRelation('classificationItem', $item);
        $row->setRelation('dataset', $dataset);

        $invoke = $this->invoker($query);
        $distribution = $invoke('distributionBlock', 2024, 'scope', 'basis', 'measure', 'stage', 'consolidated', new Collection([$row]), '0.00');

        $this->assertSame([
            'year' => 2024,
            'scope' => 'scope',
            'accounting_basis' => 'basis',
            'measurement_type' => 'measure',
            'stage' => 'stage',
            'consolidation' => 'consolidated',
            'amount' => '0.00',
            'denominator' => '0.00',
            'items' => [[
                'code' => 'GF01', 'label' => '  Fonction 1  ', 'description' => 'Cette catégorie regroupe les dépenses publiques classées sous «   Fonction 1   » dans le périmètre affiché.', 'amount' => '25.00', 'percent' => null,
                'per_100' => null, 'quality_status' => 'validated',
                'provenance' => [
                    'dataset' => 'dataset', 'source' => 'Source', 'source_url' => 'https://example.test',
                    'source_page' => 4, 'raw_label' => 'Libellé brut',
                ],
            ]],
            'quality' => [
                'status' => 'validated', 'coverage_percent' => '100.00', 'included_amount' => '0.00',
                'excluded_amount' => null, 'excluded_items' => [],
            ],
        ], $distribution);

        $this->assertSame('  Fonction 1  ', $invoke('labelRow', new Collection([$row]), 'Fonction 1')?->classificationItem->official_label);
        $this->assertSame([
            'amount' => '25.00', 'unit' => 'EUR', 'year' => 2024, 'dataset' => 'dataset',
            'source' => 'Source', 'source_page' => 4,
        ], $invoke('amountBlock', $row));
        $this->assertSame([
            'dataset' => 'dataset', 'source' => 'Source', 'source_url' => 'https://example.test',
            'source_page' => 4, 'raw_label' => 'Libellé brut',
        ], $invoke('overviewProvenance', $row));
        $this->assertSame([
            'amount' => null, 'unit' => 'EUR', 'year' => null, 'dataset' => null, 'source' => null, 'source_page' => null,
        ], $invoke('amountBlock', null));
    }

    public function test_it_aggregates_consolidated_cofog_rows_and_removes_zeroes(): void
    {
        $query = app(PublicFinanceQuery::class);
        $invoke = $this->invoker($query);
        $item = new ClassificationItem(['code' => 'GF10', 'official_label' => 'Protection sociale']);
        $other = new ClassificationItem(['code' => 'GF07', 'official_label' => 'Santé']);
        $rows = collect([
            tap(new FinancialObservation(['amount' => '693000000000.00']), fn ($row) => $row->setRelation('classificationItem', $item)),
            tap(new FinancialObservation(['amount' => '555700000000.00']), fn ($row) => $row->setRelation('classificationItem', $item)),
            tap(new FinancialObservation(['amount' => '261200000000.00']), fn ($row) => $row->setRelation('classificationItem', $other)),
            tap(new FinancialObservation(['amount' => '0.00']), fn ($row) => $row->setRelation('classificationItem', new ClassificationItem(['code' => 'GF02', 'official_label' => 'Défense']))),
        ]);

        $result = $invoke('aggregateCofogRows', $rows);

        $this->assertCount(2, $result);
        $this->assertSame(['GF10', 'GF07'], $result->map(fn (FinancialObservation $row): string => $row->classificationItem->code)->all());
        $this->assertSame('1248700000000.00', $result->first()->amount);
    }

    public function test_it_resolves_explicit_and_implicit_hierarchy_levels(): void
    {
        $query = app(PublicFinanceQuery::class);
        $invoke = $this->invoker($query);

        $explicit = new ClassificationItem(['code' => '01.2', 'metadata' => ['level' => 'custom']]);
        $implicitSubAction = new ClassificationItem(['code' => '01.2', 'metadata' => []]);
        $implicitAction = new ClassificationItem(['code' => '01', 'metadata' => []]);

        $this->assertSame('custom', $invoke('level', $explicit));
        $this->assertSame('sub_action', $invoke('level', $implicitSubAction));
        $this->assertSame('action', $invoke('level', $implicitAction));
    }

    public function test_budget_nodes_expose_stable_identifiers_and_descriptions(): void
    {
        $query = app(PublicFinanceQuery::class);
        $source = new Source(['name' => 'Budget source', 'homepage_url' => 'https://example.test/budget']);
        $dataset = new Dataset(['slug' => 'budget-dataset']);
        $dataset->setRelation('source', $source);
        $item = new ClassificationItem([
            'code' => null,
            'slug' => 'justice',
            'official_label' => 'Justice',
            'description' => 'Les crédits consacrés à la justice.',
            'metadata' => [],
        ]);
        $ae = new FinancialObservation(['amount' => '12.00', 'measure' => FinancialMeasure::CommitmentAuthorization, 'budget_stage' => BudgetStage::Execution, 'metadata' => []]);
        $ae->setRelation('dataset', $dataset);
        $cp = new FinancialObservation(['amount' => '10.00', 'measure' => FinancialMeasure::PaymentCredit, 'budget_stage' => BudgetStage::Execution, 'metadata' => []]);
        $cp->setRelation('dataset', $dataset);
        $item->setRelation('observations', collect([$ae, $cp]));

        $invoke = $this->invoker($query);
        $node = $invoke('budgetNode', $item, 2024, 'mission', false);

        $this->assertSame([
            'code' => null,
            'slug' => 'justice',
            'label' => 'Justice',
            'description' => 'Les crédits consacrés à la justice.',
        ], array_intersect_key($node, array_flip(['code', 'slug', 'label', 'description'])));
        $this->assertTrue($node['contributes_to_program_total']);
        $this->assertSame('12.00', $node['ae']['execution']);
        $this->assertSame('10.00', $node['cp']['execution']);
        $this->assertSame('Budget source', $node['provenance']['source']);

        $excluded = new ClassificationItem(['code' => 'X', 'slug' => 'excluded', 'official_label' => 'Excluded', 'metadata' => ['contributes_to_program_total' => false]]);
        $excluded->setRelation('observations', collect());
        $this->assertFalse($invoke('budgetNode', $excluded, 2024, 'action', false)['contributes_to_program_total']);
    }

    /** @return callable(string, mixed ...$arguments): mixed */
    private function invoker(PublicFinanceQuery $query): callable
    {
        return static function (string $method, mixed ...$arguments) use ($query): mixed {
            $reflection = new \ReflectionMethod($query, $method);
            $reflection->setAccessible(true);

            return $reflection->invoke($query, ...$arguments);
        };
    }
}
