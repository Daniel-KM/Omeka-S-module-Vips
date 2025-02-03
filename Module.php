<?php declare(strict_types=1);

namespace Vips;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
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
    public function init(ModuleManager $moduleManager): void
    {
        // To use the module without php-vips, skip composer.
        // TODO Find a better way to manage the module without php-vips.
        if (function_exists('vips_version')) {
            require_once __DIR__ . '/vendor/autoload.php';
        }

        $moduleManager->getEventManager()->attach(ModuleEvent::EVENT_MERGE_CONFIG, [$this, 'onEventMergeConfig']);
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Force thumbnailer = vips/vipscli in config, else this module is useless.
     */
    public function onEventMergeConfig(ModuleEvent $event): void
    {
        // At this point, the config is read only, so it is copied and replaced.
        /** @var \Laminas\ModuleManager\Listener\ConfigListener $configListener */
        $configListener = $event->getParam('configListener');
        $config = $configListener->getMergedConfig(false);
        $thumbnailer = $config['service_manager']['aliases']['Omeka\File\Thumbnailer'];
        if (in_array($thumbnailer, ['Vips\File\Thumbnailer\Vips', 'Vips\File\Thumbnailer\VipsCli'])) {
            return;
        }
        $config['service_manager']['aliases']['Omeka\File\Thumbnailer'] = function_exists('vips_version')
            ? 'Vips\File\Thumbnailer\Vips'
            : 'Vips\File\Thumbnailer\VipsCli';
        $configListener->setMergedConfig($config);
    }

    public function install(ServiceLocatorInterface $services)
    {
        /**
         * @var \Omeka\Stdlib\Cli $cli
         * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
         */
        $plugins = $services->get('ControllerPluginManager');
        $cli = $services->get('Omeka\Cli');
        $translate = $plugins->get('translate');
        $messenger = $plugins->get('messenger');

        // Check if vips is installed.
        $hasVips = function_exists('vips_version');
        $hasVipsCli = (bool) $cli->getCommandPath('vips');
        if (!$hasVips && !$hasVipsCli) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The php-extension "php-vips" (recommended) or the library "vips" should be installed first to use this module.') // @translate
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        if (!$hasVips) {
            $messenger->addWarning(new \Omeka\Stdlib\Message(
                'It is recommnded to use php-extension "php-vips" instead of the cli "vips" for performance, unless you have memory issues on big images.' // @translate
            ));
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // TODO To be replaced by omeka controller once the event will be integrated upstream.
        $sharedEventManager->attach(
            \Vips\Controller\Admin\SystemInfoController::class,
            'system.info',
            [$this, 'handleSystemInfo']
        );
    }

    /**
     * Adapted from:
     * @see \Omeka\Controller\Admin\SystemInfoController.
     */
    public function handleSystemInfo(Event $event): void
    {
        /**
         * @var \Omeka\Stdlib\Cli $cli
         * @var \Omeka\Mvc\Controller\Plugin\Translate $translate
         */
        $services = $this->getServiceLocator();
        $cli = $services->get('Omeka\Cli');
        $config = $services->get('Config');
        $plugins = $services->get('ControllerPluginManager');
        $translate = $plugins->get('translate');

        $info = $event->getParam('info', []);

        $vipsDir = $this->getVipsDir();
        if ($vipsDir) {
            $info['Paths']['Vips directory'] = sprintf(
                '%s %s',
                $this->getVipsDir(),
                !$cli->validateCommand($this->getVipsPath()) ? $translate('[invalid]') : ''
            );
        }

        $thumbnailer = $config['service_manager']['aliases']['Omeka\File\Thumbnailer'];

        // TODO List all available thumbnailers.
        $info['Thumbnailer'] = [
            'Name' => $thumbnailer,
            'Version' => $this->getThumbnailerVersion($thumbnailer),
            // TODO Add the list of supported image formats.
        ];

        $event->setParam('info', $info);
    }

    protected function getThumbnailerVersion(?string $thumbnailerClass): string
    {
        /**
         * @var \Omeka\Stdlib\Cli $cli
         * @var \Omeka\Mvc\Controller\Plugin\Translate $translate
         */
        $services = $this->getServiceLocator();
        $cli = $services->get('Omeka\Cli');
        $config = $services->get('Config');
        $plugins = $services->get('ControllerPluginManager');
        $translate = $plugins->get('translate');

        switch ($thumbnailerClass) {
            default:
                return '';
            case \Omeka\File\Thumbnailer\Gd::class:
                if (!function_exists('gd_info')) {
                    return '';
                }
                $result = gd_info();
                return $result['GD Version'] ?? $result['GD library Version'] ?? '';
            case \Omeka\File\Thumbnailer\Imagick::class:
                if (!class_exists('Imagick', false)) {
                    return '';
                }
                $result = \Imagick::getVersion();
                return $result['versionString'] ?? reset($result);
            case \Omeka\File\Thumbnailer\ImageMagick::class:
                $imageMagickDir = @$config['thumbnails']['thumbnailer_options']['imagemagick_dir']
                    ?: preg_replace('/convert$/', '', $cli->getCommandPath('convert'));
                $imageMagickPath = sprintf('%s/convert', $imageMagickDir);
                $result = $cli->execute(sprintf('%s --version', $imageMagickPath));
                return $result
                    ? str_replace('Version:', '', strtok($result, "\n"))
                    : $translate('[Unable to execute command]');
            case \Omeka\File\Thumbnailer\NoThumbnail::class:
                return '';
            case \Vips\File\Thumbnailer\Vips::class:
                return function_exists('vips_version')
                    ? vips_version()
                    : '';
            case \Vips\File\Thumbnailer\VipsCli::class:
                return $this->getVipsVersion();
        }
    }

    protected function getVipsVersion(): string
    {
        /**
         * @var \Omeka\Stdlib\Cli $cli
         * @var \Omeka\Mvc\Controller\Plugin\Translate $translate
         */
        $services = $this->getServiceLocator();
        $cli = $services->get('Omeka\Cli');
        $plugins = $services->get('ControllerPluginManager');
        $translate = $plugins->get('translate');

        $output = $cli->execute(sprintf('%s --version', $this->getVipsPath()));
        if (!$output) {
            $output = $translate('[Unable to execute command]');
        }
        return $output;
    }

    /**
     * Get the directory where vips is installed.
     */
    protected function getVipsDir(): string
    {
        $services = $this->getServiceLocator();
        $cli = $services->get('Omeka\Cli');
        $config = $services->get('Config');
        $vipsDir = @$config['thumbnails']['thumbnailer_options']['vips_dir'];
        if (!$vipsDir) {
            $vipsDir = (string) preg_replace('/vips$/', '', $cli->getCommandPath('vips'));
        }
        return $vipsDir;
    }

    protected function getVipsPath(): string
    {
        return sprintf('%s/vips', $this->getVipsDir());
    }
}
