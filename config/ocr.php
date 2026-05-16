<?php

return [
    'enabled' => env('OCR_ENABLED', true),
    'tesseract_path' => env('TESSERACT_PATH', 'tesseract'),
    'language' => env('TESSERACT_LANGUAGE', 'eng'),
    'timeout' => (int) env('TESSERACT_TIMEOUT', 30),
    'pdf_max_pages' => (int) env('OCR_PDF_MAX_PAGES', 2),
];
