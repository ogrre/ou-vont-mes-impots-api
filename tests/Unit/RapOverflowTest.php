<?php

namespace Tests\Unit;

use App\Console\Commands\ImportRap;
use App\Services\Rap\RapTextParser;
use PHPUnit\Framework\TestCase;

class RapOverflowTest extends TestCase
{
    public function test_ambiguous_columns_remain_null_and_require_review(): void
    {
        $text = "2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS\n"
            ."11 – Remboursements  107 982 187 077 107 982 187 077\n"
            ."107 982 187 077 107 982 187 077\nTotal des AE consommées  107 982 187 077";
        $parsed = (new RapTextParser)->parse($text, '200', 'Remboursements');
        $this->assertNull($parsed['actions'][0]['ae_consumed']);
        $this->assertTrue($parsed['actions'][0]['review_required']);
        $this->assertTrue($parsed['review_required']);
        $this->assertNull($parsed['validation']['differences']['ae_consumed']['actions_sum']);
        $this->assertStringNotContainsString('9223372036854775807', json_encode($parsed));
    }

    public function test_negative_middle_columns_keep_the_consumption_row(): void
    {
        $text = "2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS\n"
            ."01 – Action  200 000  -1 234,56  198 765,44\n200 000  -2 345,67  197 654,33";
        $parsed = (new RapTextParser)->parse($text, '999', 'Test');
        $this->assertSame('197654.33', $parsed['actions'][0]['ae_consumed']);
        $this->assertSame('-2345.67', $parsed['actions'][0]['amount_rows'][1][1]);
    }

    public function test_import_guard_rejects_old_saturated_json_values(): void
    {
        $method = new \ReflectionMethod(ImportRap::class, 'assertSafeAmount');
        $this->expectException(\RuntimeException::class);
        $method->invoke(new ImportRap, '9223372036854775807.00');
    }
}
