<?php declare(strict_types=1);

namespace Vips\Controller\Admin;

use Laminas\View\Model\ViewModel;

class SystemInfoController extends \Omeka\Controller\Admin\SystemInfoController
{
    /**
     * The method getSystemInfo() is private in \Omeka\Controller\Admin\SystemInfoController,
     * so override browseAction().
     */
    public function browseAction()
    {
        // The method to get info is private, so use reflection.
        // $info = $this->getSystemInfo();
        $reflection = new \ReflectionClass($this);
        $method = $reflection->getMethod('getSystemInfo');
        $method->setAccessible(true);
        $info = $method->invoke($this);

        $eventManager = $this->getEventManager();
        $args = $eventManager->prepareArgs([
            'info' => $info,
        ]);
        $eventManager->trigger('system.info', $this, $args);

        $info = $args['info'];

        $view = new ViewModel([
            'info' => $info,
        ]);
        return $view
            ->setTemplate('omeka/admin/system-info/browse');
    }
}
