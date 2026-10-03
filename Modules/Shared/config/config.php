<?php

return [
    'name' => 'Shared',

    'images' => [
        'default_disk' => env('IMAGE_STORAGE_DISK', env('FILESYSTEM_DISK', 'public')),

        'max_file_size_kb' => env('IMAGE_MAX_SIZE_KB', 10240), // 10MB overall hard ceiling

        'allowed_mimes' => [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ],

        'max_dimensions' => [
            'width' => (int) env('IMAGE_MAX_WIDTH', 4096),
            'height' => (int) env('IMAGE_MAX_HEIGHT', 4096),
        ],

        'presets' => [
            'avatar' => [
                'max_size_kb' => 2048,
                'max_width' => 1024,
                'max_height' => 1024,
                'directory' => 'avatars',
                'convert_to' => 'webp',
                'quality' => 85,
            ],
            'event_banner' => [
                'max_size_kb' => 8192,
                'max_width' => 3840,
                'max_height' => 2160,
                'directory' => 'events/banners',
                'convert_to' => 'webp',
                'quality' => 85,
            ],
            'organizer_logo' => [
                'max_size_kb' => 4096,
                'max_width' => 1500,
                'max_height' => 1500,
                'directory' => 'organizers/logos',
                'convert_to' => 'webp',
                'quality' => 90,
            ],
            'general' => [
                'max_size_kb' => 5120,
                'max_width' => 4096,
                'max_height' => 4096,
                'directory' => 'uploads/images',
                'convert_to' => 'webp',
                'quality' => 85,
            ],
        ],

        'strip_metadata' => true,
        'scan_polyglot' => true,
    ],
];
