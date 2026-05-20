<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ExternalDocumentPreviewFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_document_preview_uses_company_formatting(): void
    {
        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'base_currency' => 'SGD',
            'date_format' => 'Y-m-d',
            'number_format' => 'en-SG',
        ]));

        $user = User::factory()->create([
            'role' => 'procurement',
            'is_active' => true,
        ]);

        $document = new Document([
            'type' => 'purchase_request',
            'direction' => 'incoming',
            'document_number' => 'PR-2026-00001',
            'status' => 'draft',
            'issue_date' => '2026-05-20',
            'currency' => null,
            'subtotal' => 1234.56,
            'tax_total' => 0,
            'total' => 1234.56,
        ]);
        $document->exists = true;
        $document->id = 1;
        $document->setRelation('attachments', new Collection());
        $document->setRelation('items', new Collection([
            new DocumentItem([
                'description' => 'Preview service',
                'quantity' => 1,
                'unit' => 'job',
                'line_total' => 1234.56,
            ]),
        ]));

        $html = $this->actingAs($user)->view('documents.partials.external-document-preview', [
            'document' => $document,
            'sourcePanelMode' => 'full',
        ])->render();

        $this->assertStringContainsString('2026-05-20', $html);
        $this->assertStringContainsString('SGD 1,234.56', $html);
        $this->assertStringNotContainsString('MYR 1,234.56', $html);
        $this->assertStringNotContainsString('20 May 2026', $html);
    }
}
