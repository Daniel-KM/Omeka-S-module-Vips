<?php declare(strict_types=1);

namespace VipsTest\File\Thumbnailer;

use CommonTest\AbstractHttpControllerTestCase;
use Vips\File\Thumbnailer\Vips;
use Vips\File\Thumbnailer\VipsCli;
use VipsTest\VipsTestTrait;

/**
 * EXIF orientation tests for Vips thumbnailers.
 *
 * The fixture test-exif6.jpg is 100x60 raw pixels with EXIF
 * orientation 6 (90° CW). After auto-orient the visual dimensions
 * are 60x100 (portrait).
 *
 * @group integration
 */
class ExifOrientationTest extends AbstractHttpControllerTestCase
{
    use VipsTestTrait;

    public function tearDown(): void
    {
        $this->cleanupTempFiles();
        parent::tearDown();
    }

    /**
     * VipsExt: proportional thumbnail of EXIF-6 image must be
     * portrait (height > width).
     */
    public function testVipsExtAutoOrientsExif6(): void
    {
        if (!$this->hasVipsLibrary()) {
            $this->markTestSkipped('Requires PHP vips extension and jcupitt/vips.');
        }

        $this->loginAdmin();
        $services = $this->getServiceLocator();

        /** @var Vips $thumbnailer */
        $thumbnailer = $services->get(Vips::class);
        $tempFile = $this->createTempFileFromFixture('test-exif6.jpg');
        $thumbnailer->setSource($tempFile);

        $result = $thumbnailer->create('default', 50);
        $this->registerTempFile($result);

        $this->assertFileExists($result);
        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        // After auto-orient the image is portrait: height > width.
        $this->assertGreaterThan(
            $imageData[0],
            $imageData[1],
            'EXIF-6 thumbnail should be portrait (height > width)'
        );
    }

    /**
     * VipsCli: proportional thumbnail of EXIF-6 image must be
     * portrait (height > width).
     */
    public function testVipsCliAutoOrientsExif6(): void
    {
        $this->loginAdmin();

        if (!$this->hasVipsCli()) {
            $this->markTestSkipped('Requires vips CLI tool.');
        }

        $services = $this->getServiceLocator();

        /** @var VipsCli $thumbnailer */
        $thumbnailer = $services->get(VipsCli::class);
        $tempFile = $this->createTempFileFromFixture('test-exif6.jpg');
        $thumbnailer->setSource($tempFile);
        $thumbnailer->setOptions([]);

        $result = $thumbnailer->create('default', 50);
        $this->registerTempFile($result);

        $this->assertFileExists($result);
        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        // After auto-orient the image is portrait: height > width.
        $this->assertGreaterThan(
            $imageData[0],
            $imageData[1],
            'EXIF-6 thumbnail should be portrait (height > width)'
        );
    }

    /**
     * VipsExt: square thumbnail of EXIF-6 image must be square.
     */
    public function testVipsExtSquareFromExif6(): void
    {
        if (!$this->hasVipsLibrary()) {
            $this->markTestSkipped('Requires PHP vips extension and jcupitt/vips.');
        }

        $this->loginAdmin();
        $services = $this->getServiceLocator();

        /** @var Vips $thumbnailer */
        $thumbnailer = $services->get(Vips::class);
        $tempFile = $this->createTempFileFromFixture('test-exif6.jpg');
        $thumbnailer->setSource($tempFile);

        $result = $thumbnailer->create('square', 50);
        $this->registerTempFile($result);

        $this->assertFileExists($result);
        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        $this->assertEquals(50, $imageData[0]);
        $this->assertEquals(50, $imageData[1]);
    }

    /**
     * VipsCli: square thumbnail of EXIF-6 image must be square.
     */
    public function testVipsCliSquareFromExif6(): void
    {
        $this->loginAdmin();

        if (!$this->hasVipsCli()) {
            $this->markTestSkipped('Requires vips CLI tool.');
        }

        $services = $this->getServiceLocator();

        /** @var VipsCli $thumbnailer */
        $thumbnailer = $services->get(VipsCli::class);
        $tempFile = $this->createTempFileFromFixture('test-exif6.jpg');
        $thumbnailer->setSource($tempFile);
        $thumbnailer->setOptions([]);

        $result = $thumbnailer->create('square', 50);
        $this->registerTempFile($result);

        $this->assertFileExists($result);
        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        $this->assertEquals(50, $imageData[0]);
        $this->assertEquals(50, $imageData[1]);
    }
}
