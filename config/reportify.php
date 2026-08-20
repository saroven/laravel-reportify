<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | The storage disk where generated export files (PDF, Excel, CSV, TXT, ZIP)
    | will be saved. Defaults to 'public'.
    |
    */
    'storage_disk' => env('REPORTIFY_STORAGE_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Force Synchronous Exports
    |--------------------------------------------------------------------------
    |
    | Set to true to force all exports to execute inline synchronously.
    | Useful for client environments without background queue worker daemons.
    |
    */
    'force_sync' => (bool) env('REPORTIFY_FORCE_SYNC', false),

    /*
    |--------------------------------------------------------------------------
    | Default Export Base Folder
    |--------------------------------------------------------------------------
    |
    | Relative directory path within the storage disk for saving exported files.
    |
    */
    'export_directory' => 'exports',

    /*
    |--------------------------------------------------------------------------
    | Data Chunking & Limits
    |--------------------------------------------------------------------------
    |
    | Default chunk size for batch queries and PDF merging/chunking.
    |
    */
    'chunk_size' => (int) env('REPORTIFY_CHUNK_SIZE', 2000),

    /*
    |--------------------------------------------------------------------------
    | mPDF Engine Defaults
    |--------------------------------------------------------------------------
    |
    | Configuration options for the mPDF engine wrapper.
    |
    */
    'mpdf' => [
        'backtrack_limit' => '1000000000',
        'recursion_limit' => '1000000000',
        'default_paper_size' => 'A4',
        'default_orientation' => 'P',
        'author' => env('APP_NAME', 'Laravel'),
    ],

    /*
    |--------------------------------------------------------------------------
    | View Mappings
    |--------------------------------------------------------------------------
    |
    | Default header and footer Blade views used for PDF rendering.
    |
    */
    'views' => [
        'pdf_header' => 'reportify::pdf-header',
        'pdf_footer' => 'reportify::pdf-footer',
        'empty_pdf'  => 'reportify::empty-pdf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Download Manager Integration Hook
    |--------------------------------------------------------------------------
    |
    | Functions or callbacks used for logging background job status updates.
    |
    */
    'download_manager' => [
        'enabled' => true,
        'create_callback' => 'importDownloadManagerCreate',
        'update_callback' => 'importDownloadManagerUpdate',
        'delete_callback' => 'importDownloadManagerDeleteFile',
    ],
];
