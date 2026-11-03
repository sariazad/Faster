<?php

/*
 * Only the settings this app changes; everything else uses Livewire's defaults.
 * Temporary uploads go up to 250 MB (backup ZIPs); each question image/sound
 * field still limits itself to 10–20 MB.
 */
return [
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:256000'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 15,
        'cleanup' => true,
    ],
];
