<?php declare(strict_types=1);

namespace Vips\File\Thumbnailer;

use Omeka\File\Exception;
use Omeka\File\TempFileFactory;
use Omeka\File\Thumbnailer\AbstractThumbnailer;

class Vips extends AbstractThumbnailer
{
   /**
     * @var TempFileFactory
     */
    protected $tempFileFactory;

    public function __construct(TempFileFactory $tempFileFactory)
    {
        if (!extension_loaded('vips')) {
            throw new Exception\InvalidThumbnailerException('The vips PHP extension must be loaded to use this thumbnailer.'); // @translate
        }
        $this->tempFileFactory = $tempFileFactory;
    }

    public function create($strategy, $constraint, array $options = [])
    {
        /**
         * @see https://libvips.github.io/php-vips/
         * @see https://www.libvips.org/API/current/Using-vipsthumbnail.html
         *
         * The extension requires vips 8.7 or newer, so it is useless to manage
         * old versions.
         */

        // $this->source is the file; $this->sourceFile is the object TempFile.

        // TODO Is there a way to get the image size from vips? Or use database?
        $imageData = getimagesize($this->source);
        if ($imageData) {
            $origWidth = $imageData[0];
            $origHeight = $imageData[1];
        } else {
            $origWidth = null;
            $origHeight = null;
        }

        // TODO Add an option to resize everything, even smaller images, to the thumbnail size.
        // TODO Use vips supports <>!.

        $args = [];

        /**
         * @todo The options are not available on php-vips, or don't use ::thumbnail.

        // Available parameters on load.
        // Special params on source file are managed via ImageMagick: page,
        // density, background.

        $mediaType = $this->sourceFile->getMediaType();
        $supportPages = [
            'application/pdf',
            'image/gif',
            'image/tiff',
            'image/webp',
        ];
        $supportDpi = [
            'application/pdf',
            'image/svg+xml',
        ];
        $supportBackground = [
            'application/pdf',
        ];

        if (in_array($mediaType, $supportPages)) {
            $args['page'] = (int) $this->getOption('page', 0);
        }
        if (in_array($mediaType, $supportDpi)) {
            $args['dpi'] = 150;
        }
        if (in_array($mediaType, $supportBackground)) {
            $args['background'] ='255 255 255 255';
        }
        */

        // Params on destination are managed via vips.
        if ($strategy === 'square') {
            $newWidth = $constraint;
            $args['height'] = $constraint;
            $vipsCrop = [
                // "none" does not crop (default, not for square).
                // 'none',
                'low',
                'centre',
                'high',
                'attention',
                'entropy',
                // "all" does not crop as square.
                // 'all',
            ];
            $mapImagickToVips = [
                'northwest' => 'high',
                'north' => 'high',
                'northeast' => 'high',
                'west' => 'centre',
                'center' => 'centre',
                'east' => 'centre',
                'southwest' => 'low',
                'south' => 'low',
                'southeast' => 'low',
            ];
            if (empty($options['vips_gravity'])) {
                $gravity = isset($options['gravity']) ? strtolower($options['gravity']) : 'attention';
                if (isset($mapImagickToVips[$gravity])) {
                    $gravity = $mapImagickToVips[$gravity];
                } elseif (!in_array($gravity, $vipsCrop)) {
                    $gravity = 'attention';
                }
            } else {
                $gravity = $vipsCrop[$options['vips_gravity']] ?? 'attention';
            }
            $args['crop'] = $gravity;
        } else {
            if ($imageData) {
                if ($origWidth < $constraint && $origHeight < $constraint) {
                    // Original is smaller than constraint.
                    $newWidth = $origWidth;
                    $args['height'] = $origHeight;
                } elseif ($origWidth > $origHeight) {
                    // Original is paysage.
                    $newWidth = $constraint;
                    $args['height'] = round($origHeight * $constraint / $origWidth);
                } elseif ($origWidth < $origHeight) {
                    // Original is portrait.
                    $newWidth = round($origWidth * $constraint / $origHeight);
                    $args['height'] = $constraint;
                } else {
                    // Original is square.
                    $newWidth = $constraint;
                }
            } else {
                $newWidth = $constraint;
            }
            // $args['crop'] = 'none';
        }

        // TODO Manage icc profile and options "autoOrient" and "pdfUseCropBox".

        $newFile = $this->tempFileFactory->build();
        $tempPath = $newFile->getTempPath() . '.jpg';
        $newFile->delete();

        try {
            $vips = \Jcupitt\Vips\Image::thumbnail($this->source, $newWidth, $args);
            $vips->writeToFile($tempPath);
        } catch (\Exception $e) {
            throw new Exception\CannotCreateThumbnailException($e->getMessage(), $e->getCode());
        }

        return $tempPath;
    }
}
