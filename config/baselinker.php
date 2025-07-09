<?php

return [
    'token' => env('BASELINKER_API_TOKEN'),
    'default_catalog_id' => env('BASELINKER_DEFAULT_CATALOG_ID', '1'),
    'default_inventory_id' => env('BASELINKER_DEFAULT_INVENTORY_ID', '1'),
    'default_warehouse_id' => env('BASELINKER_DEFAULT_WAREHOUSE_ID', '1'),
    'sync_enabled' => env('BASELINKER_SYNC_ENABLED', true),
    'batch_size' => env('BASELINKER_BATCH_SIZE', 50),
    
    'orders' => [
        'sync_days_back' => env('BASELINKER_ORDERS_SYNC_DAYS_BACK', 7),
        'auto_sync_enabled' => env('BASELINKER_ORDERS_AUTO_SYNC', true),
        'sync_interval_minutes' => env('BASELINKER_ORDERS_SYNC_INTERVAL', 15),
    ],

    'tracking_urls' => [
        'dpd' => 'https://tracktrace.dpd.com.pl/parcelDetails?p1={tracking_number}',
        'inpost' => 'https://inpost.pl/sledzenie-przesylek?number={tracking_number}',
        'poczta' => 'https://emonitoring.poczta-polska.pl/?numer={tracking_number}',
        'ups' => 'https://www.ups.com/track?loc=pl_PL&tracknum={tracking_number}',
        'fedex' => 'https://www.fedex.com/apps/fedextrack/?action=track&tracknumbers={tracking_number}',
    ],
];