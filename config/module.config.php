<?php declare(strict_types=1);

namespace Vips;

return [
    'thumbnails' => [
        'types' => [
            'square' => [
                'options' => [
                    // Vips interesting: none, centre, entropy, attention, low, high.
                    // When not set, use main gravity (center by default)..
                    'vips_gravity' => 'attention',
                ],
            ],
        ],
        'thumbnailer_options' => [
            'vips_dir' => null,
        ],
    ],
    'service_manager' => [
        'factories' => [
            File\Thumbnailer\Vips::class => Service\File\Thumbnailer\VipsFactory::class,
            File\Thumbnailer\VipsCli::class => Service\File\Thumbnailer\VipsCliFactory::class,
        ],
        'aliases' => [
            // This option is overridden by the omeka config in config/local.config.php by default.
            'Omeka\File\Thumbnailer' => 'Vips\File\Thumbnailer\Vips',
        ],
    ],
    'controllers' => [
        'factories' => [
            'Omeka\Controller\Admin\SystemInfo' => Service\Controller\Admin\SystemInfoControllerFactory::class,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'vips' => [
    ],
];
