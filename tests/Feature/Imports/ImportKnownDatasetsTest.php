<?php

namespace Tests\Feature\Imports;

use App\Enums\ImportStatus;
use App\Models\DatasetFile;
use App\Models\ImportBatch;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportKnownDatasetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_checksum_is_skipped_without_a_new_batch(): void
    {
        $this->assertExistingChecksum(ImportStatus::Completed, 0);
    }

    public function test_failed_checksum_is_not_reported_as_success(): void
    {
        $this->assertExistingChecksum(ImportStatus::Failed, 1);
    }

    public function test_running_checksum_is_not_reported_as_success(): void
    {
        $this->assertExistingChecksum(ImportStatus::Running, 1);
    }

    private function assertExistingChecksum(ImportStatus $status, int $exitCode): void
    {
        $this->seed(DatabaseSeeder::class);
        $descriptor = DatasetFile::query()->where('slug', 'state-budget-revenue-execution-2024')->firstOrFail();
        DatasetFile::query()->where('id', '!=', $descriptor->id)->update(['expected_filename' => null]);
        $path = base_path('data/2024/budget-etat/fiscalite/Annexe1-Etat_Recettes.csv');
        $batch = ImportBatch::query()->create([
            'dataset_id' => $descriptor->dataset_id,
            'dataset_file_id' => $descriptor->id,
            'filename' => basename($path),
            'checksum' => hash_file('sha256', $path),
            'status' => $status,
            'started_at' => now(),
        ]);

        $this->artisan('dataset:import-known', ['path' => dirname($path)])->assertExitCode($exitCode);

        $this->assertSame(1, ImportBatch::query()->count());
        $this->assertSame($status, $batch->fresh()->status);
        $this->assertDatabaseCount('financial_observations', 0);
    }
}
