<?php declare(strict_types=1);

namespace VipsTest\Controller\Admin;

use CommonTest\AbstractHttpControllerTestCase;
use VipsTest\VipsTestTrait;

/**
 * Tests for the Vips SystemInfo controller.
 */
class SystemInfoControllerTest extends AbstractHttpControllerTestCase
{
    use VipsTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    /**
     * Test that system info route can be accessed.
     */
    public function testSystemInfoRouteCanBeAccessed(): void
    {
        $this->dispatch('/admin/system-info');
        $this->assertResponseStatusCode(200);
        $this->assertControllerName('Omeka\Controller\Admin\SystemInfo');
        $this->assertActionName('browse');
    }

    /**
     * Test that system info controller is the Vips override.
     */
    public function testSystemInfoControllerIsVipsOverride(): void
    {
        $controllers = $this->getServiceLocator()->get('ControllerManager');
        $controller = $controllers->get('Omeka\Controller\Admin\SystemInfo');
        $this->assertInstanceOf(
            \Vips\Controller\Admin\SystemInfoController::class,
            $controller
        );
    }
}
