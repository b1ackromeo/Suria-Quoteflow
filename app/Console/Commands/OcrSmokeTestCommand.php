<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\Document;
use App\Services\Documents\BusinessDocumentCaptureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class OcrSmokeTestCommand extends Command
{
    protected $signature = 'quoteflow:ocr-smoke-test {--keep-file : Keep the generated smoke-test image in local storage}';

    protected $description = 'Run a bounded OCR smoke test through QuoteFlow document capture.';

    public function handle(BusinessDocumentCaptureService $captureService): int
    {
        if (! (bool) config('ocr.enabled')) {
            $this->error('OCR is disabled. Enable OCR_ENABLED before running the smoke test.');

            return self::FAILURE;
        }

        $relativePath = null;

        try {
            $relativePath = $this->createSmokeImage();

            $attachment = new Attachment([
                'category' => 'invoice_copy',
                'original_name' => 'quoteflow-ocr-smoke-test.png',
                'path' => $relativePath,
                'mime_type' => 'image/png',
                'size' => Storage::size($relativePath),
            ]);
            $attachment->setRelation('document', new Document([
                'type' => 'supplier_invoice',
            ]));

            $result = $captureService->extract($attachment);
            $fields = $result['extracted_fields'] ?? [];
            $missing = $this->missingRequiredFields($fields);

            if ($missing !== []) {
                $this->error('OCR smoke test failed. Missing extracted field(s): '.implode(', ', $missing));
                $this->line('Engine: '.($result['engine'] ?? 'unknown'));

                return self::FAILURE;
            }

            $this->info('OCR smoke test passed.');
            $this->line('Engine: '.($result['engine'] ?? 'unknown'));
            $this->line('Invoice no.: '.($fields['invoice_number'] ?? '-'));
            $this->line('Total: '.($fields['total'] ?? '-'));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('OCR smoke test failed. '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($relativePath && ! $this->option('keep-file')) {
                Storage::delete($relativePath);
            } elseif ($relativePath) {
                $this->line('Smoke-test image kept at: '.Storage::path($relativePath));
            }
        }
    }

    private function createSmokeImage(): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('PHP GD is required to generate the OCR smoke-test image.');
        }

        $directory = 'ocr/smoke-tests';
        $relativePath = $directory.'/'.Str::uuid().'.png';
        $absolutePath = Storage::path($relativePath);

        if (! is_dir(dirname($absolutePath)) && ! mkdir(dirname($absolutePath), 0775, true) && ! is_dir(dirname($absolutePath))) {
            throw new RuntimeException('Cannot create OCR smoke-test directory.');
        }

        $image = imagecreatetruecolor(1600, 900);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 20, 20, 20);
        imagefilledrectangle($image, 0, 0, 1600, 900, $white);

        $lines = [
            'Best Supplies Sdn Bhd',
            'Tax Invoice',
            'Invoice No: OCR-2026-0001',
            'Invoice Date: 2026-05-24',
            'PO Number: SPO-2026-0001',
            'Subtotal: RM 100.00',
            'Tax: RM 8.00',
            'Grand Total: RM 108.00',
            'Payment Terms: 30 days from invoice date',
        ];

        $font = $this->fontPath();

        foreach ($lines as $index => $line) {
            $y = 90 + ($index * 75);

            if ($font && function_exists('imagettftext')) {
                imagettftext($image, 30, 0, 80, $y, $black, $font, $line);
            } else {
                imagestring($image, 5, 80, $y - 25, $line, $black);
            }
        }

        imagepng($image, $absolutePath);
        imagedestroy($image);

        return $relativePath;
    }

    private function fontPath(): ?string
    {
        foreach ([
            'C:\Windows\Fonts\arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function missingRequiredFields(array $fields): array
    {
        $required = [
            'supplier_name',
            'invoice_number',
            'invoice_date',
            'total',
        ];

        return collect($required)
            ->filter(fn (string $field) => blank($fields[$field] ?? null))
            ->values()
            ->all();
    }
}
