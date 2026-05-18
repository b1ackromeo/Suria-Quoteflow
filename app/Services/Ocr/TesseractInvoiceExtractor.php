<?php

namespace App\Services\Ocr;

use App\Models\Attachment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Local text-reading implementation used by QuoteFlow's embedded
 * BusinessDocumentCaptureService. Controllers should depend on the business
 * service, not this low-level OCR reader.
 */
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

        $attachment->loadMissing('document');
        $documentType = $attachment->document?->type;
        $candidates = $this->initialTextCandidates($attachment, $documentType);
        $extractionErrors = [];
        $temporaryImages = [];

        try {
            if ($attachment->isPdf()) {
                $candidates = array_merge($candidates, $this->pdfTextCandidates($attachment, $documentType));

                $bestPdfCandidate = $this->bestCandidate($candidates);
                if ($bestPdfCandidate && $bestPdfCandidate['score'] >= $this->highConfidenceScore()) {
                    return $this->resultFromCandidate($bestPdfCandidate);
                }
            }

            try {
                foreach ($this->imagePathsFor($attachment, $temporaryImages) as $imagePath) {
                    foreach ($this->tesseractPageSegmentationModes() as $psm) {
                        try {
                            $candidate = $this->candidateFromText(
                                'tesseract-psm-'.$psm,
                                $this->runTesseract($imagePath, $psm),
                                $documentType
                            );

                            if ($candidate) {
                                $candidates[] = $candidate;
                            }
                        } catch (\Throwable $exception) {
                            $extractionErrors[] = $exception->getMessage();
                        }
                    }
                }
            } catch (\Throwable $exception) {
                $extractionErrors[] = $exception->getMessage();
            }
        } finally {
            foreach ($temporaryImages as $temporaryImage) {
                if (is_file($temporaryImage)) {
                    @unlink($temporaryImage);
                }
            }
        }

        $bestCandidate = $this->bestCandidate($candidates);

        if (! $bestCandidate) {
            $error = collect($extractionErrors)->filter()->first();

            throw new RuntimeException($error ?: 'No readable text was returned for this file.');
        }

        return $this->resultFromCandidate($bestCandidate);
    }

    protected function initialTextCandidates(Attachment $attachment, ?string $documentType): array
    {
        return [];
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

    private function runTesseract(string $imagePath, int $psm): string
    {
        $process = new Process([
            $this->tesseractBinary(),
            $imagePath,
            'stdout',
            '-l',
            (string) config('ocr.language', 'eng'),
            '--psm',
            (string) $psm,
        ]);

        $process->setTimeout(max(5, (int) config('ocr.timeout', 30)));
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException($message !== '' ? $message : 'Tesseract OCR failed.');
        }

        return $process->getOutput();
    }

    private function pdfTextCandidates(Attachment $attachment, ?string $documentType): array
    {
        if (! (bool) config('ocr.use_pdf_text_layer', true)) {
            return [];
        }

        $path = Storage::path($attachment->path);

        if (! is_file($path)) {
            throw new RuntimeException('Attachment file cannot be found in local storage.');
        }

        $candidates = [];

        foreach (['layout' => ['-layout'], 'text' => []] as $mode => $options) {
            $text = $this->runPdfToText($path, $options);
            $candidate = $this->candidateFromText('pdftotext-'.$mode, $text, $documentType);

            if ($candidate) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    private function runPdfToText(string $path, array $options): string
    {
        $binary = $this->configuredBinary('pdftotext_path', 'pdftotext');

        if ($binary === '') {
            return '';
        }

        $command = array_merge(
            [$binary, '-f', '1', '-l', (string) max(1, (int) config('ocr.pdf_max_pages', 2))],
            $options,
            [$path, '-']
        );

        try {
            $process = new Process($command);
            $process->setTimeout(max(5, (int) config('ocr.timeout', 30)));
            $process->run();

            if (! $process->isSuccessful()) {
                return '';
            }

            return $process->getOutput();
        } catch (\Throwable) {
            return '';
        }
    }

    private function tesseractPageSegmentationModes(): array
    {
        $configured = config('ocr.tesseract_psm_modes', '6,4,11');
        $values = is_array($configured)
            ? $configured
            : preg_split('/[\s,]+/', (string) $configured, -1, PREG_SPLIT_NO_EMPTY);

        $modes = collect($values)
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value >= 3 && $value <= 13)
            ->unique()
            ->take(3)
            ->values()
            ->all();

        return $modes ?: [6];
    }

    private function highConfidenceScore(): int
    {
        return max(1, min(100, (int) config('ocr.high_confidence_score', 85)));
    }

    protected function candidateFromText(string $engine, string $rawText, ?string $documentType): ?array
    {
        $rawText = $this->normalizeRawText($rawText);

        if ($rawText === '') {
            return null;
        }

        $fields = $this->parseFields($rawText, $documentType);

        return [
            'engine' => $engine,
            'raw_text' => $rawText,
            'fields' => $fields,
            'score' => $this->scoreExtractedFields($fields, $documentType),
        ];
    }

    private function bestCandidate(array $candidates): ?array
    {
        $candidates = array_values(array_filter($candidates, fn ($candidate) => filled($candidate['raw_text'] ?? null)));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $left, array $right) {
            $scoreComparison = ($right['score'] ?? 0) <=> ($left['score'] ?? 0);

            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return strlen((string) ($right['raw_text'] ?? '')) <=> strlen((string) ($left['raw_text'] ?? ''));
        });

        return $candidates[0];
    }

    private function resultFromCandidate(array $candidate): array
    {
        return [
            'raw_text' => $candidate['raw_text'],
            'extracted_fields' => $candidate['fields'],
            'engine' => $candidate['engine'],
            'language' => (string) config('ocr.language', 'eng'),
        ];
    }

    private function normalizeRawText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", (string) $text);

        return trim((string) $text);
    }

    private function scoreExtractedFields(array $fields, ?string $documentType): int
    {
        $isQuotation = $documentType === 'supplier_quotation';
        $score = 0;

        foreach ([
            'supplier_name' => 18,
            $isQuotation ? 'quote_number' : 'invoice_number' => 18,
            $isQuotation ? 'quote_date' : 'invoice_date' => 14,
            'total' => 18,
            'payment_terms' => 5,
            'subtotal' => 4,
            'tax_total' => 4,
            $isQuotation ? 'valid_until' : 'po_number' => 6,
        ] as $field => $weight) {
            if (filled($fields[$field] ?? null)) {
                $score += $weight;
            }
        }

        if ($isQuotation && filled($fields['commercial_terms'] ?? null)) {
            $score += 4;
        }

        $items = array_values(array_filter($fields['items'] ?? [], 'is_array'));
        $score += min(count($items) * 5, 25);

        $validLineMath = 0;
        $lineTotalSum = 0.0;

        foreach ($items as $item) {
            $quantity = $this->numberFromText($item['quantity'] ?? null);
            $unitPrice = $this->numberFromText($item['unit_price'] ?? null);
            $lineTotal = $this->numberFromText($item['line_total'] ?? null);

            if ($lineTotal !== null) {
                $lineTotalSum += $lineTotal;
            }

            if ($quantity !== null && $unitPrice !== null && $lineTotal !== null && abs(($quantity * $unitPrice) - $lineTotal) <= 0.05) {
                $validLineMath++;
            }
        }

        $score += min($validLineMath * 2, 10);

        $documentTotal = $this->numberFromText($fields['total'] ?? null);
        if ($documentTotal !== null && $lineTotalSum > 0 && abs($documentTotal - $lineTotalSum) <= 0.05) {
            $score += 15;
        }

        return min($score, 100);
    }

    private function tesseractBinary(): string
    {
        return $this->configuredBinary('tesseract_path', 'tesseract');
    }

    private function configuredBinary(string $key, string $default): string
    {
        return trim((string) config('ocr.'.$key, $default));
    }

    private function parseFields(string $text, ?string $documentType = 'supplier_invoice'): array
    {
        return match ($documentType) {
            'supplier_quotation' => $this->parseSupplierQuotationFields($text),
            default => $this->parseSupplierInvoiceFields($text),
        };
    }

    private function parseSupplierInvoiceFields(string $text): array
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

    private function parseSupplierQuotationFields(string $text): array
    {
        $quoteDate = $this->findDocumentDate($text, [
            'quotation\s*date',
            'quote\s*date',
            'date',
        ]);

        $fields = [
            'supplier_name' => $this->guessSupplierName($text),
            'quote_number' => $this->normalizeReference($this->findValue($text, [
                'quotation\s*(?:no\.?|number|#)',
                'quote\s*(?:no\.?|number|#)',
                'supplier\s*quote\s*(?:no\.?|number|#)',
                'ref(?:erence)?\s*(?:no\.?|number|#)?',
                'our\s*ref',
            ])),
            'quote_date' => $quoteDate,
            'valid_until' => $this->findDocumentDate($text, [
                'valid\s*until',
                'expiry\s*date',
                'expires',
            ]) ?? $this->findValidityDate($text, $quoteDate),
            'subtotal' => $this->findAmount($text, ['subtotal', 'sub\s*total']),
            'tax_total' => $this->findAmount($text, ['sst', 'service\s*tax', 'tax']),
            'total' => $this->findAmount($text, ['grand\s*total', 'quote\s*total', 'quotation\s*total', 'total\s*amount', 'amount\s*due', '\btotal\b']),
            'payment_terms' => $this->findPaymentTerms($text),
            'commercial_terms' => $this->findCommercialTerms($text),
        ];

        $items = $this->findLineItems($text);

        if ($items !== []) {
            $fields['items'] = $items;
        }

        return array_filter($fields, fn ($value) => filled($value));
    }

    private function findValue(string $text, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (preg_match('/(?:'.$label.')\s*[:#=\-]?\s*([^\r\n]+)/i', $text, $match)) {
                $value = trim((string) preg_replace('/\s+/', ' ', $match[1]));
                $value = preg_replace('/\s+(?:date|invoice\s+date|quote\s+date)\s*[:#\-]?.*$/i', '', $value);

                return trim((string) $value);
            }
        }

        $lines = collect(preg_split('/\R+/', $text))
            ->map(fn ($line) => trim((string) preg_replace('/\s+/', ' ', $line)))
            ->filter()
            ->values();

        foreach ($labels as $label) {
            foreach ($lines as $index => $line) {
                if (! preg_match('/^'.$label.'\s*[:#=\-]?\s*(.*)$/i', $line, $match)) {
                    continue;
                }

                $value = trim((string) ($match[1] ?? ''));
                if ($value !== '') {
                    return $value;
                }

                $next = $lines[$index + 1] ?? null;
                if ($next && ! preg_match('/date|total|amount|terms?|description|qty|quantity/i', $next)) {
                    return $next;
                }
            }
        }

        return null;
    }

    private function findDate(string $text): ?string
    {
        return $this->findDocumentDate($text, ['invoice\s*date', 'date']);
    }

    private function findDocumentDate(string $text, array $labels): ?string
    {
        $datePatterns = [
            '([0-9]{1,2}[\/\-. ][0-9]{1,2}[\/\-. ][0-9]{2,4})',
            '([0-9]{1,2}\s+[A-Za-z]{3,9}\s+[0-9]{2,4})',
            '([0-9]{4}[\/\-.][0-9]{1,2}[\/\-.][0-9]{1,2})',
        ];

        foreach ($labels as $label) {
            foreach ($datePatterns as $datePattern) {
                if (preg_match('/(?:'.$label.')\s*[:#\-]?\s*'.$datePattern.'/i', $text, $match)) {
                    try {
                        return $this->dateFromText($match[1]);
                    } catch (\Throwable) {
                        return trim($match[1]);
                    }
                }
            }
        }

        $lines = collect(preg_split('/\R+/', $text))
            ->map(fn ($line) => trim((string) preg_replace('/\s+/', ' ', $line)))
            ->filter()
            ->values();

        foreach ($labels as $label) {
            foreach ($lines as $index => $line) {
                if (! preg_match('/^'.$label.'\s*[:#=\-]?\s*(.*)$/i', $line, $labelMatch)) {
                    continue;
                }

                $valueLine = trim((string) ($labelMatch[1] ?? '')) ?: ($lines[$index + 1] ?? '');

                foreach ($datePatterns as $datePattern) {
                    if (preg_match('/'.$datePattern.'/i', $valueLine, $match)) {
                        try {
                            return $this->dateFromText($match[1]);
                        } catch (\Throwable) {
                            return trim($match[1]);
                        }
                    }
                }
            }
        }

        return null;
    }

    private function findValidityDate(string $text, ?string $quoteDate): ?string
    {
        if (! $quoteDate || ! preg_match('/validity\s*[:#\-]?\s*([0-9]+)\s*days?/i', $text, $match)) {
            return null;
        }

        try {
            return Carbon::parse($quoteDate)->addDays((int) $match[1])->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function findAmount(string $text, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (preg_match('/(?:'.$label.')\s*[:#=\-]?\s*(?:MYR|RM)?\s*([0-9][0-9,]*(?:\.[0-9]{2})?)(?:\s*(?:MYR|RM))?/i', $text, $match)) {
                return number_format((float) str_replace(',', '', $match[1]), 2, '.', '');
            }
        }

        $lines = collect(preg_split('/\R+/', $text))
            ->map(fn ($line) => trim((string) preg_replace('/\s+/', ' ', $line)))
            ->filter()
            ->values();

        foreach ($labels as $label) {
            foreach ($lines as $index => $line) {
                if (! preg_match('/^'.$label.'\s*[:#=\-]?\s*(.*)$/i', $line, $labelMatch)) {
                    continue;
                }

                $valueLine = trim((string) ($labelMatch[1] ?? '')) ?: ($lines[$index + 1] ?? '');

                if (preg_match('/(?:MYR|RM)?\s*([0-9][0-9,]*(?:\.[0-9]{2})?)(?:\s*(?:MYR|RM))?/i', $valueLine, $amountMatch)) {
                    return number_format((float) str_replace(',', '', $amountMatch[1]), 2, '.', '');
                }
            }
        }

        return null;
    }

    private function findPaymentTerms(string $text): ?string
    {
        foreach (preg_split('/\R+/', $text) as $line) {
            if (! preg_match('/^\s*(?:payment\s*)?terms\s*[:=\-]?\s*([^\r\n]+)/i', $line, $match)) {
                continue;
            }

            $value = trim((string) preg_replace('/\s+/', ' ', $match[1]));

            if ($value !== '' && ! str_starts_with($value, '&')) {
                return $value;
            }
        }

        return $this->findValue($text, ['payment\s*terms?']);
    }

    private function findCommercialTerms(string $text): ?string
    {
        $lines = collect(preg_split('/\R+/', $text))
            ->map(fn ($line) => trim((string) preg_replace('/\s+/', ' ', $line)))
            ->values();

        $capture = false;
        $terms = [];

        foreach ($lines as $line) {
            if ($line === '') {
                if ($capture && $terms !== []) {
                    break;
                }

                continue;
            }

            if (! $capture && preg_match('/^(terms?\s*(?:&|and)?\s*conditions?|commercial\s*terms?|terms|delivery|warranty|validity|remarks?|notes?|exclusions?)\b/i', $line)) {
                $capture = true;
            }

            if (! $capture) {
                continue;
            }

            if (preg_match('/^(?:prepared\s+by|authorized|signature|bank\s+details|subtotal|tax|grand\s+total|total\s+amount|we\s+trust|we\s+hope|thank\s+you|yours\s+faithfully|yours\s+sincerely)\b/i', $line)) {
                break;
            }

            if (preg_match('/^(?:[0-9]+\.?\s+)?[A-Za-z"].*\s+[0-9]+(?:\.[0-9]{1,3})?\s+[A-Za-z][A-Za-z0-9\/-]{0,19}\s+(?:MYR|RM)?\s*[0-9][0-9,]*(?:\.[0-9]{2})/i', $line)) {
                continue;
            }

            $line = preg_replace('/^\s*[-*•]\s*/u', '', $line);
            $line = preg_replace('/^(terms?\s*(?:&|and)?\s*conditions?|commercial\s*terms?)\s*[:=\-]*\s*/i', '', (string) $line);

            if (trim((string) $line) !== '') {
                $terms[] = trim((string) $line);
            }

            if (strlen(implode("\n", $terms)) >= 1200) {
                break;
            }
        }

        if ($terms === []) {
            return null;
        }

        return Str::limit(implode("\n", array_unique($terms)), 1200, '');
    }

    private function findLineItems(string $text): array
    {
        $items = [];

        foreach (preg_split('/\R+/', $text) as $line) {
            $line = trim((string) preg_replace('/\s+/', ' ', $line));

            if ($line === '' || preg_match('/invoice|quotation|quote\s*(?:no|date)|subtotal|tax|total|payment|valid|supplier|customer|bill\s+to|description\s+qty/i', $line)) {
                continue;
            }

            $matched = preg_match('/^(?:[0-9]+\.?\s+)?(?<description>.+?)\s+(?<quantity>[0-9]+(?:\.[0-9]{1,3})?)\s+(?<unit>[A-Za-z][A-Za-z0-9\/-]{0,19})\s+(?:[^\d\r\n]+\s+)?(?:MYR|RM)?\s*(?<unit_price>[0-9][0-9,]*(?:\.[0-9]{2}))\s+(?:MYR|RM)?\s*(?<line_total>[0-9][0-9,]*(?:\.[0-9]{2}))(?:\s*(?:MYR|RM))?$/iu', $line, $match);

            if (! $matched) {
                $matched = preg_match('/^(?:[0-9]+\.?\s+)?(?<description>.+?)\s+(?<quantity>[0-9]+(?:\.[0-9]{1,3})?)\s+(?<unit>[A-Za-z][A-Za-z0-9\/-]{0,19})\s+(?:MYR|RM)?\s*(?<line_total>[0-9][0-9,]*(?:\.[0-9]{2}))(?:\s*(?:MYR|RM))?$/i', $line, $match);
            }

            if (! $matched) {
                continue;
            }

            $description = trim((string) preg_replace('/^[0-9]+\.?\s+/', '', $match['description']));

            if ($description === '' || strlen($description) < 3) {
                continue;
            }

            $quantity = (float) $match['quantity'];
            $lineTotal = (float) str_replace(',', '', $match['line_total']);
            $unitPrice = isset($match['unit_price']) && $match['unit_price'] !== ''
                ? (float) str_replace(',', '', $match['unit_price'])
                : ($quantity > 0 ? round($lineTotal / $quantity, 2) : $lineTotal);

            $items[] = [
                'description' => $description,
                'quantity' => number_format($quantity, 3, '.', ''),
                'unit' => trim($match['unit'] ?? '') ?: 'unit',
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
            ];

            if (count($items) >= 20) {
                break;
            }
        }

        return $items;
    }

    private function guessSupplierName(string $text): ?string
    {
        $lines = collect(preg_split('/\R+/', $text))
            ->map(fn ($line) => trim((string) preg_replace('/\s+/', ' ', $line)))
            ->filter()
            ->values();

        foreach ($lines as $line) {
            if (preg_match('/^(?:supplier|vendor|from)\s*[:=\-]\s*(.+)$/i', $line, $match)) {
                $candidate = trim($match[1], " .\t\n\r\0\x0B");

                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        $companyLine = $lines->first(function (string $line) {
            return ! preg_match('/^(to|attn?|re|terms|price|delivery|validity)\b/i', $line)
                && preg_match('/\b(sdn\s*bhd|berhad|ltd|limited|pte|enterprise|trading|resources)\b/i', $line);
        });

        if ($companyLine) {
            return trim($companyLine, " .\t\n\r\0\x0B");
        }

        foreach ($lines as $line) {
            if (preg_match('/payment\s*terms?|terms|valid\s*until|validity/i', $line)) {
                continue;
            }

            if (preg_match('/^(.+?)\s+(?:tax\s+)?(?:invoice|quotation|quote)\b/i', $line, $match)) {
                $candidate = trim($match[1]);

                if ($candidate !== '' && ! preg_match('/^re:?$/i', $candidate)) {
                    return $candidate;
                }
            }
        }

        return $lines->first(function (string $line) {
            return strlen($line) > 2 && ! preg_match('/invoice|quotation|quote|receipt|date|total|amount|payment|terms|valid|page/i', $line);
        });
    }

    private function numberFromText(mixed $value): ?float
    {
        if (! filled($value)) {
            return null;
        }

        $number = preg_replace('/[^\d.\-]/', '', (string) $value);

        if ($number === '' || ! is_numeric($number)) {
            return null;
        }

        return (float) $number;
    }

    private function dateFromText(string $value): string
    {
        $value = trim($value);

        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'j.n.Y', 'Y-m-d', 'j F Y', 'd F Y', 'j M Y', 'd M Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return Carbon::parse($value)->toDateString();
    }

    private function normalizeReference(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $value = trim((string) $value);

        if (str_contains($value, '/')) {
            $value = preg_replace('/\s+/', '', $value);
        }

        return $value;
    }
}
