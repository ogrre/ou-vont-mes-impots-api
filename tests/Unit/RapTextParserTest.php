<?php

namespace Tests\Unit;

use App\Services\Rap\RapPdfExtractor;
use App\Services\Rap\RapTextParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RapTextParserTest extends TestCase
{
    public function test_it_parses_the_action_table_by_textual_anchor(): void
    {
        $text = <<<'TEXT'
2024 / AUTORISATIONS D'ENGAGEMENT
99 – Action hors section                           900       900       900
2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS
2024 / AUTORISATIONS D'ENGAGEMENT
02 – Action sans montants
01 – Action de test                                      100       200       300       300
                                                           10        20        30
03 – Action AE seulement                                  7         8         9
Total des AE prévues en LFI                              300
Total des AE consommées                                  30
2024 / crédits de paiement
01 – Action de test                                      100       200       300       300
                                                           11        21        32
Total des CP prévus en LFI                              300
Total des CP consommés                                  32
2023 / présentation par action
99 – Action de l’exercice précédent                800       800       800
TEXT;

        $result = app(RapTextParser::class)->parse($text, '999', 'Programme test');

        $this->assertSame('300.00', $result['actions'][0]['ae_lfi']);
        $this->assertSame('30.00', $result['actions'][0]['ae_consumed']);
        $this->assertSame('300.00', $result['actions'][0]['cp_lfi']);
        $this->assertSame('32.00', $result['actions'][0]['cp_consumed']);
        $this->assertArrayNotHasKey('cp_amounts', $result['actions'][1]);
        $this->assertSame('01', $result['actions'][0]['code']);
        $this->assertSame('Action de test', $result['actions'][0]['label']);
        $this->assertSame('action', $result['actions'][0]['hierarchy_level']);
        $this->assertNull($result['actions'][0]['parent_action_code']);
        $this->assertTrue($result['actions'][0]['contributes_to_program_total']);
        $this->assertSame(['100.00', '200.00', '300.00', '300.00', '10.00', '20.00', '30.00'], $result['actions'][0]['amounts']);
        $this->assertSame(['100.00', '200.00', '300.00', '300.00'], $result['actions'][0]['amount_rows'][0]);
        $this->assertSame(['10.00', '20.00', '30.00'], $result['actions'][0]['amount_rows'][1]);
        $this->assertSame([], $result['actions'][0]['titles']);
        $this->assertFalse($result['actions'][0]['review_required']);
        $this->assertSame(['01', '03'], array_column($result['actions'], 'code'));
        $this->assertSame(['actions' => 2, 'sub_actions' => 0], $result['counts']);
        $this->assertSame(['year', 'program', 'actions', 'parser', 'warnings', 'validation', 'review_required', 'counts'], array_keys($result));
        $this->assertSame(['code' => '999', 'name' => 'Programme test'], $result['program']);
        $this->assertSame(['ae_lfi' => '300.00', 'ae_consumed' => '30.00', 'cp_lfi' => '300.00', 'cp_consumed' => '32.00'], $result['validation']['totals']);
        $this->assertSame(['ae_consumed', 'cp_lfi', 'cp_consumed'], array_keys($result['validation']['differences']));
    }

    public function test_it_keeps_ae_rows_when_the_cp_section_is_absent(): void
    {
        $text = <<<'TEXT'
2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS
2024 / AUTORISATIONS D'ENGAGEMENT
01 – Action AE                                     100       200       300
TEXT;

        $result = app(RapTextParser::class)->parse($text, '123', 'Programme sans CP');

        $this->assertSame(['01'], array_column($result['actions'], 'code'));
        $this->assertSame(['100.00', '200.00', '300.00'], $result['actions'][0]['amounts']);
        $this->assertArrayNotHasKey('cp_amounts', $result['actions'][0]);
        $this->assertNull($result['validation']['totals']['cp_lfi']);
        $this->assertNull($result['validation']['totals']['cp_consumed']);
    }

    #[DataProvider('reviewRequiredCases')]
    public function test_cp_columns_do_not_clear_or_create_the_review_flag_at_the_boundary(int $aeColumns, int $cpColumns, bool $expected): void
    {
        $ae = implode('  ', range(1, $aeColumns));
        $cp = implode('  ', range(1, $cpColumns));
        $text = "2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS\n"
            ."01 – Action test\n{$ae}\n2024 / CRÉDITS DE PAIEMENT\n01 – Action test\n{$cp}";

        $result = app(RapTextParser::class)->parse($text, '001', 'Programme');

        $this->assertSame($expected, $result['actions'][0]['review_required']);
    }

    /** @return iterable<string,array{int,int,bool}> */
    public static function reviewRequiredCases(): iterable
    {
        yield 'incomplete AE stays under review despite complete CP' => [5, 6, true];
        yield 'six AE and CP columns meet the threshold' => [6, 6, false];
    }

    public function test_it_keeps_institutional_credits_separate_from_ae_and_cp(): void
    {
        $text = <<<'TEXT'
Préambule ignoré  999  999  999
intitulé de l’action   Dotation 2024   Crédits ouverts   Dépenses constatées
 Sénat                  341 864 000    341 864 000       341 864 000
Total                  341 864 000    341 864 000       341 864 000
TEXT;

        $result = app(RapTextParser::class)->parse($text, '521', 'Sénat');
        $action = $result['actions'][0];

        $this->assertSame('institutional_credits', $result['format']);
        $this->assertSame('341864000.00', $action['special_measurements']['allocation']);
        $this->assertSame('341864000.00', $action['special_measurements']['credits_opened']);
        $this->assertSame('341864000.00', $action['special_measurements']['expenditure_recorded']);
        $this->assertNull($action['ae_lfi']);
        $this->assertNull($action['cp_lfi']);
        $this->assertSame('Sénat', $action['label']);
        $this->assertSame('action', $action['hierarchy_level']);
        $this->assertSame([], $action['amount_rows']);
        $this->assertSame([], $action['titles']);
        $this->assertFalse($action['review_required']);
        $this->assertSame(2024, $result['year']);
        $this->assertSame(['code' => '521', 'name' => 'Sénat'], $result['program']);
        $this->assertSame(['anchor' => 'institutional credits table', 'amount_mapping' => 'allocation-opened-recorded-v1'], $result['parser']);
        $this->assertSame(['tolerance_eur' => 1000, 'totals' => [], 'differences' => [], 'review_required' => false], $result['validation']);
    }

    public function test_it_handles_the_four_column_institutional_format_without_conversion(): void
    {
        $text = <<<'TEXT'
Intitulé de l’action   Dotation prévue en LFI   Dotation supplémentaire   Total des crédits ouverts   Dépenses constatées
Assemblée nationale    607 647 569              19 534 273                627 181 842                 600 000 000
Total                   607 647 569              19 534 273                627 181 842                 627 181 842
TEXT;

        $result = app(RapTextParser::class)->parse($text, '511', 'Assemblée nationale');

        $this->assertSame('607647569.00', $result['actions'][0]['special_measurements']['allocation']);
        $this->assertSame('627181842.00', $result['actions'][0]['special_measurements']['credits_opened']);
        $this->assertSame('600000000.00', $result['actions'][0]['special_measurements']['expenditure_recorded']);
    }

    public function test_it_reads_multicolumn_totals_after_the_label_and_detects_mismatches(): void
    {
        $text = <<<'TEXT'
2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS
2024 / AUTORISATIONS D'ENGAGEMENT
01 – Action de test                                      1       2       3       4       5       6
                                                           1       2       3       4       5       6
TOTAL DES AE PRÉVUES EN LFI
                                                           6       2007
TOTAL DES AE CONSOMMÉES                                  6
2024 / CRÉDITS DE PAIEMENT
01 – Action de test                                      1       2       3       4       5       6
                                                           1       2       3       4       5       6
TOTAL DES CP PRÉVUS EN LFI                               6
TOTAL DES CP CONSOMMÉS
                                                           6       2007
2023 / présentation par action
TEXT;

        $result = app(RapTextParser::class)->parse($text, '999', 'Programme test');

        $this->assertSame('2007.00', $result['validation']['totals']['ae_lfi']);
        $this->assertSame('6.00', $result['validation']['totals']['ae_consumed']);
        $this->assertSame('6.00', $result['validation']['totals']['cp_lfi']);
        $this->assertSame('2007.00', $result['validation']['totals']['cp_consumed']);
        $this->assertSame(1000, $result['validation']['tolerance_eur']);
        $this->assertTrue($result['validation']['review_required']);
        $this->assertSame('6.00', $result['actions'][0]['ae_lfi']);
        $this->assertSame('6.00', $result['actions'][0]['cp_consumed']);
        $this->assertArrayHasKey('ae_lfi', $result['validation']['differences']);
        $this->assertArrayHasKey('cp_consumed', $result['validation']['differences']);
        $this->assertSame('6.00', $result['validation']['differences']['ae_lfi']['actions_sum']);
        $this->assertSame('2007.00', $result['validation']['differences']['ae_lfi']['programme_total']);
        $this->assertSame('-2001.00', $result['validation']['differences']['ae_lfi']['difference']);
    }

    public function test_it_rejects_an_anchor_without_a_numbered_action(): void
    {
        $text = '2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aucune action chiffrée');

        app(RapTextParser::class)->parse($text, '999', 'Programme test');
    }

    public function test_it_parses_special_institution_rows_and_ignores_non_action_lines(): void
    {
        $text = <<<'TEXT'
INTITULÉ de l’action   dotation 2024   crédits ouverts   dépenses constatées
INTITULÉ ancienne ligne 1  2  3
Intitulé  sans chiffres
Libellé partiel        10             20
Sénat                  341 864 000    341 864 000       341 864 000
Commission des dépenses 10             20              30
Observatoire Total local 40            50              60
Assemblée nationale    607 647 569    19 534 273        627 181 842       600 000 000
   total               948 000 000    948 000 000       948 000 000
TEXT;
        $text .= "\fAction après le saut  1  2  3";

        $result = app(RapTextParser::class)->parse($text, '511', 'Institution');

        $this->assertSame(['1', '2', '3', '4'], array_column($result['actions'], 'code'));
        $this->assertSame('Sénat', $result['actions'][0]['label']);
        $this->assertSame(['341864000.00', '341864000.00', '341864000.00'], $result['actions'][0]['amounts']);
        $this->assertSame('Commission des dépenses', $result['actions'][1]['label']);
        $this->assertSame('Observatoire Total local', $result['actions'][2]['label']);
        $this->assertSame(['607647569.00', '627181842.00', '600000000.00'], $result['actions'][3]['amounts']);
        $this->assertFalse($result['actions'][0]['contributes_to_program_total']);
        $this->assertSame('institutional_credits', $result['format']);
        $this->assertSame(['actions' => 4, 'sub_actions' => 0], $result['counts']);
        $this->assertSame([], $result['validation']['totals']);
        $this->assertSame(['Ces montants ne sont pas des AE/CP et ne doivent pas être convertis.'], $result['warnings']);
        $this->assertSame(['year', 'program', 'format', 'actions', 'parser', 'review_required', 'warnings', 'validation', 'counts'], array_keys($result));
        $this->assertFalse($result['review_required']);
    }

    public function test_it_rejects_an_empty_special_institution_table(): void
    {
        $text = <<<'TEXT'
Dotation 2024   Crédits ouverts   Dépenses constatées
Total                  100          100          100
TEXT;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Section 2024 par action introuvable.');

        app(RapTextParser::class)->parse($text, '511', 'Institution');
    }

    public function test_it_does_not_add_sub_actions_to_programme_totals(): void
    {
        $text = <<<'TEXT'
2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS
2024 / AUTORISATIONS D'ENGAGEMENT
01 – Parent                                      100       200       300       300
01.1 – Enfant                                    900       900       900       900
Total des AE prévues en LFI                      300
2024 / CRÉDITS DE PAIEMENT
01 – Parent                                      100       200       300       300
01.1 – Enfant                                    900       900       900       900
Total des CP prévus en LFI                       300
TEXT;

        $result = app(RapTextParser::class)->parse($text, '999', 'Programme test');

        $this->assertFalse($result['validation']['review_required']);
        $this->assertTrue($result['actions'][0]['contributes_to_program_total']);
        $this->assertFalse($result['actions'][1]['contributes_to_program_total']);
        $this->assertSame('sub_action', $result['actions'][1]['hierarchy_level']);
        $this->assertSame('01', $result['actions'][1]['parent_action_code']);
    }

    public function test_it_handles_wrapped_labels_missing_totals_and_numeric_formats(): void
    {
        $text = <<<'TEXT'
2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS OUVERTS ET DES CRÉDITS CONSOMMÉS
2024 / AUTORISATIONS D'ENGAGEMENT
02 – Action avec libellé
-5       1234       6
02.1 – Action suivante                            10         20
2024 / CRÉDITS DE PAIEMENT
02 – Action avec libellé                         -5       1234       6
TEXT;

        $result = app(RapTextParser::class)->parse($text, '999', 'Programme test');

        $amounts = array_merge(...array_column($result['actions'], 'amounts'));
        $this->assertContains('1234.00', $amounts);
        $this->assertContains('-5.00', $amounts);
        $this->assertContains('6.00', $amounts);
        $this->assertNull($result['validation']['totals']['ae_lfi']);
        $this->assertTrue($result['actions'][0]['review_required']);
    }

    public function test_it_validates_the_parser_primitives_and_fallbacks(): void
    {
        $parser = app(RapTextParser::class);
        $invoke = static function (string $method, mixed ...$arguments) use ($parser): mixed {
            $reflection = new \ReflectionMethod($parser, $method);
            $reflection->setAccessible(true);

            return $reflection->invoke($parser, ...$arguments);
        };

        $this->assertSame('1234.50', $invoke('amount', "1\u{00a0}234,50"));
        $this->assertSame(['1234.00', '-2.00', '3.00'], $invoke('amountColumns', '+1 234       -2       3'));
        $this->assertNull($invoke('rowTotal', []));
        $this->assertSame('3.00', $invoke('rowTotal', ['1.00', '2.00', '3.00']));
        $finished = $invoke('finish', ['code' => '01', 'label' => 'Action'], ['1  2  3', '1  2 euros']);
        $this->assertSame([['1.00', '2.00', '3.00']], $finished['amount_rows']);
        $this->assertSame(['1.00', '2.00', '3.00'], $finished['amounts']);
        $invalidAmounts = $invoke('finish', ['code' => '02', 'label' => 'Action'], ['note 123']);
        $this->assertSame([], $invalidAmounts['amount_rows']);
        $unicodeAmounts = $invoke('finish', ['code' => '03', 'label' => 'Action'], ["1\u{00a0}234"]);
        $this->assertSame([['1234.00']], $unicodeAmounts['amount_rows']);

        $rows = $invoke('parseRows', "  01 – Première action\nTexte mentionnant Total des AE consommées\n1  2  3\nTexte préfixe 99 – faux\ntotal des AE consommées\n7  8  9\n  02 – Deuxième action  4  5  6");
        $this->assertSame(['01', '02'], array_column($rows, 'code'));
        $this->assertSame(['1.00', '2.00', '3.00'], $rows[0]['amounts']);
        $this->assertSame(['4.00', '5.00', '6.00'], $rows[1]['amounts']);

        $continuation = $invoke('parseRows', "01 – Action\nSuite de libellé sans nombres\n02 – Action suivante  4  5  6");
        $this->assertSame(['01', '02'], array_column($continuation, 'code'));
        $this->assertSame([], $continuation[0]['amounts']);
        $this->assertSame([], $continuation[0]['extracted_lines']);
        $validation = $invoke('validateTotals', "Total des AE prévues en LFI  100\nTotal des AE consommées  100", [
            ['ae_lfi' => '100.00', 'ae_consumed' => '100.00', 'contributes_to_program_total' => true],
            ['ae_lfi' => '9999.00', 'ae_consumed' => '9999.00', 'contributes_to_program_total' => false],
        ]);
        $this->assertSame('100.00', $validation['totals']['ae_lfi']);
        $this->assertSame('100.00', $validation['totals']['ae_consumed']);
        $this->assertSame([], $validation['differences']);
        $this->assertFalse($validation['review_required']);

        $missingAmount = $invoke('validateTotals', 'Total des AE prévues en LFI  1001', [
            ['contributes_to_program_total' => true],
        ]);
        $this->assertSame(['actions_sum' => null, 'programme_total' => '1001.00', 'difference' => null], $missingAmount['differences']['ae_lfi']);

        $atTolerance = $invoke('validateTotals', 'Total des AE prévues en LFI  2000', [
            ['ae_lfi' => '1000.00', 'contributes_to_program_total' => true],
        ]);
        $this->assertSame([], $atTolerance['differences']);
        $this->assertFalse($atTolerance['review_required']);

        $overTolerance = $invoke('validateTotals', 'Total des AE prévues en LFI  2001', [
            ['ae_lfi' => '1000.00'],
        ]);
        $this->assertSame(['actions_sum' => '0.00', 'programme_total' => '2001.00', 'difference' => '-2001.00'], $overTolerance['differences']['ae_lfi']);
        $this->assertTrue($overTolerance['review_required']);
    }

    /** @param array<int,string> $lines */
    #[DataProvider('totalAfterCases')]
    public function test_it_extracts_totals_inline_or_from_the_next_three_lines(array $lines, string $label, ?string $expected): void
    {
        $parser = app(RapTextParser::class);
        $method = new \ReflectionMethod($parser, 'totalAfter');

        $this->assertSame($expected, $method->invoke($parser, $lines, $label));
    }

    /** @return iterable<string,array{array<int,string>,string,?string}> */
    public static function totalAfterCases(): iterable
    {
        yield 'inline columns' => [['Total des recettes  6  2007'], 'Total des recettes', '2007.00'];
        yield 'next line' => [['Total des recettes', '6  2007'], 'Total des recettes', '2007.00'];
        yield 'third following line' => [['Total des recettes', 'texte', 'texte', '  2007'], 'Total des recettes', '2007.00'];
        yield 'outside lookahead window' => [['Total des recettes', 'texte', 'texte', 'texte', '2007'], 'Total des recettes', null];
        yield 'accented label matches different case' => [['TOTAL DES RECETTES PRÉVUES EN LFI  6  2007'], 'Total des recettes prévues en LFI', '2007.00'];
        yield 'ignores a trailing text column' => [['Total des recettes  2007  note'], 'Total des recettes', '2007.00'];
        yield 'uppercase accented search label' => [['TOTAL DES RECETTES PRÉVUES EN LFI 2007'], 'TOTAL DES RECETTES PRÉVUES EN LFI', '2007.00'];
        yield 'multibyte label adjacent to amount' => [['Total des AE consommées2007'], 'Total des AE consommées', '2007.00'];
        yield 'nonbreaking space thousands separator' => [["Total des recettes  1\u{00a0}234"], 'Total des recettes', '1234.00'];
        yield 'rejects numeric prefix followed by letters' => [['Total des recettes  xyz2007'], 'Total des recettes', null];
        yield 'rejects a text suffix after number' => [['Total des recettes  2007 note'], 'Total des recettes', null];
        yield 'label absent' => [['Autre ligne'], 'Total des recettes', null];
    }

    #[DataProvider('amountLineCases')]
    public function test_it_recognizes_supported_amount_line_shapes(string $line, bool $expected): void
    {
        $parser = app(RapTextParser::class);
        $method = new \ReflectionMethod($parser, 'isAmountLine');

        $this->assertSame($expected, $method->invoke($parser, $line));
    }

    /** @return iterable<string,array{string,bool}> */
    public static function amountLineCases(): iterable
    {
        yield 'negative middle column' => ['123 456  -1 234,50  122 221,50', true];
        yield 'signed decimal' => ['- 1 234,50', true];
        yield 'integer with outer spaces' => [' 1 234 ', true];
        yield 'narrow nonbreaking space thousands separator' => ["1\u{202f}234", true];
        yield 'plain text' => ['un texte', false];
        yield 'text prefix' => ['montant 123', false];
        yield 'text suffix' => ['123 euros', false];
        yield 'empty value' => ['', false];
    }

    #[DataProvider('splitLabelColumnsCases')]
    public function test_it_separates_labels_from_numeric_columns(string $value, array $expected): void
    {
        $parser = app(RapTextParser::class);
        $method = new \ReflectionMethod($parser, 'splitLabelColumns');

        $this->assertSame($expected, $method->invoke($parser, $value));
    }

    /** @return iterable<string,array{string,array{string,string|null}}> */
    public static function splitLabelColumnsCases(): iterable
    {
        yield 'several signed amounts' => ['Libellé     10   -2', ['Libellé', '10   -2']];
        yield 'trims label and numeric columns around separators' => ['  Libellé    10   -2  ', ['Libellé', '10   -2']];
        yield 'does not parse a numeric suffix after a line break' => ["junk\nLibellé   10", ["junk\nLibellé   10", null]];
        yield 'recognizes nonbreaking column separators' => ["Libellé\u{00a0}\u{00a0}10", ['Libellé', '10']];
        yield 'label without amount columns' => ['  Libellé sans colonnes  ', ['Libellé sans colonnes', null]];
    }

    #[DataProvider('rapFixtures')]
    public function test_reference_rap_pdfs_are_parseable(string $filename, int $minimumActions): void
    {
        $path = base_path('data/2024/budget-etat/performance/'.$filename);
        if (! is_file($path) || trim((string) shell_exec('command -v pdftotext')) === '') {
            $this->markTestSkipped('Fixture PDF ou pdftotext indisponible dans cet environnement.');
        }
        $result = app(RapTextParser::class)->parse(app(RapPdfExtractor::class)->extract($path), 'fixture', 'Fixture');
        $this->assertGreaterThanOrEqual($minimumActions, $result['counts']['actions']);
        $this->assertSame(2024, $result['year']);
        $this->assertSame(['code' => 'fixture', 'name' => 'Fixture'], $result['program']);
        $this->assertSame('2024 / PRÉSENTATION PAR ACTION ET TITRE DES CRÉDITS', $result['parser']['anchor']);
        $this->assertSame('layout-column-order-v1', $result['parser']['amount_mapping']);
        $this->assertSame($result['counts']['actions'], count($result['actions']));
        $this->assertArrayHasKey('warnings', $result);

        foreach ($result['actions'] as $action) {
            $this->assertArrayHasKey('code', $action);
            $this->assertArrayHasKey('label', $action);
            $this->assertArrayHasKey('hierarchy_level', $action);
            $this->assertArrayHasKey('parent_action_code', $action);
            $this->assertArrayHasKey('contributes_to_program_total', $action);
            $this->assertNotEmpty($action['amounts']);
            $this->assertArrayHasKey('amount_rows', $action);
            $this->assertArrayHasKey('extracted_lines', $action);
            $this->assertArrayHasKey('review_required', $action);
        }
    }

    /** @return array<string,array{string,int}> */
    public static function rapFixtures(): array
    {
        return [
            '101' => ['FR_2024_PLR_JA_PGM_101.pdf', 5],
            '102' => ['FR_2024_PLR_TB_PGM_102.pdf', 10],
            '104' => ['FR_2024_PLR_IA_PGM_104.pdf', 4],
        ];
    }
}
