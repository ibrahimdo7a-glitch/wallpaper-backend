<?php

return [
    'class_namespace' => 'App\\Livewire',

    'view_path' => resource_path('views/livewire'),

    'layout' => 'components.layouts.app',

    'lazy_placeholder' => null,

    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['required', 'file', 'max:563200'], // 550 MB (APKs)
        'directory' => 'livewire-tmp',
        'middleware' => 'throttle:60,1',
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'mpg', 'webp',
            'weba', 'ogv', 'oga', 'webm',
        ],
        'max_upload_size' => 563200,
    ],

    'inject_assets' => true,

    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#2563EB',
    ],

    'inject_morph_markers' => true,

    'pagination_theme' => 'tailwind',
];
