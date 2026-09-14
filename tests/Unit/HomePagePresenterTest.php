<?php

namespace Tests\Unit;

use App\Services\Api\HomePagePresenter;
use Tests\TestCase;

class HomePagePresenterTest extends TestCase
{
    public function test_it_presents_and_sorts_the_homepage_blocks(): void
    {
        $result = app(HomePagePresenter::class)->present($this->overview());

        $this->assertSame(2024, $result['data_year']);
        $this->assertSame(2024, $result['reference_year']);
        $this->assertSame('2024.1', $result['methodology_version']);
        $this->assertSame('120.00', $result['headline']['amount']);
        $this->assertSame('Où vont les dépenses publiques ?', $result['headline']['title']);
        $this->assertSame('Une vue d’ensemble des finances publiques françaises et du budget de l’État.', $result['headline']['description']);
        $this->assertSame('EUR', $result['headline']['unit']);
        $this->assertSame([], $result['headline']['items']);
        $this->assertNull($result['headline']['percentage']);
        $this->assertNull($result['headline']['per_100']);
        $this->assertSame('validated', $result['headline']['quality_status']);
        $this->assertSame(['status' => 'validated'], $result['headline']['quality']);
        $this->assertSame(['source' => 'INSEE', 'source_url' => null, 'dataset' => 'public', 'source_page' => null], $result['headline']['provenance']);

        $this->assertSame(['status' => 'validated'], $result['public_spending']['quality']);
        $this->assertSame('validated', $result['public_spending']['quality_status']);
        $this->assertSame(['status' => 'validated'], $result['who_spends']['quality']);
        $this->assertSame(['large', 'small'], array_column($result['what_for']['items'], 'code'));
        $this->assertSame(['status' => 'validated'], $result['what_for']['quality']);
        $this->assertSame('50.00', $result['who_spends']['items'][0]['percentage']);
        $this->assertSame('50.00', $result['who_spends']['items'][0]['per_100']);
        $this->assertSame('EUR', $result['state_budget']['unit']);
        $this->assertSame('Budget de l’État', $result['state_budget']['title']);
        $this->assertSame([['code' => 'mission', 'amount' => '80.00']], $result['state_budget']['items']);
        $this->assertSame(['status' => 'validated'], $result['state_budget']['quality']);
        $this->assertSame('validated', $result['state_budget']['quality_status']);
        $this->assertSame('Budget', $result['state_budget']['provenance']['source']);
        $this->assertSame('validated', $result['revenues']['quality_status']);
        $this->assertSame('D’où vient l’argent ?', $result['revenues']['title']);
        $this->assertSame($this->overview()['revenues'], $result['revenues']['sub_blocks']);
        $this->assertSame(['public_revenues', 'state_budget_revenues'], array_column($result['revenues']['items'], 'code'));
        $this->assertSame('national_accounts', $result['revenues']['items'][0]['accounting_basis']);
        $this->assertSame('budgetary', $result['revenues']['items'][1]['accounting_basis']);
        $this->assertSame(['sources' => ['INSEE', 'Budget'], 'datasets' => ['public-revenue', 'state-revenue']], $result['revenues']['provenance']);
        $this->assertSame(
            ['source' => 'INSEE', 'source_url' => null, 'dataset' => 'public-revenue', 'source_page' => null],
            $result['revenues']['items'][0]['provenance'],
        );
        $this->assertSame(
            ['source' => 'Budget', 'source_url' => null, 'dataset' => 'state-revenue', 'source_page' => null],
            $result['revenues']['items'][1]['provenance'],
        );
    }

    public function test_it_handles_missing_amounts_and_zero_denominators(): void
    {
        $overview = $this->overview();
        $overview['public_finances']['expenditure']['amount'] = null;
        $overview['public_finances']['quality'] = [];
        $overview['institutional_distribution'] = [
            'amount' => null,
            'denominator' => '0.00',
            'items' => [['code' => 'empty', 'amount' => null]],
            'quality' => [],
        ];
        $overview['functional_distribution'] = ['amount' => null, 'items' => [], 'quality' => []];
        $overview['state_budget'] = ['amount' => null, 'distribution' => [], 'quality' => []];
        $overview['revenues'] = [];

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertNull($result['public_spending']['amount']);
        $this->assertSame('not_importable', $result['public_spending']['quality_status']);
        $this->assertNull($result['who_spends']['items'][0]['percentage']);
        $this->assertNull($result['who_spends']['items'][0]['per_100']);
        $this->assertSame('not_importable', $result['revenues']['quality_status']);
        $this->assertNull($result['revenues']['items'][0]['amount']);
    }

    public function test_it_prioritizes_the_worst_revenue_quality_status(): void
    {
        $overview = $this->overview();
        $overview['revenues']['public_revenues']['quality'] = ['status' => 'review_required'];
        $overview['revenues']['state_budget_revenues']['quality'] = ['status' => 'validated'];

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertSame('review_required', $result['revenues']['quality_status']);
        $this->assertSame('review_required', $result['revenues']['quality']['status']);
    }

    public function test_not_importable_revenue_takes_precedence_over_validated_revenue(): void
    {
        $overview = $this->overview();
        $overview['revenues']['public_revenues']['quality']['status'] = 'not_importable';
        $overview['revenues']['state_budget_revenues']['quality']['status'] = 'validated';

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertSame('not_importable', $result['revenues']['quality_status']);
    }

    public function test_it_returns_null_ratios_independently_for_missing_amount_or_denominator(): void
    {
        $overview = $this->overview();
        $overview['institutional_distribution']['denominator'] = '200.00';
        $overview['institutional_distribution']['items'] = [
            ['code' => 'missing_amount', 'amount' => null],
            ['code' => 'valid', 'amount' => '50.00'],
        ];
        $overview['functional_distribution']['items'] = [
            ['code' => 'missing_denominator', 'amount' => '50.00'],
        ];
        $overview['institutional_distribution']['items'][0]['amount'] = null;

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertNull($result['who_spends']['items'][0]['percentage']);
        $this->assertSame('25.00', $result['who_spends']['items'][1]['percentage']);

        $overview['institutional_distribution']['denominator'] = null;
        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertNull($result['who_spends']['items'][1]['percentage']);
    }

    public function test_it_normalizes_scalar_overview_sections_before_presenting_them(): void
    {
        $overview = $this->overview();
        $overview['year'] = '2024';
        $overview['public_finances']['expenditure'] = 'invalid';
        $overview['institutional_distribution'] = 'invalid';
        $overview['functional_distribution'] = 'invalid';
        $overview['state_budget'] = 'invalid';
        $overview['revenues'] = 'invalid';

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertSame(2024, $result['data_year']);
        $this->assertNull($result['public_spending']['amount']);
        $this->assertSame([], $result['who_spends']['items']);
        $this->assertSame([], $result['what_for']['items']);
        $this->assertSame([], $result['state_budget']['items']);
    }

    public function test_it_sorts_items_with_missing_amounts_last(): void
    {
        $overview = $this->overview();
        $overview['functional_distribution']['items'] = [
            ['code' => 'missing'],
            ['code' => 'small', 'amount' => '10.00'],
            ['code' => 'large', 'amount' => '100.00'],
        ];

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertSame(['large', 'small', 'missing'], array_column($result['what_for']['items'], 'code'));
    }

    public function test_it_calculates_ratios_for_integer_amounts_and_keeps_provenance_metadata(): void
    {
        $overview = $this->overview();
        $overview['institutional_distribution']['denominator'] = '3.00';
        $overview['institutional_distribution']['items'] = [['code' => 'integer', 'amount' => 1]];
        $overview['institutional_distribution']['source_url'] = 'https://example.test/source';
        $overview['institutional_distribution']['source_page'] = 12;
        $overview['institutional_distribution']['dataset'] = 'institutions';

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertSame('33.33', $result['who_spends']['items'][0]['percentage']);
        $this->assertSame('33.33', $result['who_spends']['items'][0]['per_100']);
        $this->assertSame([
            'source' => 'INSEE',
            'source_url' => 'https://example.test/source',
            'dataset' => 'institutions',
            'source_page' => 12,
        ], $result['who_spends']['provenance']);
    }

    public function test_it_truncates_large_ratio_calculations_without_float_precision_loss(): void
    {
        $overview = $this->overview();
        $overview['institutional_distribution']['denominator'] = '1000000000000000000.00';
        $overview['institutional_distribution']['items'] = [[
            'code' => 'large_amount',
            'amount' => '123499999999999999.99',
        ]];

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertSame('12.34', $result['who_spends']['items'][0]['percentage']);
        $this->assertSame('12.34', $result['who_spends']['items'][0]['per_100']);
    }

    public function test_it_preserves_accounting_basis_values_for_both_revenue_items(): void
    {
        $overview = $this->overview();
        $overview['revenues']['public_revenues']['accounting_basis'] = 'alternate_national_basis';
        $overview['revenues']['state_budget_revenues']['accounting_basis'] = 'alternate_budget_basis';

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertSame('alternate_national_basis', $result['revenues']['items'][0]['accounting_basis']);
        $this->assertSame('alternate_budget_basis', $result['revenues']['items'][1]['accounting_basis']);
    }

    public function test_it_filters_null_revenue_provenance_and_reindexes_remaining_values(): void
    {
        $overview = $this->overview();
        $overview['revenues']['public_revenues']['source'] = null;
        $overview['revenues']['public_revenues']['dataset'] = null;
        $overview['revenues']['state_budget_revenues']['dataset'] = 'state-dataset';

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertSame(['sources' => ['Budget'], 'datasets' => ['state-dataset']], $result['revenues']['provenance']);
    }

    public function test_it_uses_only_the_precision_needed_for_the_displayed_percentage(): void
    {
        $overview = $this->overview();
        $overview['institutional_distribution']['denominator'] = '100000.00';
        $overview['institutional_distribution']['items'] = [['code' => 'boundary', 'amount' => '12349.00']];

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertSame('12.34', $result['who_spends']['items'][0]['percentage']);
        $this->assertSame('12.34', $result['who_spends']['items'][0]['per_100']);
    }

    /** @return array<string, mixed> */
    private function overview(): array
    {
        return [
            'year' => 2024,
            'public_finances' => [
                'expenditure' => ['amount' => '120.00'],
                'quality' => ['status' => 'validated'],
                'source' => 'INSEE',
                'dataset' => 'public',
            ],
            'institutional_distribution' => [
                'amount' => '100.00',
                'denominator' => '200.00',
                'items' => [['code' => 'half', 'amount' => '100.00']],
                'quality' => ['status' => 'validated'],
                'source' => 'INSEE',
            ],
            'functional_distribution' => [
                'amount' => '100.00',
                'items' => [
                    ['code' => 'small', 'amount' => '10.00'],
                    ['code' => 'large', 'amount' => '90.00'],
                ],
                'quality' => ['status' => 'validated'],
            ],
            'state_budget' => [
                'amount' => '80.00',
                'distribution' => [['code' => 'mission', 'amount' => '80.00']],
                'quality' => ['status' => 'validated'],
                'source' => 'Budget',
                'dataset' => 'state',
            ],
            'revenues' => [
                'public_revenues' => [
                    'amount' => '90.00',
                    'quality' => ['status' => 'validated'],
                    'accounting_basis' => 'national_accounts',
                    'source' => 'INSEE',
                    'dataset' => 'public-revenue',
                ],
                'state_budget_revenues' => [
                    'amount' => '70.00',
                    'quality' => ['status' => 'validated'],
                    'accounting_basis' => 'budgetary',
                    'source' => 'Budget',
                    'dataset' => 'state-revenue',
                ],
            ],
        ];
    }
}
