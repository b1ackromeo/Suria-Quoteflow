<?php

namespace Tests\Unit;

use App\Models\Attachment;
use App\Services\Documents\PaddleOcrDocumentAnalyzer;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaddleOcrDocumentAnalyzerTest extends TestCase
{
    public function test_it_returns_no_candidates_when_disabled(): void
    {
        Storage::fake('local');
        Storage::put('attachments/sample.png', 'image');

        config(['ocr.paddleocr.enabled' => false]);

        $attachment = new Attachment([
            'path' => 'attachments/sample.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ]);

        $this->assertSame([], (new PaddleOcrDocumentAnalyzer())->candidates($attachment));
    }

    public function test_it_returns_no_candidates_when_script_is_missing(): void
    {
        Storage::fake('local');
        Storage::put('attachments/sample.png', 'image');

        config([
            'ocr.paddleocr.enabled' => true,
            'ocr.paddleocr.script' => storage_path('app/missing-paddle-script.py'),
        ]);

        $attachment = new Attachment([
            'path' => 'attachments/sample.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ]);

        $this->assertSame([], (new PaddleOcrDocumentAnalyzer())->candidates($attachment));
    }

    public function test_it_respects_the_configured_file_size_limit(): void
    {
        Storage::fake('local');
        Storage::put('attachments/large.png', 'image');

        config([
            'ocr.paddleocr.enabled' => true,
            'ocr.paddleocr.max_file_kb' => 1,
            'ocr.paddleocr.script' => base_path('tools/ocr/paddle_document_capture.py'),
        ]);

        $attachment = new Attachment([
            'path' => 'attachments/large.png',
            'mime_type' => 'image/png',
            'size' => 4096,
        ]);

        $this->assertSame([], (new PaddleOcrDocumentAnalyzer())->candidates($attachment));
    }

    public function test_it_maps_successful_process_output_to_a_text_candidate(): void
    {
        Storage::fake('local');
        Storage::put('attachments/sample.png', 'image');

        $script = storage_path('app/testing/paddle-success.php');
        if (! is_dir(dirname($script))) {
            mkdir(dirname($script), 0775, true);
        }

        file_put_contents($script, <<<'PHP'
<?php
echo json_encode([
    'pipeline' => 'fixture',
    'text' => "Supplier: Alpha Engineering Sdn Bhd\nQuotation No: AQ-1044\nTotal: RM 2,750.00",
]);
PHP);

        config([
            'ocr.paddleocr.enabled' => true,
            'ocr.paddleocr.python' => PHP_BINARY,
            'ocr.paddleocr.script' => $script,
            'ocr.paddleocr.cache_dir' => storage_path('app/testing/paddle-cache'),
            'ocr.paddleocr.timeout' => 10,
        ]);

        $attachment = new Attachment([
            'path' => 'attachments/sample.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ]);

        $candidates = (new PaddleOcrDocumentAnalyzer())->candidates($attachment);

        $this->assertCount(1, $candidates);
        $this->assertSame('paddleocr-3.5.0-fixture', $candidates[0]['engine']);
        $this->assertStringContainsString('Alpha Engineering Sdn Bhd', $candidates[0]['text']);
    }
}
