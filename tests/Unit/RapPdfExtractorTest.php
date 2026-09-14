<?php

namespace Tests\Unit;

use App\Services\Rap\RapPdfExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RapPdfExtractorTest extends TestCase
{
    public function test_it_rejects_a_missing_or_unreadable_pdf(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PDF RAP introuvable ou illisible');

        app(RapPdfExtractor::class)->extract(base_path('data/does-not-exist.pdf'));
    }

    public function test_it_rejects_a_readable_directory_instead_of_invoking_pdftotext(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PDF RAP introuvable ou illisible');

        app(RapPdfExtractor::class)->extract(sys_get_temp_dir());
    }

    public function test_it_extracts_text_from_an_existing_readable_pdf(): void
    {
        $path = base_path('data/2024/budget-etat/performance/FR_2024_PLR_JA_PGM_101.pdf');
        if (trim((string) shell_exec('command -v pdftotext')) === '') {
            $this->markTestSkipped('pdftotext indisponible dans cet environnement.');
        }

        $text = app(RapPdfExtractor::class)->extract($path);

        $this->assertNotSame('', trim($text));
        $this->assertStringContainsString('PRÉSENTATION PAR ACTION', $text);
    }

    #[DataProvider('processOutputCases')]
    public function test_it_handles_pdftotext_stdout_stderr_and_exit_status(string $stdout, string $stderr, int $exitCode, ?string $expectedText, ?string $expectedError): void
    {
        $directory = sys_get_temp_dir().'/rap-pdftotext-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $executable = $directory.'/pdftotext';
        $script = "#!/bin/sh\nprintf '%s' ".escapeshellarg($stdout)."\nprintf '%s' ".escapeshellarg($stderr)." >&2\nexit {$exitCode}\n";
        file_put_contents($executable, $script);
        chmod($executable, 0700);

        $previousPath = getenv('PATH');
        putenv('PATH='.$directory.PATH_SEPARATOR.($previousPath ?: ''));

        try {
            $pdf = base_path('data/2024/budget-etat/performance/FR_2024_PLR_JA_PGM_101.pdf');
            if ($expectedError !== null) {
                try {
                    app(RapPdfExtractor::class)->extract($pdf);
                    $this->fail('Une extraction pdftotext invalide doit lever une exception.');
                } catch (RuntimeException $exception) {
                    $this->assertSame($expectedError, $exception->getMessage());
                }

                return;
            }

            $this->assertSame($expectedText, app(RapPdfExtractor::class)->extract($pdf));
        } finally {
            if ($previousPath === false) {
                putenv('PATH');
            } else {
                putenv('PATH='.$previousPath);
            }
            unlink($executable);
            rmdir($directory);
        }
    }

    /** @return iterable<string,array{string,string,int,?string,?string}> */
    public static function processOutputCases(): iterable
    {
        yield 'successful stdout is returned' => ["extracted text\n", '', 0, "extracted text\n", null];
        yield 'stderr is trimmed and reported on nonzero exit' => ['partial stdout', "  conversion failed  \n", 2, null, 'pdftotext a échoué : conversion failed'];
        yield 'whitespace-only stdout is rejected' => [" \n\t", '', 0, null, 'pdftotext a échoué : '];
    }
}
