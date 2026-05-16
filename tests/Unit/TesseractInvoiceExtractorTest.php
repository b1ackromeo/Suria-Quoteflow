<?php

namespace Tests\Unit;

use App\Services\Ocr\TesseractInvoiceExtractor;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TesseractInvoiceExtractorTest extends TestCase
{
    public function test_it_parses_supplier_name_from_two_column_invoice_header(): void
    {
        $extractor = new TesseractInvoiceExtractor();
        $parseFields = new ReflectionMethod($extractor, 'parseFields');
        $parseFields->setAccessible(true);

        $fields = $parseFields->invoke($extractor, <<<TEXT
N2N System        INVOICE
Invoice No: OCR-E2E-7715
Invoice Date: 15 May 2026
PO Number: SPO-2026-00002
Subtotal: MYR 2,250.00
Tax: MYR 180.00
Total: MYR 2,430.00
Payment Terms: 14 days from invoice date
TEXT);

        $this->assertSame('N2N System', $fields['supplier_name']);
        $this->assertSame('OCR-E2E-7715', $fields['invoice_number']);
        $this->assertSame('SPO-2026-00002', $fields['po_number']);
        $this->assertSame('2430.00', $fields['total']);
    }
}
