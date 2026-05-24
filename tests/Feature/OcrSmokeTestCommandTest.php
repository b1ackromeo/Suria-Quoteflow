<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Services\Documents\BusinessDocumentCaptureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class OcrSmokeTestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_ocr_smoke_test_passes_when_required_fields_are_extracted(): void
    {
        config(['ocr.enabled' => true]);

        $this->app->instance(BusinessDocumentCaptureService::class, new class extends BusinessDocumentCaptureService {
            public function extract(Attachment $attachment): array
            {
                return [
                    'engine' => 'test-capture',
                    'raw_text' => 'Supplier invoice smoke test.',
                    'extracted_fields' => [
                        'supplier_name' => 'Best Supplies Sdn Bhd',
                        'invoice_number' => 'OCR-2026-0001',
                        'invoice_date' => '2026-05-24',
                        'total' => '108.00',
                    ],
                ];
            }
        });

        [$exitCode, $output] = $this->runOcrSmokeTest();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('OCR smoke test passed.', $output);
        $this->assertStringContainsString('Invoice no.: OCR-2026-0001', $output);
    }

    public function test_ocr_smoke_test_fails_when_required_fields_are_missing(): void
    {
        config(['ocr.enabled' => true]);

        $this->app->instance(BusinessDocumentCaptureService::class, new class extends BusinessDocumentCaptureService {
            public function extract(Attachment $attachment): array
            {
                return [
                    'engine' => 'test-capture',
                    'raw_text' => 'Unreadable smoke test.',
                    'extracted_fields' => [
                        'total' => '108.00',
                    ],
                ];
            }
        });

        [$exitCode, $output] = $this->runOcrSmokeTest();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('OCR smoke test failed. Missing extracted field(s): supplier_name, invoice_number, invoice_date', $output);
    }

    public function test_ocr_smoke_test_fails_when_ocr_is_disabled(): void
    {
        config(['ocr.enabled' => false]);

        [$exitCode, $output] = $this->runOcrSmokeTest();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('OCR is disabled.', $output);
    }

    private function runOcrSmokeTest(): array
    {
        $this->withoutMockingConsoleOutput();

        $command = Artisan::all()['quoteflow:ocr-smoke-test'];
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([], [
            'interactive' => false,
        ]);

        return [$exitCode, $tester->getDisplay()];
    }
}
