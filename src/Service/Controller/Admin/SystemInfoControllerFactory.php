<?php declare(strict_types=1);

namespace Vips\Service\Controller\Admin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Vips\Controller\Admin\SystemInfoController;

class SystemInfoControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SystemInfoController(
            $services->get('Omeka\Connection'),
            $services->get('Config'),
            $services->get('Omeka\Cli'),
            $services->get('Omeka\ModuleManager')
        );
    }
}
