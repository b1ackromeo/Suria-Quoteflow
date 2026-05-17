<?php

namespace Tests\Unit;

use App\Services\Documents\BusinessDocumentCaptureService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TesseractInvoiceExtractorTest extends TestCase
{
    public function test_it_parses_supplier_name_from_two_column_invoice_header(): void
    {
        $extractor = new BusinessDocumentCaptureService();
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

    public function test_it_parses_supplier_quotation_fields_and_line_items(): void
    {
        $extractor = new BusinessDocumentCaptureService();
        $parseFields = new ReflectionMethod($extractor, 'parseFields');
        $parseFields->setAccessible(true);

        $fields = $parseFields->invoke($extractor, <<<TEXT
N2N System        QUOTATION
Quotation No: SQ-BEST-009
Quote Date: 14 May 2026
Valid Until: 13 Jun 2026
Description Qty Unit Amount
Operations hardware kit 5 set MYR 2,250.00
Service coordination 1 job MYR 180.00
Total: MYR 2,430.00
Payment Terms: 30 days from invoice date
TEXT, 'supplier_quotation');

        $this->assertSame('N2N System', $fields['supplier_name']);
        $this->assertSame('SQ-BEST-009', $fields['quote_number']);
        $this->assertSame('2026-05-14', $fields['quote_date']);
        $this->assertSame('2026-06-13', $fields['valid_until']);
        $this->assertSame('2430.00', $fields['total']);
        $this->assertCount(2, $fields['items']);
        $this->assertSame('Operations hardware kit', $fields['items'][0]['description']);
        $this->assertSame('450.00', $fields['items'][0]['unit_price']);
    }

    public function test_it_parses_real_supplier_quotation_ocr_shape(): void
    {
        $extractor = new BusinessDocumentCaptureService();
        $parseFields = new ReflectionMethod($extractor, 'parseFields');
        $parseFields->setAccessible(true);

        $fields = $parseFields->invoke($extractor, <<<TEXT
GREAT LITE ELECTRIC (M) SDN BHD.....
Our Ref: GL-Q08 1/04/26/LKW/LCH Date: 17/4/2026
To: (1OT RESOURCES SDN BHD)
RE: QUOTATION
1 "FAJAR" 16MM CU. PVC FLEXIBLE CABLE (R,Y,B X1 EACH) 3 COILS - 1,380.00 4,140.00
2 "DNF" 6MM PVC CABLE (R,BLK,G X2 EACH) 6 cos 369.00 2,214.00
3. "HIMEL" 1 ROW 10WAY METAL/CLAD DB BOX 1 BAG 76.00 76.00
TOTAL AMOUNT: 6,833.50
Terms & Condition:-
Price NETT
Delivery: TBA.
Terms CASH
Validity 1 Days.
TEXT, 'supplier_quotation');

        $this->assertSame('GREAT LITE ELECTRIC (M) SDN BHD', $fields['supplier_name']);
        $this->assertSame('GL-Q081/04/26/LKW/LCH', $fields['quote_number']);
        $this->assertSame('2026-04-17', $fields['quote_date']);
        $this->assertSame('2026-04-18', $fields['valid_until']);
        $this->assertSame('6833.50', $fields['total']);
        $this->assertSame('CASH', $fields['payment_terms']);
        $this->assertCount(3, $fields['items']);
        $this->assertSame('3.000', $fields['items'][0]['quantity']);
        $this->assertSame('1380.00', $fields['items'][0]['unit_price']);
        $this->assertSame('4140.00', $fields['items'][0]['line_total']);
    }

    public function test_it_parses_stacked_labels_and_amount_suffixes_from_another_quote_layout(): void
    {
        $extractor = new BusinessDocumentCaptureService();
        $parseFields = new ReflectionMethod($extractor, 'parseFields');
        $parseFields->setAccessible(true);

        $fields = $parseFields->invoke($extractor, <<<TEXT
Supplier: Alpha Engineering Sdn Bhd
Quotation No.
AQ-1044
Date
2026-05-10
Valid Until
2026-06-09
Description Quantity UOM Unit Price Amount
Cable tray system 2 lot RM 1,200.00 2,400.00 MYR
Installation labour 1 job RM 350.00 350.00 MYR
Grand Total =
RM 2,750.00
Payment Terms: 30 days
TEXT, 'supplier_quotation');

        $this->assertSame('Alpha Engineering Sdn Bhd', $fields['supplier_name']);
        $this->assertSame('AQ-1044', $fields['quote_number']);
        $this->assertSame('2026-05-10', $fields['quote_date']);
        $this->assertSame('2026-06-09', $fields['valid_until']);
        $this->assertSame('2750.00', $fields['total']);
        $this->assertCount(2, $fields['items']);
        $this->assertSame('Cable tray system', $fields['items'][0]['description']);
        $this->assertSame('1200.00', $fields['items'][0]['unit_price']);
    }

    public function test_it_scores_and_selects_the_best_extraction_candidate(): void
    {
        $extractor = new BusinessDocumentCaptureService();
        $candidateFromText = new ReflectionMethod($extractor, 'candidateFromText');
        $candidateFromText->setAccessible(true);
        $bestCandidate = new ReflectionMethod($extractor, 'bestCandidate');
        $bestCandidate->setAccessible(true);

        $weakOcrCandidate = $candidateFromText->invoke($extractor, 'tesseract-psm-6', <<<TEXT
QUOTATION
Total RM 2,750.00
TEXT, 'supplier_quotation');

        $textLayerCandidate = $candidateFromText->invoke($extractor, 'pdftotext-layout', <<<TEXT
Supplier: Alpha Engineering Sdn Bhd
Quotation No: AQ-1044
Quote Date: 10 May 2026
Description Qty Unit Unit Price Amount
Cable tray system 2 lot RM 1,200.00 RM 2,400.00
Installation labour 1 job RM 350.00 RM 350.00
Grand Total: RM 2,750.00
Payment Terms: 30 days
TEXT, 'supplier_quotation');

        $selected = $bestCandidate->invoke($extractor, [$weakOcrCandidate, $textLayerCandidate]);

        $this->assertSame('pdftotext-layout', $selected['engine']);
        $this->assertGreaterThan($weakOcrCandidate['score'], $selected['score']);
        $this->assertSame('AQ-1044', $selected['fields']['quote_number']);
    }
}
