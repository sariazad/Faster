<?php

// ألف ياء — links shown on "تعرّف علينا" and the page footers. Edit freely (or use .env).
return [
    'email' => env('ALEFYA_EMAIL', 'hello@example.com'),

    'accounts' => [
        [
            'name' => env('ALEFYA_ACCOUNT_NAME', 'ألف ياء'),
            'x' => env('ALEFYA_X', 'https://x.com/'),
            'tiktok' => env('ALEFYA_TIKTOK', 'https://www.tiktok.com/'),
            'youtube' => env('ALEFYA_YOUTUBE', 'https://www.youtube.com/'),
        ],
    ],

    // Single challenge
    'solo' => [
        'rows' => 3,
        'cols' => 5,
        'seconds' => 30,
        'bonus' => 2,
    ],
];
