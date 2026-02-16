<?php declare(strict_types=1);

namespace VipsTest;

use CommonTest\AbstractHttpControllerTestCase;
use Laminas\ModuleManager\ModuleEvent;
use Vips\Module;

/**
 * Tests for the Vips module bootstrap.
 */
class ModuleTest extends AbstractHttpControllerTestCase
{
    use VipsTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    // Config tests.

    /**
     * Test that getConfig returns an array with expected keys.
     */
    public function testGetConfigReturnsArray(): void
    {
        $module = new Module();
        $config = $module->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('thumbnails', $config);
        $this->assertArrayHasKey('service_manager', $config);
        $this->assertArrayHasKey('controllers', $config);
        $this->assertArrayHasKey('translator', $config);
        $this->assertArrayHasKey('vips', $config);
    }

    /**
     * Test that config contains thumbnailer factories.
     */
    public function testConfigRegistersThumbnailerFactories(): void
    {
        $module = new Module();
        $config = $module->getConfig();

        $factories = $config['service_manager']['factories'];
        $this->assertArrayHasKey(\Vips\File\Thumbnailer\Vips::class, $factories);
        $this->assertArrayHasKey(\Vips\File\Thumbnailer\VipsCli::class, $factories);
    }

    /**
     * Test that config sets default thumbnailer alias.
     */
    public function testConfigSetsDefaultThumbnailerAlias(): void
    {
        $module = new Module();
        $config = $module->getConfig();

        $aliases = $config['service_manager']['aliases'];
        $this->assertArrayHasKey('Omeka\File\Thumbnailer', $aliases);
        $this->assertEquals(
            'Vips\File\Thumbnailer\Vips',
            $aliases['Omeka\File\Thumbnailer']
        );
    }

    /**
     * Test that config contains square thumbnail gravity option.
     */
    public function testConfigHasSquareGravityOption(): void
    {
        $module = new Module();
        $config = $module->getConfig();

        $this->assertArrayHasKey('types', $config['thumbnails']);
        $this->assertArrayHasKey('square', $config['thumbnails']['types']);
        $this->assertArrayHasKey('options', $config['thumbnails']['types']['square']);
        $this->assertEquals(
            'attention',
            $config['thumbnails']['types']['square']['options']['vips_gravity']
        );
    }

    /**
     * Test that config contains vips_dir thumbnailer option.
     */
    public function testConfigHasVipsDirOption(): void
    {
        $module = new Module();
        $config = $module->getConfig();

        $this->assertArrayHasKey('thumbnailer_options', $config['thumbnails']);
        $this->assertArrayHasKey('vips_dir', $config['thumbnails']['thumbnailer_options']);
        $this->assertNull($config['thumbnails']['thumbnailer_options']['vips_dir']);
    }

    /**
     * Test that config overrides SystemInfo controller.
     */
    public function testConfigOverridesSystemInfoController(): void
    {
        $module = new Module();
        $config = $module->getConfig();

        $controllers = $config['controllers']['factories'];
        $this->assertArrayHasKey('Omeka\Controller\Admin\SystemInfo', $controllers);
        $this->assertEquals(
            \Vips\Service\Controller\Admin\SystemInfoControllerFactory::class,
            $controllers['Omeka\Controller\Admin\SystemInfo']
        );
    }

    /**
     * Test that the Vips module is active.
     */
    public function testModuleIsActive(): void
    {
        $moduleManager = $this->getServiceLocator()->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('Vips');
        $this->assertNotNull($module);
        $this->assertEquals('active', $module->getState());
    }

    // Mode selection tests.

    /**
     * Test hasVipsLibrary returns true when both ext-vips and jcupitt/vips
     * are available.
     */
    public function testHasVipsLibraryWhenBothAvailable(): void
    {
        if (!$this->hasVipsLibrary()) {
            $this->markTestSkipped('Requires PHP vips extension and jcupitt/vips library.');
        }

        $module = new Module();
        $this->assertTrue($module->hasVipsLibrary());
    }

    /**
     * Test that merged config selects Vips when library is available.
     */
    public function testMergedConfigSelectsVipsWhenLibraryAvailable(): void
    {
        $module = new Module();
        if (!$module->hasVipsLibrary()) {
            $this->markTestSkipped('Requires PHP vips extension and jcupitt/vips library.');
        }

        $config = $this->getServiceLocator()->get('Config');
        $thumbnailer = $config['service_manager']['aliases']['Omeka\File\Thumbnailer'];
        $this->assertEquals(\Vips\File\Thumbnailer\Vips::class, $thumbnailer);
    }

    /**
     * Test that merged config selects VipsCli when library is not available.
     */
    public function testMergedConfigSelectsVipsCliWhenLibraryNotAvailable(): void
    {
        $module = new Module();
        if ($module->hasVipsLibrary()) {
            $this->markTestSkipped('Requires PHP vips extension or jcupitt/vips to NOT be available.');
        }

        $config = $this->getServiceLocator()->get('Config');
        $thumbnailer = $config['service_manager']['aliases']['Omeka\File\Thumbnailer'];
        $this->assertEquals(\Vips\File\Thumbnailer\VipsCli::class, $thumbnailer);
    }

    /**
     * Test onEventMergeConfig keeps alias when already set to Vips.
     */
    public function testOnEventMergeConfigKeepsVipsAlias(): void
    {
        $module = new Module();
        $config = [
            'service_manager' => [
                'aliases' => [
                    'Omeka\File\Thumbnailer' => 'Vips\File\Thumbnailer\Vips',
                ],
            ],
        ];
        $event = $this->createMergeConfigEvent($config);
        $module->onEventMergeConfig($event);

        $result = $event->getParam('configListener')->getMergedConfig(false);
        $this->assertEquals(
            'Vips\File\Thumbnailer\Vips',
            $result['service_manager']['aliases']['Omeka\File\Thumbnailer']
        );
    }

    /**
     * Test onEventMergeConfig keeps alias when already set to VipsCli.
     */
    public function testOnEventMergeConfigKeepsVipsCliAlias(): void
    {
        $module = new Module();
        $config = [
            'service_manager' => [
                'aliases' => [
                    'Omeka\File\Thumbnailer' => 'Vips\File\Thumbnailer\VipsCli',
                ],
            ],
        ];
        $event = $this->createMergeConfigEvent($config);
        $module->onEventMergeConfig($event);

        $result = $event->getParam('configListener')->getMergedConfig(false);
        $this->assertEquals(
            'Vips\File\Thumbnailer\VipsCli',
            $result['service_manager']['aliases']['Omeka\File\Thumbnailer']
        );
    }

    /**
     * Test onEventMergeConfig overrides a non-Vips thumbnailer (e.g. GD).
     */
    public function testOnEventMergeConfigOverridesNonVipsThumbnailer(): void
    {
        $module = new Module();
        $config = [
            'service_manager' => [
                'aliases' => [
                    'Omeka\File\Thumbnailer' => 'Omeka\File\Thumbnailer\Gd',
                ],
            ],
        ];
        $event = $this->createMergeConfigEvent($config);
        $module->onEventMergeConfig($event);

        $result = $event->getParam('configListener')->getMergedConfig(false);
        $expected = $module->hasVipsLibrary()
            ? \Vips\File\Thumbnailer\Vips::class
            : \Vips\File\Thumbnailer\VipsCli::class;
        $this->assertEquals(
            $expected,
            $result['service_manager']['aliases']['Omeka\File\Thumbnailer']
        );
    }

    /**
     * Test onEventMergeConfig overrides ImageMagick thumbnailer.
     */
    public function testOnEventMergeConfigOverridesImageMagickThumbnailer(): void
    {
        $module = new Module();
        $config = [
            'service_manager' => [
                'aliases' => [
                    'Omeka\File\Thumbnailer' => 'Omeka\File\Thumbnailer\ImageMagick',
                ],
            ],
        ];
        $event = $this->createMergeConfigEvent($config);
        $module->onEventMergeConfig($event);

        $result = $event->getParam('configListener')->getMergedConfig(false);
        $this->assertContains(
            $result['service_manager']['aliases']['Omeka\File\Thumbnailer'],
            [\Vips\File\Thumbnailer\Vips::class, \Vips\File\Thumbnailer\VipsCli::class]
        );
    }

    // Version detection tests.

    /**
     * Test isVipsLibraryV2 returns false when library is not installed.
     */
    public function testIsVipsLibraryV2ReturnsFalseWhenNotInstalled(): void
    {
        if ($this->hasVipsLibrary()) {
            $this->markTestSkipped('Requires jcupitt/vips to NOT be installed.');
        }

        $module = new Module();
        $this->assertFalse($module->isVipsLibraryV2());
    }

    /**
     * Test isVipsLibraryV2 detects v2 via FFI class.
     *
     * This test verifies the detection logic: v2 has \Jcupitt\Vips\FFI,
     * v1 does not. The result depends on the installed version.
     */
    public function testIsVipsLibraryV2MatchesFFIClassPresence(): void
    {
        $module = new Module();
        $this->assertSame(
            class_exists(\Jcupitt\Vips\FFI::class),
            $module->isVipsLibraryV2()
        );
    }

    /**
     * Test hasVipsLibrary returns true with ext-vips (v1 path).
     */
    public function testHasVipsLibraryWithExtVips(): void
    {
        if (!extension_loaded('vips') || !class_exists(\Jcupitt\Vips\Image::class)) {
            $this->markTestSkipped('Requires ext-vips and jcupitt/vips library.');
        }

        $module = new Module();
        $this->assertTrue($module->hasVipsLibrary());
    }

    /**
     * Test hasVipsLibrary returns false without library class.
     */
    public function testHasVipsLibraryReturnsFalseWithoutLibrary(): void
    {
        if (class_exists(\Jcupitt\Vips\Image::class)) {
            $this->markTestSkipped('Requires jcupitt/vips to NOT be installed.');
        }

        $module = new Module();
        $this->assertFalse($module->hasVipsLibrary());
    }

    // Both modes comparison tests.

    /**
     * Test both modes produce square thumbnails with same dimensions.
     *
     * @group integration
     */
    public function testBothModesProduceSameSquareDimensions(): void
    {
        if (!$this->hasVipsLibrary() || !$this->hasVipsCli()) {
            $this->markTestSkipped('Requires both PHP vips library and vips CLI.');
        }

        $services = $this->getServiceLocator();
        $vipsExt = $services->get(\Vips\File\Thumbnailer\Vips::class);
        $vipsCli = $services->get(\Vips\File\Thumbnailer\VipsCli::class);

        $tempFileExt = $this->createTempFileFromFixture('test-landscape.jpg');
        $vipsExt->setSource($tempFileExt);
        $resultExt = $vipsExt->create('square', 200);
        $this->registerTempFile($resultExt);

        $tempFileCli = $this->createTempFileFromFixture('test-landscape.jpg');
        $vipsCli->setSource($tempFileCli);
        $vipsCli->setOptions([]);
        $resultCli = $vipsCli->create('square', 200);
        $this->registerTempFile($resultCli);

        $extData = getimagesize($resultExt);
        $cliData = getimagesize($resultCli);

        $this->assertEquals($extData[0], $cliData[0], 'Both modes should produce same square width');
        $this->assertEquals($extData[1], $cliData[1], 'Both modes should produce same square height');
    }

    /**
     * Test both modes produce proportional thumbnails with same dimensions.
     *
     * @group integration
     */
    public function testBothModesProduceSameProportionalDimensions(): void
    {
        if (!$this->hasVipsLibrary() || !$this->hasVipsCli()) {
            $this->markTestSkipped('Requires both PHP vips library and vips CLI.');
        }

        $services = $this->getServiceLocator();
        $vipsExt = $services->get(\Vips\File\Thumbnailer\Vips::class);
        $vipsCli = $services->get(\Vips\File\Thumbnailer\VipsCli::class);

        $fixtures = ['test-landscape.jpg', 'test-portrait.jpg', 'test-square.jpg', 'test-small.jpg'];
        foreach ($fixtures as $fixture) {
            $tempFileExt = $this->createTempFileFromFixture($fixture);
            $vipsExt->setSource($tempFileExt);
            $resultExt = $vipsExt->create('default', 200);
            $this->registerTempFile($resultExt);

            $tempFileCli = $this->createTempFileFromFixture($fixture);
            $vipsCli->setSource($tempFileCli);
            $vipsCli->setOptions([]);
            $resultCli = $vipsCli->create('default', 200);
            $this->registerTempFile($resultCli);

            $extData = getimagesize($resultExt);
            $cliData = getimagesize($resultCli);

            $this->assertEquals($extData[0], $cliData[0], "Both modes should produce same width for $fixture");
            $this->assertEquals($extData[1], $cliData[1], "Both modes should produce same height for $fixture");
        }
    }

    // Helpers.

    public function tearDown(): void
    {
        $this->cleanupTempFiles();
        parent::tearDown();
    }

    /**
     * Create a ModuleEvent with a mock ConfigListener for testing
     * onEventMergeConfig().
     */
    protected function createMergeConfigEvent(array $config): ModuleEvent
    {
        $configListener = new class($config) {
            private array $config;

            public function __construct(array $config)
            {
                $this->config = $config;
            }

            public function getMergedConfig(bool $flag): array
            {
                return $this->config;
            }

            public function setMergedConfig(array $config): void
            {
                $this->config = $config;
            }
        };

        $event = new ModuleEvent();
        $event->setParam('configListener', $configListener);
        return $event;
    }
}
