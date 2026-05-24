<?php

return [
    'mysqldump_path' => env('MYSQLDUMP_PATH') ?: 'mysqldump',

    'directory' => env('DATABASE_BACKUP_DIR') ?: storage_path('app/backups'),

    'timeout' => (int) (env('DATABASE_BACKUP_TIMEOUT') ?: 300),

    'files' => [
        'directory' => env('FILE_BACKUP_DIR') ?: storage_path('app/backups'),

        'sources' => [
            [
                'path' => storage_path('app/attachments'),
                'prefix' => 'attachments',
            ],
            [
                'path' => storage_path('app/public/company-logos'),
                'prefix' => 'public/company-logos',
            ],
        ],
    ],
];
