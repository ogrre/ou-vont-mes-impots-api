<?php

namespace Tests\Feature\Api\V1;

use App\Models\Classification;
use App\Models\ClassificationItem;
use App\Models\DatasetFile;
use App\Services\Api\PublicFinanceQuery;
use App\Services\Imports\InseeCofogXlsxImporter;
use App\Services\Imports\InseePublicAccountsXlsxImporter;
use App\Services\Imports\StateBudgetRevenueCsvImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFinanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_imported_years_sources_categories_and_history(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(InseePublicAccountsXlsxImporter::class)->import(
            DatasetFile::query()->where('slug', 'insee-t-3203')->firstOrFail(),
            base_path('data/series-historiques/insee/T_3203_fr.xlsx'),
        );

        $this->getJson('/api/v1/years')->assertOk()->assertJsonPath('years.0', 1978)->assertJsonPath('years.47', 2025);
        $this->getJson('/api/v1/sources')->assertOk()->assertJsonFragment(['code' => 'insee-t-3203'])->assertJsonStructure(['sources' => [['code', 'name', 'source', 'publisher', 'accounting_system', 'scope', 'first_year', 'last_year']]]);
        $this->getJson('/api/v1/categories/insee_accounting')->assertOk()->assertJsonPath('classification', 'insee_accounting')->assertJsonStructure(['classification', 'categories' => [['id', 'code', 'slug', 'name', 'description', 'parent_id']]]);
        $this->getJson('/api/v1/history?metric=expenditure&from=1978&to=1979')->assertOk()->assertJsonPath('items.0.year', 1978);
    }

    public function test_it_exposes_the_budget_state_hierarchy_routes_with_quality_metadata(): void
    {
        $payload = ['year' => 2024, 'items' => [['code' => '01', 'label' => 'Mission test', 'year' => 2024, 'ae' => ['lfi' => '100.00', 'execution' => '90.00'], 'cp' => ['lfi' => '100.00', 'execution' => '90.00'], 'quality' => ['status' => 'review_required', 'reason' => 'Contrôle à revoir', 'source' => 'Budget.gouv', 'source_page' => 12], 'provenance' => ['source_url' => 'https://www.budget.gouv.fr', 'dataset' => 'state-budget-rap-2024']]], 'coverage' => ['programmes_total' => 183, 'parsed' => 178, 'validated' => 139, 'review_required' => 39, 'not_importable' => 5]];
        $mock = $this->mock(PublicFinanceQuery::class);
        $mock->shouldReceive('budgetStateMissions')->with(2024)->andReturn($payload);

        $this->getJson('/api/v1/budget-state/2024/missions')->assertOk()->assertJsonPath('coverage.parsed', 178)->assertJsonPath('items.0.quality.status', 'review_required')->assertJsonPath('items.0.provenance.dataset', 'state-budget-rap-2024');
    }

    public function test_it_exposes_distribution_defaults_and_denominator(): void
    {
        $mock = $this->mock(PublicFinanceQuery::class);
        $mock->shouldReceive('budgetStateDistribution')->with(2024, null, null, 'payment_credit', 'executed', 'per_100')->andReturn(['year' => 2024, 'measurement' => 'payment_credit', 'stage' => 'executed', 'denominator' => '100.00', 'items' => [['code' => '01', 'amount' => '100.00', 'percent' => '100.00', 'per_100' => '100.00', 'quality_status' => 'validated']], 'quality' => ['coverage_percent' => '100.00']]);

        $this->getJson('/api/v1/budget-state/2024/distribution')->assertOk()->assertJsonPath('measurement', 'payment_credit')->assertJsonPath('stage', 'executed')->assertJsonPath('denominator', '100.00')->assertJsonPath('items.0.per_100', '100.00');
    }

    public function test_it_exposes_global_search_filters_and_canonical_identifiers(): void
    {
        $payload = ['query' => 'enseignement', 'year' => 2024, 'items' => [['type' => 'mission', 'code' => 'EDU', 'label' => 'Enseignement scolaire', 'year' => 2024, 'scope' => 'state_budget_programme_action', 'classification' => 'state_budget_programme_action', 'parent' => null, 'breadcrumb' => [['type' => 'mission', 'code' => 'EDU', 'label' => 'Enseignement scolaire']], 'amount' => '100.00', 'quality_status' => 'validated']]];
        $mock = $this->mock(PublicFinanceQuery::class);
        $mock->shouldReceive('search')->with('enseignement', 2024, null, null, 20)->andReturn($payload);

        $this->getJson('/api/v1/search?q=enseignement&year=2024')
            ->assertOk()
            ->assertJsonPath('items.0.type', 'mission')
            ->assertJsonPath('items.0.code', 'EDU')
            ->assertJsonMissingPath('items.0.route');
    }

    public function test_global_search_validates_and_caps_limit(): void
    {
        $this->getJson('/api/v1/search?q=a&limit=51')->assertUnprocessable()->assertJsonValidationErrors(['q', 'limit']);
    }

    public function test_it_exposes_a_charged_cofog_detail(): void
    {
        $payload = ['year' => 2024, 'code' => 'GF10', 'label' => 'Protection sociale', 'description' => 'La protection sociale regroupe les retraites.', 'amount' => '693000000000.00', 'denominator' => '693000000000.00', 'items' => [['code' => 'GF101', 'label' => 'Maladie', 'description' => 'Les dépenses de maladie.', 'amount' => '250000000000.00', 'percent' => '36.07', 'quality_status' => 'validated']], 'quality' => ['status' => 'validated'], 'source' => 'INSEE', 'dataset' => 'insee-t-3301', 'accounting_basis' => 'national_accounts', 'scope' => 'general_government', 'measurement_type' => 'expenditure', 'stage' => 'execution', 'consolidation' => 'consolidated'];
        $mock = $this->mock(PublicFinanceQuery::class);
        $mock->shouldReceive('cofogDetail')->with(2024, 'GF10')->andReturn($payload);

        $this->getJson('/api/v1/cofog/2024/GF10')->assertOk()->assertJsonPath('code', 'GF10')->assertJsonPath('label', 'Protection sociale')->assertJsonPath('description', 'La protection sociale regroupe les retraites.')->assertJsonPath('items.0.description', 'Les dépenses de maladie.')->assertJsonPath('items.0.amount', '250000000000.00');
    }

    public function test_overview_keeps_national_accounts_and_state_budget_separate_and_reports_missing_2024_datasets(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(InseePublicAccountsXlsxImporter::class)->import(
            DatasetFile::query()->where('slug', 'insee-t-3201')->firstOrFail(),
            base_path('data/series-historiques/insee/T_3201_fr.xlsx'),
        );

        $response = $this->getJson('/api/v1/overview/2024')->assertOk();

        $response->assertJsonPath('public_finances.accounting_basis', 'national_accounts')
            ->assertJsonPath('public_finances.scope', 'general_government')
            ->assertJsonPath('public_finances.expenditure.amount', '1672589200000.00')
            ->assertJsonPath('state_budget.accounting_basis', 'budgetary')
            ->assertJsonPath('state_budget.scope', 'french_state_budget')
            ->assertJsonPath('functional_distribution.amount', null)
            ->assertJsonPath('functional_distribution.quality.status', 'not_importable')
            ->assertJsonPath('revenues.public_revenues.accounting_basis', 'national_accounts')
            ->assertJsonPath('revenues.state_budget_revenues.amount', null)
            ->assertJsonPath('revenues.state_budget_revenues.quality.status', 'not_importable')
            ->assertJsonPath('institutional_distribution.quality.status', 'not_importable');

        $response->assertJsonPath('public_finances.measurement_type', 'expenditure_and_revenue')
            ->assertJsonPath('public_finances.stage', 'execution')
            ->assertJsonPath('public_finances.consolidation', 'consolidated')
            ->assertJsonPath('public_finances.year', 2024)
            ->assertJsonPath('public_finances.dataset', 'insee-t-3201')
            ->assertJsonPath('public_finances.source', 'INSEE')
            ->assertJsonPath('methodology.separation_rule', 'Les comptes nationaux et la comptabilité budgétaire sont exposés séparément et ne sont jamais additionnés.');

        $this->assertSame([
            'national_accounts' => 'Comptes nationaux INSEE : administrations publiques consolidées.',
            'budget_accounting' => 'Budget de l’État : crédits de paiement exécutés du PLRG/RAP.',
            'separation_rule' => 'Les comptes nationaux et la comptabilité budgétaire sont exposés séparément et ne sont jamais additionnés.',
        ], $response->json('methodology'));
        $this->assertSame([
            'scope', 'accounting_basis', 'measurement_type', 'stage', 'consolidation', 'year', 'amount',
            'expenditure', 'revenue', 'balance', 'dataset', 'source', 'quality',
        ], array_keys($response->json('public_finances')));
        foreach (['expenditure', 'revenue', 'balance'] as $key) {
            $this->assertSame(['amount', 'unit', 'year', 'dataset', 'source', 'source_page'], array_keys($response->json("public_finances.{$key}")));
        }
        $this->assertSame([
            'scope', 'basis', 'measurement', 'stage', 'consolidation', 'dataset', 'source', 'quality',
            'accounting_basis', 'measurement_type', 'year', 'amount', 'items', 'denominator',
        ], array_keys($response->json('state_budget')));
        $this->assertArrayHasKey('public_revenues', $response->json('revenues'));
        $this->assertArrayHasKey('state_budget_revenues', $response->json('revenues'));
    }

    public function test_overview_exposes_imported_cofog_and_budget_revenues_without_merging_accounting_bases(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(InseeCofogXlsxImporter::class)->import(DatasetFile::where('slug', 'insee-t-3301')->firstOrFail(), base_path('data/2024/insee/T_3301.xlsx'));
        app(StateBudgetRevenueCsvImporter::class)->import(DatasetFile::where('slug', 'state-budget-revenue-execution-2024')->firstOrFail(), base_path('data/2024/budget-etat/fiscalite/Annexe1-Etat_Recettes.csv'));

        $this->getJson('/api/v1/overview/2024')->assertOk()
            ->assertJsonPath('functional_distribution.accounting_basis', 'national_accounts')
            ->assertJsonPath('functional_distribution.items.0.percent', '10.83')
            ->assertJsonPath('revenues.public_revenues.accounting_basis', 'national_accounts')
            ->assertJsonPath('revenues.state_budget_revenues.accounting_basis', 'budgetary')
            ->assertJsonPath('revenues.state_budget_revenues.stage', 'execution')
            ->assertJsonPath('revenues.state_budget_revenues.quality.status', 'validated');
    }

    public function test_overview_builds_the_institutional_distribution_from_all_three_sector_datasets(): void
    {
        $this->seed(DatabaseSeeder::class);
        foreach ([
            'insee-t-3201' => 'T_3201_fr.xlsx',
            'insee-t-3202' => 'T_3202_fr.xlsx',
            'insee-t-3205' => 'T_3205_fr.xlsx',
            'insee-t-3212' => 'T_3212_fr.xlsx',
        ] as $slug => $filename) {
            app(InseePublicAccountsXlsxImporter::class)->import(
                DatasetFile::where('slug', $slug)->firstOrFail(),
                base_path('data/series-historiques/insee/'.$filename),
            );
        }

        $payload = $this->getJson('/api/v1/overview/2024')->assertOk()->json();

        $this->assertSame(
            ['central_government', 'local_government', 'social_security'],
            array_column($payload['institutional_distribution']['items'], 'code'),
        );
        $this->assertSame('review_required', $payload['institutional_distribution']['quality']['status']);
        $this->assertSame('1672589200000.00', $payload['institutional_distribution']['denominator']);
        foreach ($payload['institutional_distribution']['items'] as $item) {
            $this->assertSame(2024, $item['year']);
            $this->assertSame('review_required', $item['quality_status']);
            $this->assertSame('INSEE', $item['provenance']['source']);
            $this->assertNotEmpty($item['amount']);
        }
    }

    public function test_home_contract_exposes_stable_frontend_blocks_and_global_metadata(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(InseePublicAccountsXlsxImporter::class)->import(DatasetFile::where('slug', 'insee-t-3201')->firstOrFail(), base_path('data/series-historiques/insee/T_3201_fr.xlsx'));

        $this->getJson('/api/v1/home/2024')->assertOk()
            ->assertJsonStructure(['data_year', 'reference_year', 'generated_at', 'methodology_version', 'headline', 'public_spending', 'who_spends', 'what_for', 'state_budget', 'revenues'])
            ->assertJsonPath('data_year', 2024)
            ->assertJsonPath('reference_year', 2024)
            ->assertJsonStructure(['public_spending' => ['title', 'description', 'amount', 'unit', 'items', 'percentage', 'per_100', 'quality_status', 'quality', 'methodology', 'provenance'], 'revenues' => ['items', 'sub_blocks' => ['public_revenues', 'state_budget_revenues']]])
            ->assertJsonPath('revenues.items.0.percentage', null)
            ->assertJsonPath('revenues.items.1.per_100', null);
    }

    public function test_global_search_matches_label_code_and_raw_label_and_orders_exact_code_first(): void
    {
        $classification = Classification::query()->create([
            'code' => 'search_test',
            'name' => 'Recherche',
        ]);
        $parent = ClassificationItem::query()->create([
            'classification_id' => $classification->id,
            'code' => 'PARENT',
            'official_label' => 'Parent',
            'slug' => 'parent',
            'metadata' => [],
        ]);
        ClassificationItem::query()->create([
            'classification_id' => $classification->id,
            'parent_id' => $parent->id,
            'code' => 'EDU',
            'official_label' => 'Éducation',
            'slug' => 'education',
            'metadata' => ['raw_label' => 'École et formation'],
        ]);

        $result = app(PublicFinanceQuery::class)->search('EDU', null, null, null);

        $this->assertSame('EDU', $result['items'][0]['code']);
        $this->assertSame('Éducation', $result['items'][0]['label']);
        $this->assertSame('search_test', $result['items'][0]['scope']);
        $this->assertSame(['type' => 'classification', 'code' => 'PARENT', 'label' => 'Parent'], $result['items'][0]['parent']);
        $this->assertSame([
            ['type' => 'classification', 'code' => 'PARENT', 'label' => 'Parent'],
            ['type' => 'classification', 'code' => 'EDU', 'label' => 'Éducation'],
        ], $result['items'][0]['breadcrumb']);
        $this->assertNull($result['items'][0]['amount']);

        $rawLabelResult = app(PublicFinanceQuery::class)->search('ecole', null, null, null);
        $this->assertSame('EDU', $rawLabelResult['items'][0]['code']);

        $filtered = app(PublicFinanceQuery::class)->search('education', null, 'search_test', 'classification', 1);
        $this->assertCount(1, $filtered['items']);
        $this->assertSame('EDU', $filtered['items'][0]['code']);
        $this->assertSame([], app(PublicFinanceQuery::class)->search('education', null, null, 'mission')['items']);
    }
}
