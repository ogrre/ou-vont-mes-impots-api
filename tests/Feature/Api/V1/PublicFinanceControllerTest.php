<?php

namespace Tests\Feature\Api\V1;

use App\Services\Api\PublicFinanceQuery;
use Tests\TestCase;

class PublicFinanceControllerTest extends TestCase
{
    public function test_budget_distribution_uses_read_only_cache_headers(): void
    {
        $payload = [
            'year' => 2099,
            'measurement' => 'payment_credit',
            'stage' => 'executed',
            'unit' => 'per_100',
            'denominator' => '100.00',
            'items' => [],
            'quality' => ['status' => 'validated'],
        ];
        $mock = $this->mock(PublicFinanceQuery::class);
        $mock->shouldReceive('budgetStateDistribution')->once()->with(2099, null, null, 'payment_credit', 'executed', 'per_100')->andReturn($payload);

        $this->getJson('/api/v1/budget-state/2099/distribution')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, public, stale-while-revalidate=86400')
            ->assertJsonPath('year', 2099);
    }
}
