<?php declare(strict_types=1);

namespace VipsTest\File\Thumbnailer;

use CommonTest\AbstractHttpControllerTestCase;
use Vips\File\Thumbnailer\Vips;
use VipsTest\VipsTestTrait;

/**
 * Tests for the Vips PHP extension thumbnailer.
 *
 * @group integration
 */
class VipsExtTest extends AbstractHttpControllerTestCase
{
    use VipsTestTrait;

    /**
     * @var Vips
     */
    protected $thumbnailer;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        if (!$this->hasVipsLibrary()) {
            $this->markTestSkipped('Requires PHP vips extension and jcupitt/vips library.');
        }

        $services = $this->getServiceLocator();
        $this->thumbnailer = $services->get(Vips::class);
    }

    public function tearDown(): void
    {
        $this->cleanupTempFiles();
        parent::tearDown();
    }

    /**
     * Test that the Vips instance is created when extension is loaded.
     */
    public function testConstructorWithExtensionLoaded(): void
    {
        $services = $this->getServiceLocator();
        $thumbnailer = $services->get(Vips::class);
        $this->assertInstanceOf(Vips::class, $thumbnailer);
    }

    /**
     * Test creating a square thumbnail from a landscape image.
     */
    public function testCreateSquareThumbnailFromLandscape(): void
    {
        $tempFile = $this->createTempFileFromFixture('test-landscape.jpg');
        $this->thumbnailer->setSource($tempFile);

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

        $result = $this->thumbnailer->create('default', 200);
        $this->registerTempFile($result);

        $this->assertFileExists($result);

        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        // Small image 100x80 should not be upscaled.
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

        $result = $this->thumbnailer->create('square', 200);
        $this->registerTempFile($result);

        $this->assertFileExists($result);

        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        $this->assertEquals(200, $imageData[0]);
        $this->assertEquals(200, $imageData[1]);
    }

    /**
     * Test square thumbnail with attention gravity (default).
     */
    public function testSquareThumbnailWithAttentionGravity(): void
    {
        $tempFile = $this->createTempFileFromFixture('test-landscape.jpg');
        $this->thumbnailer->setSource($tempFile);

        $result = $this->thumbnailer->create('square', 200, ['vips_gravity' => 'attention']);
        $this->registerTempFile($result);

        $this->assertFileExists($result);
        $imageData = getimagesize($result);
        $this->assertEquals(200, $imageData[0]);
        $this->assertEquals(200, $imageData[1]);
    }

    /**
     * Test square thumbnail with entropy gravity.
     */
    public function testSquareThumbnailWithEntropyGravity(): void
    {
        $tempFile = $this->createTempFileFromFixture('test-landscape.jpg');
        $this->thumbnailer->setSource($tempFile);

        $result = $this->thumbnailer->create('square', 200, ['vips_gravity' => 'entropy']);
        $this->registerTempFile($result);

        $this->assertFileExists($result);
        $imageData = getimagesize($result);
        $this->assertEquals(200, $imageData[0]);
        $this->assertEquals(200, $imageData[1]);
    }

    /**
     * Test square thumbnail with ImageMagick gravity mapping.
     *
     * @dataProvider imagickGravityProvider
     */
    public function testSquareThumbnailWithImagickGravity(string $gravity): void
    {
        $tempFile = $this->createTempFileFromFixture('test-landscape.jpg');
        $this->thumbnailer->setSource($tempFile);

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
            'northwest' => ['northwest'],
            'north'     => ['north'],
            'northeast' => ['northeast'],
            'west'      => ['west'],
            'center'    => ['center'],
            'east'      => ['east'],
            'southwest' => ['southwest'],
            'south'     => ['south'],
            'southeast' => ['southeast'],
        ];
    }

    /**
     * Test proportional thumbnail of a square image.
     */
    public function testProportionalThumbnailFromSquare(): void
    {
        $tempFile = $this->createTempFileFromFixture('test-square.jpg');
        $this->thumbnailer->setSource($tempFile);

        $result = $this->thumbnailer->create('default', 200);
        $this->registerTempFile($result);

        $this->assertFileExists($result);

        $imageData = getimagesize($result);
        $this->assertNotFalse($imageData);
        // Square 400x400 scaled to 200x200.
        $this->assertEquals(200, $imageData[0]);
    }

    /**
     * Test output is JPEG format.
     */
    public function testOutputIsJpeg(): void
    {
        $tempFile = $this->createTempFileFromFixture('test-landscape.jpg');
        $this->thumbnailer->setSource($tempFile);

        $result = $this->thumbnailer->create('default', 200);
        $this->registerTempFile($result);

        $imageData = getimagesize($result);
        $this->assertEquals(IMAGETYPE_JPEG, $imageData[2]);
    }
}
