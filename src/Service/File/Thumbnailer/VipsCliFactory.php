<?php declare(strict_types=1);

namespace Vips\Service\File\Thumbnailer;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Vips\File\Thumbnailer\VipsCli;

class VipsCliFactory implements FactoryInterface
{
    /**
     * Create the VipsCli thumbnailer service.
     *
     * @return VipsCli
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new VipsCli(
            $services->get('Omeka\Cli'),
            $services->get('Omeka\File\TempFileFactory')
        );
    }
}
