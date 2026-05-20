<?php

namespace App\Support;

use App\Models\CompanyProfile;
use App\Models\Document;
use Illuminate\Support\Str;

class CompanyPdfText
{
    public function apply(string $html, CompanyProfile $companyProfile, Document $document): string
    {
        $html = $this->addTaxRegistration($html, $companyProfile);

        if ($document->isInvoice()) {
            $html = $this->addPaymentInstructions($html, $companyProfile);
        }

        return $this->replaceFooter($html, $companyProfile);
    }

    private function addTaxRegistration(string $html, CompanyProfile $companyProfile): string
    {
        if (! filled($companyProfile->tax_registration_number)) {
            return $html;
        }

        $taxRegistrationLine = e($companyProfile->taxLabel().' Reg. No. '.$companyProfile->tax_registration_number);

        return (string) preg_replace(
            '/(<div class="company-meta">.*?)(<\/div>)/s',
            '$1<br>'.$taxRegistrationLine.'$2',
            $html,
            1
        );
    }

    private function addPaymentInstructions(string $html, CompanyProfile $companyProfile): string
    {
        $instructionLines = collect($companyProfile->paymentInstructionLines())
            ->map(fn (string $line) => e($line))
            ->filter()
            ->values();

        if ($instructionLines->isEmpty()) {
            return $html;
        }

        $instructionHtml = '<br><strong>Payment Instructions:</strong><br>'.$instructionLines->implode('<br>');

        return (string) preg_replace(
            '/(<div class="payment">\s*<h3>Payment Details<\/h3>\s*<div>.*?)(<\/div>\s*<\/div>\s*<div class="amount-due">)/s',
            '$1'.$instructionHtml.'$2',
            $html,
            1
        );
    }

    private function replaceFooter(string $html, CompanyProfile $companyProfile): string
    {
        if (! filled($companyProfile->pdf_footer)) {
            return $html;
        }

        $footerText = Str::of((string) $companyProfile->pdf_footer)
            ->trim()
            ->replaceMatches('/\s+/', ' ')
            ->toString();

        if ($footerText === '') {
            return $html;
        }

        $footerHtml = '<strong>'.e($companyProfile->displayName()).'</strong> | '.e($footerText);

        return (string) preg_replace(
            '/(<div class="footer">\s*<table.*?<tr>\s*<td style="border: 0; padding: 0;">)(.*?)(<\/td>)/s',
            '$1'.$footerHtml.'$3',
            $html,
            1
        );
    }
}
