(function () {
    const config = window.QuoteFlowCompanyFormat || {};
    const workspace = document.querySelector('[data-document-form-workspace]');

    if (!workspace) {
        return;
    }

    const locale = config.numberFormat || 'en-MY';
    const baseCurrency = String(config.baseCurrency || 'MYR').toUpperCase();
    const taxLabel = config.taxLabel || 'Tax';
    const defaultTaxRate = Number(config.defaultTaxRate || 0);
    const dateFormat = config.dateFormat || 'd M Y';

    function currencyInputValue() {
        return String(document.querySelector('[name="currency"]')?.value || baseCurrency).toUpperCase();
    }

    function formatNumberValue(amount, decimals = 2) {
        return Number(amount || 0).toLocaleString(locale, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    }

    function formatMoneyValue(amount) {
        return `${currencyInputValue()} ${formatNumberValue(amount, 2)}`;
    }

    function formatQuantityValue(amount) {
        return Number(amount || 0).toLocaleString(locale, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3,
        });
    }

    function formatDateValue(value, fallback) {
        if (!value) return fallback;
        const date = new Date(`${value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return fallback;

        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = String(date.getFullYear());
        const shortMonth = date.toLocaleString('en', { month: 'short' });

        switch (dateFormat) {
            case 'Y-m-d':
                return `${year}-${month}-${day}`;
            case 'm/d/Y':
                return `${month}/${day}/${year}`;
            case 'd/m/Y':
                return `${day}/${month}/${year}`;
            case 'd M Y':
            default:
                return `${day} ${shortMonth} ${year}`;
        }
    }

    window.formatMoney = formatMoneyValue;
    window.formatNumber = formatNumberValue;
    window.formatQuantity = formatQuantityValue;
    window.formatPreviewDate = formatDateValue;

    function setTaxLabel() {
        const summaryTax = document.querySelector('[data-summary-tax]');
        if (summaryTax?.previousElementSibling) {
            summaryTax.previousElementSibling.textContent = taxLabel;
        }

        const previewTax = document.querySelector('[data-preview-tax]');
        if (previewTax?.previousElementSibling) {
            previewTax.previousElementSibling.textContent = taxLabel;
        }

        const row = previewTax?.closest('tr');
        const labelCell = row?.querySelector('td:first-child');
        if (labelCell) {
            labelCell.textContent = taxLabel;
        }
    }

    function setInitialMoneyPlaceholders() {
        document.querySelectorAll('[data-summary-subtotal], [data-summary-tax], [data-summary-total], [data-preview-subtotal], [data-preview-tax], [data-preview-total]').forEach((target) => {
            if (!target.textContent.trim() || /^(MYR|SGD|USD|EUR|GBP|AUD|NZD|IDR|THB|PHP|VND|BND)\s+0[.,]00$/i.test(target.textContent.trim())) {
                target.textContent = formatMoneyValue(0);
            }
        });
    }

    function applyDefaultTaxRate() {
        const form = document.querySelector('form.document-studio-form');
        const taxField = document.querySelector('[name="document_tax_rate"]');
        const isEditForm = !!form?.querySelector('[name="_method"]');

        if (!taxField || isEditForm || defaultTaxRate <= 0) {
            return;
        }

        const current = String(taxField.value || '').trim();
        if (current === '' || Number(current) === 0) {
            taxField.value = String(defaultTaxRate);
        }
    }

    function refreshTotals() {
        if (typeof window.updateDocumentTotals === 'function') {
            window.updateDocumentTotals();
        }
        if (typeof window.syncQuotationPreview === 'function') {
            window.syncQuotationPreview();
        }
    }

    function applyCompanyFormatting() {
        setTaxLabel();
        applyDefaultTaxRate();
        setInitialMoneyPlaceholders();
        refreshTotals();
    }

    applyCompanyFormatting();

    document.addEventListener('input', (event) => {
        if (event.target && event.target.matches('[name="currency"], [name="document_tax_rate"], [name$="[quantity]"], [name$="[unit_price]"]')) {
            setTaxLabel();
            refreshTotals();
        }
    });

    document.addEventListener('change', (event) => {
        if (event.target && event.target.matches('[name="currency"], [name="document_tax_rate"], [name$="[product_id]"]')) {
            setTaxLabel();
            refreshTotals();
        }
    });
})();
