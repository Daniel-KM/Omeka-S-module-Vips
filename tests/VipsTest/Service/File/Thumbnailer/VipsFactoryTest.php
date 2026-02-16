<?php declare(strict_types=1);

namespace VipsTest\Service\File\Thumbnailer;

use CommonTest\AbstractHttpControllerTestCase;
use Vips\File\Thumbnailer\Vips;
use Vips\Service\File\Thumbnailer\VipsFactory;
use VipsTest\VipsTestTrait;

/**
 * Tests for the Vips thumbnailer factory.
 */
class VipsFactoryTest extends AbstractHttpControllerTestCase
{
    use VipsTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    /**
     * Test factory creates Vips instance when PHP extension is available.
     */
    public function testFactoryCreatesVipsInstance(): void
    {
        if (!$this->hasVipsExtension()) {
            $this->markTestSkipped('Requires PHP vips extension.');
        }

        $factory = new VipsFactory();
        $services = $this->getServiceLocator();
        $thumbnailer = $factory($services, Vips::class);

        $this->assertInstanceOf(Vips::class, $thumbnailer);
    }

    /**
     * Test Vips thumbnailer is registered in service manager.
     */
    public function testVipsThumbnailerIsRegistered(): void
    {
        if (!$this->hasVipsExtension()) {
            $this->markTestSkipped('Requires PHP vips extension.');
        }

        $services = $this->getServiceLocator();
        $this->assertTrue($services->has(Vips::class));
        $thumbnailer = $services->get(Vips::class);
        $this->assertInstanceOf(Vips::class, $thumbnailer);
    }
}
