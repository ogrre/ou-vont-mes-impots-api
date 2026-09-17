<?php

namespace Tests\Unit;

use App\Models\BudgetComponent;
use App\Models\ClassificationItem;
use App\Models\FinancialObservation;
use App\Services\Api\StateRevenueQuery;
use Tests\TestCase;

class StateRevenueQueryTest extends TestCase
{
    public function test_it_builds_a_complete_breadcrumb_from_root_to_item(): void
    {
        $root = new ClassificationItem(['official_label' => 'Recettes']);
        $parent = new ClassificationItem(['official_label' => 'Recettes fiscales']);
        $parent->setRelation('parent', $root);
        $item = new ClassificationItem(['official_label' => 'Impôt sur le revenu']);
        $item->setRelation('parent', $parent);

        $query = app(StateRevenueQuery::class);
        $breadcrumb = (new \ReflectionMethod($query, 'breadcrumb'))->invoke($query, $item);

        $this->assertSame(['Recettes', 'Recettes fiscales', 'Impôt sur le revenu'], $breadcrumb);
    }

    public function test_it_explains_common_state_revenue_categories_case_insensitively(): void
    {
        $query = app(StateRevenueQuery::class);
        $method = new \ReflectionMethod($query, 'revenueDescription');

        $this->assertStringContainsString('droits d’enregistrement', $method->invoke($query, '17 - ENREGISTREMENT, TIMBRE'));
        $this->assertStringContainsString('revenus des ménages', $method->invoke($query, 'IMPÔT SUR LE REVENU'));
        $this->assertStringContainsString('bénéfices', $method->invoke($query, 'Impôt sur les sociétés'));
        $this->assertStringContainsString('taxe indirecte', $method->invoke($query, 'Taxe sur la valeur ajoutée'));
        $this->assertStringContainsString('produits énergétiques', $method->invoke($query, 'TICPE'));
        $this->assertStringContainsString('amendes', $method->invoke($query, 'Amendes'));
        $this->assertStringContainsString('participations', $method->invoke($query, 'Dividendes'));
        $this->assertStringContainsString('comptabilité budgétaire', $method->invoke($query, 'Autres recettes'));
    }

    public function test_it_preserves_nullable_component_and_revenue_mapping_rules(): void
    {
        $query = app(StateRevenueQuery::class);
        $invoke = fn (string $name, mixed ...$arguments): mixed => (new \ReflectionMethod($query, $name))->invoke($query, ...$arguments);

        $component = new BudgetComponent(['code' => 'general_budget']);
        $this->assertSame('general_budget', $invoke('budgetComponentCode', $component));
        $this->assertNull($invoke('budgetComponentCode', null));

        $this->assertFalse($invoke('isAggregate', new ClassificationItem(['metadata' => ['csv_level' => 1]])));
        $this->assertTrue($invoke('isAggregate', new ClassificationItem(['metadata' => ['csv_level' => 2]])));
        $this->assertTrue($invoke('isAggregate', new ClassificationItem(['metadata' => ['aggregation_role' => 'aggregate', 'csv_level' => 0]])));

        $item = new ClassificationItem(['official_label' => 'À déduire : remboursements']);
        $observation = new FinancialObservation(['metadata' => []]);
        $observation->setRelation('classificationItem', $item);
        $this->assertTrue($invoke('isDeduction', $observation));
        $observation->metadata = ['is_deduction' => true];
        $item->official_label = 'Recettes fiscales';
        $this->assertTrue($invoke('isDeduction', $observation));
    }
}
