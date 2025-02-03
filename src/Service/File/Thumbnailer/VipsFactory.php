<?php declare(strict_types=1);

namespace Vips\Service\File\Thumbnailer;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Vips\File\Thumbnailer\Vips;

class VipsFactory implements FactoryInterface
{
    /**
     * Create the Vips thumbnailer service.
     *
     * @return Vips
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Vips(
            $services->get('Omeka\File\TempFileFactory')
        );
    }
}
