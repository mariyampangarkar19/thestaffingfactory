<?php

return [
    /*
     * Files pass through a temporary upload before App\Actions\Documents\StoreDocument checks their real type and size
     * and stores them on the private disk. The limit here is the largest file any document type accepts (30 MB video).
     */
    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['required', 'file', 'max:30720'],
        'directory' => 'livewire-tmp',
        'middleware' => 'throttle:30,1',
        'preview_mimes' => ['png', 'gif', 'bmp', 'svg', 'wav', 'mp4', 'mov', 'avi', 'wmv', 'mp3', 'm4a', 'jpg', 'jpeg', 'mpga', 'webp', 'wma'],
        'max_upload_time' => 10,
        'cleanup' => true,
    ],
];
