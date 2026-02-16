<?php declare(strict_types=1);

namespace VipsTest\Service\File\Thumbnailer;

use CommonTest\AbstractHttpControllerTestCase;
use Vips\File\Thumbnailer\VipsCli;
use Vips\Service\File\Thumbnailer\VipsCliFactory;
use VipsTest\VipsTestTrait;

/**
 * Tests for the VipsCli thumbnailer factory.
 */
class VipsCliFactoryTest extends AbstractHttpControllerTestCase
{
    use VipsTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    /**
     * Test factory creates VipsCli instance.
     */
    public function testFactoryCreatesVipsCliInstance(): void
    {
        if (!$this->hasVipsCli()) {
            $this->markTestSkipped('Requires vips CLI tool.');
        }

        $factory = new VipsCliFactory();
        $services = $this->getServiceLocator();
        $thumbnailer = $factory($services, VipsCli::class);

        $this->assertInstanceOf(VipsCli::class, $thumbnailer);
    }

    /**
     * Test VipsCli thumbnailer is registered in service manager.
     */
    public function testVipsCliThumbnailerIsRegistered(): void
    {
        if (!$this->hasVipsCli()) {
            $this->markTestSkipped('Requires vips CLI tool.');
        }

        $services = $this->getServiceLocator();
        $this->assertTrue($services->has(VipsCli::class));
        $thumbnailer = $services->get(VipsCli::class);
        $this->assertInstanceOf(VipsCli::class, $thumbnailer);
    }
}
