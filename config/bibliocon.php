<?php

return [
    'admin_email' => env('ADMIN_EMAIL'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    // Bulk-import directories are restricted to live under this path (or be
    // this path itself). Defaults to the app's storage directory, which is
    // not where the real library lives, so an operator must set
    // IMPORT_BASE_PATH explicitly before running bulk-import for real.
    'import_base_path' => env('IMPORT_BASE_PATH', storage_path('app')),
];
