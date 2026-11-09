<?php

// آخر القائمة — contact links on «من نحن» (label => url). Leave a value empty to hide it.
return [
    'links' => [
        'X' => env('AKHER_X', ''),
        'Instagram' => env('AKHER_INSTAGRAM', ''),
        'البريد' => env('AKHER_EMAIL') ? 'mailto:'.env('AKHER_EMAIL') : '',
        'مين الأسبق' => '/',
    ],
];
