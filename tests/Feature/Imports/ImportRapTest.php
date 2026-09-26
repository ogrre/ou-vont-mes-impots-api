<?php

namespace Tests\Feature\Imports;

use App\Console\Commands\ImportRap;
use App\Models\AccountingScope;
use App\Models\Classification;
use App\Models\Dataset;
use App\Models\FinancialObservation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportRapTest extends TestCase
{
    use RefreshDatabase;

    public function test_reimport_updates_in_place_preserves_provenance_and_rejects_overflow(): void
    {
        $this->seed(DatabaseSeeder::class);
        $dataset = Dataset::query()->firstOrFail();
        $scope = AccountingScope::query()->where('code', 'french_state_budget')->firstOrFail();
        $classification = Classification::query()->create(['code' => 'rap-test', 'name' => 'RAP test']);
        $pdf = tempnam(sys_get_temp_dir(), 'rap-test-');
        file_put_contents($pdf, 'stable source fixture');
        $entry = ['program' => '200', 'name' => 'Remboursements', 'url' => 'https://www.budget.gouv.fr/fixture.pdf'];
        $parsed = ['mission' => 'Remboursements', 'parser' => ['amount_mapping' => 'decimal-v2'], 'review_required' => true, 'actions' => [
            ['code' => '11', 'label' => 'Restitutions', 'ae_lfi' => '100.00', 'ae_consumed' => '107982187077.00', 'cp_lfi' => null, 'cp_consumed' => '107982287904.00', 'review_required' => true],
        ]];
        $method = new \ReflectionMethod(ImportRap::class, 'importParsed');
        $import = fn () => $method->invoke(new ImportRap, $dataset, $entry, $pdf, $parsed, $classification, $scope);
        $import();
        $before = FinancialObservation::query()->orderBy('id')->pluck('id')->all();
        $import();
        $this->assertSame($before, FinancialObservation::query()->orderBy('id')->pluck('id')->all());
        $this->assertCount(3, $before);
        $row = FinancialObservation::query()->where('source_identifier', '200|11|cp_consumed')->firstOrFail();
        $this->assertSame('107982287904.00', $row->amount);
        $this->assertSame($entry['url'], $row->metadata['source_url']);
        $this->assertTrue($row->metadata['review_required']);
        $this->assertSame(1, $row->datasetFile->importBatches()->count());
        $parsed['actions'][0]['cp_consumed'] = '9223372036854775807.00';
        try {
            $method->invoke(new ImportRap, $dataset, $entry, $pdf, $parsed, $classification, $scope);
            $this->fail('Overflow must be rejected before writing financial observations.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Montant RAP non fiable', $e->getMessage());
        }
        $this->assertSame('107982287904.00', $row->fresh()->amount);
        $this->assertSame($before, FinancialObservation::query()->orderBy('id')->pluck('id')->all());
    }
}
