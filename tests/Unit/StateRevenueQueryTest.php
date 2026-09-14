<?php

namespace Tests\Unit;

use App\Models\ClassificationItem;
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
}
