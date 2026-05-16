<?php

namespace App\Services\Ocr;

use App\Models\Attachment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class TesseractInvoiceExtractor
{
    public function canExtract(Attachment $attachment): bool
    {
        return $attachment->isPreviewable();
    }

    public function extract(Attachment $attachment): array
    {
        if (! $this->canExtract($attachment)) {
            throw new RuntimeException('Only PDF and image files can be extracted.');
        }

        $temporaryImages = [];
        $rawText = '';

        try {
            foreach ($this->imagePathsFor($attachment, $temporaryImages) as $imagePath) {
                $rawText .= trim($this->runTesseract($imagePath)).PHP_EOL.PHP_EOL;
            }
        } finally {
            foreach ($temporaryImages as $temporaryImage) {
                if (is_file($temporaryImage)) {
                    @unlink($temporaryImage);
                }
            }
        }

        $rawText = trim($rawText);

        if ($rawText === '') {
            throw new RuntimeException('Tesseract did not return readable text for this file.');
        }

        return [
            'raw_text' => $rawText,
            'extracted_fields' => $this->parseFields($rawText),
            'engine' => 'tesseract',
            'language' => (string) config('ocr.language', 'eng'),
        ];
    }

    private function imagePathsFor(Attachment $attachment, array &$temporaryImages): array
    {
        $path = Storage::path($attachment->path);

        if (! is_file($path)) {
            throw new RuntimeException('Attachment file cannot be found in local storage.');
        }

        if ($attachment->isImage()) {
            return [$path];
        }

        if (! class_exists(\Imagick::class)) {
            throw new RuntimeException('PHP Imagick is required to extract text from PDF files.');
        }

        $maxPages = max(1, (int) config('ocr.pdf_max_pages', 2));
        $tmpDir = storage_path('app/ocr/tmp');

        if (! is_dir($tmpDir) && ! mkdir($tmpDir, 0775, true) && ! is_dir($tmpDir)) {
            throw new RuntimeException('Cannot create OCR temporary directory.');
        }

        $imagePaths = [];

        for ($page = 0; $page < $maxPages; $page++) {
            $image = new \Imagick();
            $image->setResolution(200, 200);

            try {
                $image->readImage($path.'['.$page.']');
            } catch (\Throwable $exception) {
                $image->clear();
                $image->destroy();

                if ($page === 0) {
                    throw new RuntimeException('Cannot convert the PDF for OCR. Check Imagick and Ghostscript setup.');
                }

                break;
            }

            $image->setImageBackgroundColor('white');
            $image->setImageFormat('png');

            if (defined(\Imagick::class.'::ALPHACHANNEL_REMOVE')) {
                $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            }

            $imagePath = $tmpDir.DIRECTORY_SEPARATOR.Str::uuid().'-page-'.$page.'.png';
            $image->writeImage($imagePath);
            $image->clear();
            $image->destroy();

            $temporaryImages[] = $imagePath;
            $imagePaths[] = $imagePath;
        }

        if ($imagePaths === []) {
            throw new RuntimeException('The PDF did not produce any pages for OCR.');
        }

        return $imagePaths;
    }

    private function runTesseract(string $imagePath): string
    {
        $process = new Process([
            $this->tesseractBinary(),
            $imagePath,
            'stdout',
            '-l',
            (string) config('ocr.language', 'eng'),
            '--psm',
            '6',
        ]);

        $process->setTimeout(max(5, (int) config('ocr.timeout', 30)));
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException($message !== '' ? $message : 'Tesseract OCR failed.');
        }

        return $process->getOutput();
    }

    private function tesseractBinary(): string
    {
        $configured = (string) config('ocr.tesseract_path', 'tesseract');

        if ($configured !== 'tesseract' || PHP_OS_FAMILY !== 'Windows') {
            return $configured;
        }

        foreach ([
            'C:\Program Files\Tesseract-OCR\tesseract.exe',
            'C:\Program Files (x86)\Tesseract-OCR\tesseract.exe',
            'D:\laragon\bin\tesseract\tesseract.exe',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return 'tesseract';
    }

    private function parseFields(string $text): array
    {
        $fields = [
            'supplier_name' => $this->guessSupplierName($text),
            'invoice_number' => $this->findValue($text, [
                'invoice\s*(?:no\.?|number|#)',
                'inv\s*(?:no\.?|#)',
                'tax\s*invoice\s*(?:no\.?|number|#)',
            ]),
            'invoice_date' => $this->findDate($text),
            'po_number' => $this->findValue($text, [
                'po\s*(?:no\.?|number|#)',
                'purchase\s*order\s*(?:no\.?|number|#)',
            ]),
            'subtotal' => $this->findAmount($text, ['subtotal', 'sub\s*total']),
            'tax_total' => $this->findAmount($text, ['sst', 'service\s*tax', 'tax']),
            'total' => $this->findAmount($text, ['grand\s*total', 'invoice\s*total', 'amount\s*due', 'balance\s*due', '\btotal\b']),
            'payment_terms' => $this->findValue($text, ['payment\s*terms?', 'terms']),
        ];

        return array_filter($fields, fn ($value) => filled($value));
    }

    private function findValue(string $text, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (preg_match('/(?:'.$label.')\s*[:#\-]?\s*([^\r\n]+)/i', $text, $match)) {
                return trim((string) preg_replace('/\s+/', ' ', $match[1]));
            }
        }

        return null;
    }

    private function findDate(string $text): ?string
    {
        foreach ([
            '/invoice\s*date\s*[:#\-]?\s*([0-9]{1,2}[\/\-. ][0-9]{1,2}[\/\-. ][0-9]{2,4})/i',
            '/date\s*[:#\-]?\s*([0-9]{1,2}[\/\-. ][0-9]{1,2}[\/\-. ][0-9]{2,4})/i',
            '/invoice\s*date\s*[:#\-]?\s*([0-9]{1,2}\s+[A-Za-z]{3,9}\s+[0-9]{2,4})/i',
        ] as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                try {
                    return Carbon::parse($match[1])->toDateString();
                } catch (\Throwable) {
                    return trim($match[1]);
                }
            }
        }

        return null;
    }

    private function findAmount(string $text, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (preg_match('/(?:'.$label.')\s*[:#\-]?\s*(?:MYR|RM)?\s*([0-9][0-9,]*(?:\.[0-9]{2})?)/i', $text, $match)) {
                return number_format((float) str_replace(',', '', $match[1]), 2, '.', '');
            }
        }

        return null;
    }

    private function guessSupplierName(string $text): ?string
    {
        $lines = collect(preg_split('/\R+/', $text))
            ->map(fn ($line) => trim((string) preg_replace('/\s+/', ' ', $line)))
            ->filter()
            ->values();

        foreach ($lines as $line) {
            if (preg_match('/^(.+?)\s+(?:tax\s+)?invoice\b/i', $line, $match)) {
                $candidate = trim($match[1]);

                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return $lines->first(function (string $line) {
            return strlen($line) > 2 && ! preg_match('/invoice|receipt|date|total|amount|page/i', $line);
        });
    }
}
