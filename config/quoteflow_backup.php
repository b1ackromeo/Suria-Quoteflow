<?php

return [
    'mysqldump_path' => env('MYSQLDUMP_PATH') ?: 'mysqldump',

    'directory' => env('DATABASE_BACKUP_DIR') ?: storage_path('app/backups'),

    'timeout' => (int) (env('DATABASE_BACKUP_TIMEOUT') ?: 300),
];
