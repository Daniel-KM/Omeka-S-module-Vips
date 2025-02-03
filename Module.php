<?php declare(strict_types=1);

namespace Vips;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Module\AbstractModule;

/**
 * Vips.
 *
 * @copyright Daniel Berthereau, 2020-2025
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $services)
    {
        $plugins = $services->get('ControllerPluginManager');
        $translate = $plugins->get('translate');

        // Check if vips is installed.

        /** @var \Omeka\Stdlib\Cli $cli */
        $cli = $services->get('Omeka\Cli');
        $result = $cli->getCommandPath('vips');
        if (!$result) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The library "vips" is not installed.') // @translate
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }
}
