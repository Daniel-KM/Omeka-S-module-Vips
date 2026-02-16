<?php declare(strict_types=1);

namespace VipsTest\File\Thumbnailer;

use CommonTest\AbstractHttpControllerTestCase;
use Omeka\File\Exception;
use Vips\File\Thumbnailer\VipsCli;
use VipsTest\VipsTestTrait;

/**
 * Tests for the VipsCli thumbnailer.
 *
 * @group integration
 */
class VipsCliTest extends AbstractHttpControllerTestCase
{
    use VipsTestTrait;

    /**
     * @var VipsCli
     */
    protected $thumbnailer;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        if (!$this->hasVipsCli()) {
            $this->markTestSkipped('Requires vips CLI tool.');
        }

        $services = $this->getServiceLocator();
        $this->thumbnailer = $services->get(VipsCli::class);
    }

    public function tearDown(): void
    {
        $this->cleanupTempFiles();
        parent::tearDown();
    }

    /**
     * Test setVipsPath auto-detects vips command.
     */
    public function testSetVipsPathAutoDetects(): void
    {
        $this->thumbnailer->setVipsPath(null);
        // If we get here without exception, auto-detection worked.
        $this->assertTrue(true);
    }

    /**
     * Test setVipsPath with invalid directory throws.
     */
    public function testSetVipsPathWithInvalidDirThrows(): void
    {
        $this->expectException(Exception\InvalidThumbnailerException::class);
        $this->thumbnailer->setVipsPath('/nonexistent/path');
    }

    /**
     * Test getIsOldVips returns false for modern vips.
     */
    public function testGetIsOldVipsReturnsFalseForModernVips(): void
    {
        $this->thumbnailer->setVipsPath(null);
        // vips 8.16.1 is not old (< 8.6).
        $this->assertFalse($this->thumbnailer->getIsOldVips());
    }

    /**
     * Test setIsOldVips can force old mode.
     */
    public function testSetIsOldVipsCanForceOldMode(): void
    {
        $this->thumbnailer->setIsOldVips(true);
        $this->assertTrue($this->thumbnailer->getIsOldVips());

        $this->thumbnailer->setIsOldVips(false);
        $this->assertFalse($this->thumbnailer->getIsOldVips());
    }

    /**
     * Test creating a square thumbnail from a landscape image.
     */
    public function testCreateSquareThumbnailFromLandscape(): void
    {
        $tempFile = $this->createTempFileFromFixture('test-landscape.jpg');
        $this->thumbnailer->setSource($tempFile);
        $this->thumbnailer->setOptions([]);

        $result = $this->thumbnailer->create('square', 200);
        $this->registerTempFile($result);

        $this->assertFileExists($result);
        $this->assertStringEndsWith('.jpg', $result);

        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        $this->assertEquals(200, $imageData[0], 'Square thumbnail width should be 200');
        $this->assertEquals(200, $imageData[1], 'Square thumbnail height should be 200');
    }

    /**
     * Test creating a square thumbnail from a portrait image.
     */
    public function testCreateSquareThumbnailFromPortrait(): void
    {
        $tempFile = $this->createTempFileFromFixture('test-portrait.jpg');
        $this->thumbnailer->setSource($tempFile);
        $this->thumbnailer->setOptions([]);

        $result = $this->thumbnailer->create('square', 200);
        $this->registerTempFile($result);

        $this->assertFileExists($result);

        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        $this->assertEquals(200, $imageData[0]);
        $this->assertEquals(200, $imageData[1]);
    }

    /**
     * Test creating a proportional thumbnail from a landscape image.
     */
    public function testCreateProportionalThumbnailFromLandscape(): void
    {
        $tempFile = $this->createTempFileFromFixture('test-landscape.jpg');
        $this->thumbnailer->setSource($tempFile);
        $this->thumbnailer->setOptions([]);

        $result = $this->thumbnailer->create('default', 200);
        $this->registerTempFile($result);

        $this->assertFileExists($result);

        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        // Landscape 800x600 scaled to width 200 => height 150.
        $this->assertEquals(200, $imageData[0], 'Width should be constraint');
        $this->assertEquals(150, $imageData[1], 'Height should be proportional');
    }

    /**
     * Test creating a proportional thumbnail from a portrait image.
     */
    public function testCreateProportionalThumbnailFromPortrait(): void
    {
        $tempFile = $this->createTempFileFromFixture('test-portrait.jpg');
        $this->thumbnailer->setSource($tempFile);
        $this->thumbnailer->setOptions([]);

        $result = $this->thumbnailer->create('default', 200);
        $this->registerTempFile($result);

        $this->assertFileExists($result);

        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        // Portrait 600x800 scaled to height 200 => width 150.
        $this->assertEquals(150, $imageData[0], 'Width should be proportional');
        $this->assertEquals(200, $imageData[1], 'Height should be constraint');
    }

    /**
     * Test that small images are not upscaled in proportional mode.
     */
    public function testSmallImageNotUpscaled(): void
    {
        $tempFile = $this->createTempFileFromFixture('test-small.jpg');
        $this->thumbnailer->setSource($tempFile);
        $this->thumbnailer->setOptions([]);

        $result = $this->thumbnailer->create('default', 200);
        $this->registerTempFile($result);

        $this->assertFileExists($result);

        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        // Small image 100x80 should not be upscaled beyond original.
        $this->assertLessThanOrEqual(100, $imageData[0]);
        $this->assertLessThanOrEqual(80, $imageData[1]);
    }

    /**
     * Test creating a square thumbnail from a square image.
     */
    public function testCreateSquareThumbnailFromSquare(): void
    {
        $tempFile = $this->createTempFileFromFixture('test-square.jpg');
        $this->thumbnailer->setSource($tempFile);
        $this->thumbnailer->setOptions([]);

        $result = $this->thumbnailer->create('square', 200);
        $this->registerTempFile($result);

        $this->assertFileExists($result);

        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        $this->assertEquals(200, $imageData[0]);
        $this->assertEquals(200, $imageData[1]);
    }

    /**
     * Test square thumbnail with ImageMagick gravity mapping.
     *
     * @dataProvider imagickGravityProvider
     */
    public function testSquareThumbnailWithImagickGravity(string $gravity, string $expectedVips): void
    {
        $tempFile = $this->createTempFileFromFixture('test-landscape.jpg');
        $this->thumbnailer->setSource($tempFile);
        $this->thumbnailer->setOptions([]);

        $result = $this->thumbnailer->create('square', 200, ['gravity' => $gravity]);
        $this->registerTempFile($result);

        $this->assertFileExists($result);
        $imageData = getimagesize($result);
        $this->assertEquals(200, $imageData[0]);
        $this->assertEquals(200, $imageData[1]);
    }

    /**
     * Data provider for ImageMagick gravity values.
     */
    public function imagickGravityProvider(): array
    {
        return [
            'northwest maps to high'  => ['northwest', 'high'],
            'north maps to high'      => ['north', 'high'],
            'northeast maps to high'  => ['northeast', 'high'],
            'west maps to centre'     => ['west', 'centre'],
            'center maps to centre'   => ['center', 'centre'],
            'east maps to centre'     => ['east', 'centre'],
            'southwest maps to low'   => ['southwest', 'low'],
            'south maps to low'       => ['south', 'low'],
            'southeast maps to low'   => ['southeast', 'low'],
        ];
    }

    /**
     * Test square thumbnail with native vips gravity values.
     *
     * @dataProvider vipsGravityProvider
     */
    public function testSquareThumbnailWithVipsGravity(string $gravity): void
    {
        $tempFile = $this->createTempFileFromFixture('test-landscape.jpg');
        $this->thumbnailer->setSource($tempFile);
        $this->thumbnailer->setOptions([]);

        $result = $this->thumbnailer->create('square', 200, ['gravity' => $gravity]);
        $this->registerTempFile($result);

        $this->assertFileExists($result);
        $imageData = getimagesize($result);
        $this->assertEquals(200, $imageData[0]);
        $this->assertEquals(200, $imageData[1]);
    }

    /**
     * Data provider for native vips gravity values.
     */
    public function vipsGravityProvider(): array
    {
        return [
            'low'       => ['low'],
            'centre'    => ['centre'],
            'high'      => ['high'],
            'attention' => ['attention'],
            'entropy'   => ['entropy'],
        ];
    }

    /**
     * Test output is JPEG format.
     */
    public function testOutputIsJpeg(): void
    {
        $tempFile = $this->createTempFileFromFixture('test-landscape.jpg');
        $this->thumbnailer->setSource($tempFile);
        $this->thumbnailer->setOptions([]);

        $result = $this->thumbnailer->create('default', 200);
        $this->registerTempFile($result);

        $imageData = getimagesize($result);
        $this->assertEquals(IMAGETYPE_JPEG, $imageData[2]);
    }

    /**
     * Test proportional thumbnail of a square image.
     */
    public function testProportionalThumbnailFromSquare(): void
    {
        $tempFile = $this->createTempFileFromFixture('test-square.jpg');
        $this->thumbnailer->setSource($tempFile);
        $this->thumbnailer->setOptions([]);

        $result = $this->thumbnailer->create('default', 200);
        $this->registerTempFile($result);

        $this->assertFileExists($result);

        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        // Square 400x400 scaled to 200x200.
        $this->assertEquals(200, $imageData[0]);
        $this->assertEquals(200, $imageData[1]);
    }
}
