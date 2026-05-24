<?php

return [
    'enabled' => env('OCR_ENABLED', true),
    'use_pdf_text_layer' => env('OCR_USE_PDF_TEXT_LAYER', true),
    'pdftotext_path' => env('PDFTOTEXT_PATH', 'pdftotext'),
    'tesseract_path' => env('TESSERACT_PATH', 'tesseract'),
    'ghostscript_path' => env('GHOSTSCRIPT_PATH', 'gswin64c'),
    'language' => env('TESSERACT_LANGUAGE', 'eng'),
    'timeout' => (int) env('TESSERACT_TIMEOUT', 30),
    'pdf_max_pages' => (int) env('OCR_PDF_MAX_PAGES', 2),
    'tesseract_psm_modes' => env('TESSERACT_PSM_MODES', '6,4,11'),
    'high_confidence_score' => (int) env('OCR_HIGH_CONFIDENCE_SCORE', 85),
    'paddleocr' => [
        'enabled' => env('PADDLEOCR_ENABLED', false),
        'version' => '3.5.0',
        'python' => env('PADDLEOCR_PYTHON', 'python'),
        'script' => env('PADDLEOCR_SCRIPT', base_path('tools/ocr/paddle_document_capture.py')),
        'device' => env('PADDLEOCR_DEVICE', 'cpu'),
        'timeout' => (int) env('PADDLEOCR_TIMEOUT', 60),
        'cache_dir' => env('PADDLEOCR_CACHE_DIR', storage_path('app/ocr/paddle')),
        'max_file_kb' => (int) env('PADDLEOCR_MAX_FILE_KB', 8192),
    ],
];
