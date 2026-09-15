<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'serve' => true, 'throw' => false, 'report' => false],
        'public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => env('APP_URL').'/storage', 'visibility' => 'public', 'throw' => false, 'report' => false],
        's3' => ['driver' => 's3', 'key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY'), 'region' => env('AWS_DEFAULT_REGION'), 'bucket' => env('AWS_BUCKET'), 'url' => env('AWS_URL'), 'endpoint' => env('AWS_ENDPOINT'), 'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false), 'throw' => false, 'report' => false],
    ],
    // Uploaded student and school media is intentionally delivered through
    // the expiring, signed /api/v1/media route. Do not create a public
    // symlink that would bypass signature validation and tenant controls.
    'links' => [],
];
