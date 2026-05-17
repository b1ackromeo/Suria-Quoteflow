<?php

namespace App\Services\Documents;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class PaddleOcrDocumentAnalyzer
{
    public const VERSION = '3.5.0';

    public function candidates(Attachment $attachment): array
    {
        if (! (bool) config('ocr.paddleocr.enabled', false)) {
            return [];
        }

        $path = Storage::path($attachment->path);

        if (! is_file($path)) {
            return [];
        }

        $maxFileKilobytes = max(1, (int) config('ocr.paddleocr.max_file_kb', 8192));
        $attachmentKilobytes = (int) ceil(((int) ($attachment->size ?? 0)) / 1024);
        if ($attachmentKilobytes > $maxFileKilobytes) {
            return [];
        }

        $script = (string) config('ocr.paddleocr.script', base_path('tools/ocr/paddle_document_capture.py'));
        if (! is_file($script)) {
            return [];
        }

        $cacheDir = (string) config('ocr.paddleocr.cache_dir', storage_path('app/ocr/paddle'));
        if ($cacheDir !== '' && ! is_dir($cacheDir) && ! mkdir($cacheDir, 0775, true) && ! is_dir($cacheDir)) {
            return [];
        }

        $process = new Process([
            (string) config('ocr.paddleocr.python', 'python'),
            $script,
            '--input',
            $path,
            '--expected-version',
            self::VERSION,
            '--max-pages',
            (string) max(1, (int) config('ocr.pdf_max_pages', 2)),
            '--device',
            (string) config('ocr.paddleocr.device', 'cpu'),
            '--cache-dir',
            $cacheDir,
        ]);

        if ($cacheDir !== '') {
            $process->setEnv([
                'PADDLEOCR_HOME' => $cacheDir,
                'PADDLE_HOME' => $cacheDir,
                'HF_HOME' => $cacheDir.DIRECTORY_SEPARATOR.'huggingface',
                'XDG_CACHE_HOME' => $cacheDir,
            ]);
        }

        $process->setTimeout(max(10, (int) config('ocr.paddleocr.timeout', config('ocr.timeout', 30))));
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $payload = json_decode($process->getOutput(), true);
        if (! is_array($payload)) {
            return [];
        }

        $text = trim((string) ($payload['text'] ?? ''));
        if ($text === '') {
            return [];
        }

        $engine = sprintf(
            'paddleocr-%s-%s',
            self::VERSION,
            Str::slug((string) ($payload['pipeline'] ?? 'ppstructurev3'))
        );

        return [[
            'engine' => $engine,
            'text' => $text,
        ]];
    }
}
