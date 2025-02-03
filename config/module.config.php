<?php declare(strict_types=1);

namespace Vips;

return [
    'thumbnails' => [
        'thumbnailer_options' => [
            'vips_dir' => null,
        ],
    ],
    'service_manager' => [
        'factories' => [
            File\Thumbnailer\VipsCli::class => Service\File\Thumbnailer\VipsCliFactory::class,
        ],
        'aliases' => [
            // This option is overridden by the omeka config in config/local.config.php by default.
            'Omeka\File\Thumbnailer' => 'Vips\File\Thumbnailer\VipsCli',
        ],
    ],
    'vips' => [
    ],
];
