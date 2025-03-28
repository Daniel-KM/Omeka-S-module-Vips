<?php declare(strict_types=1);

namespace Vips\File\Thumbnailer;

use Omeka\File\Exception;
use Omeka\File\TempFileFactory;
use Omeka\File\Thumbnailer\AbstractThumbnailer;
use Omeka\Stdlib\Cli;

class VipsCli extends AbstractThumbnailer
{
    const VIPS_COMMAND = 'vips';

    /**
     * @var string|false Path to the "vips" command. False means unavailable.
     */
    protected $vipsPath;

    /**
     * Old means version < 8.6.
     *
     * @var bool
     */
    protected $isOldVips;

    /**
     * @var Cli
     */
    protected $cli;

    /**
     * @var TempFileFactory
     */
    protected $tempFileFactory;

    /**
     * @param Cli $cli
     * @param TempFileFactory $tempFileFactory
     */
    public function __construct(Cli $cli, TempFileFactory $tempFileFactory)
    {
        $this->cli = $cli;
        $this->tempFileFactory = $tempFileFactory;
    }

    public function setOptions(array $options): void
    {
        parent::setOptions($options);
        if (is_null($this->vipsPath)) {
            $this->setVipsPath($this->getOption('vips_dir'));
        }
    }

    public function create($strategy, $constraint, array $options = [])
    {
        if ($this->vipsPath === false) {
            throw new Exception\CannotCreateThumbnailException;
        }

        if ($this->getIsOldVips()) {
            return $this->createWithOldVips($strategy, $constraint, $options);
        }

        // $this->source is the file; $this->sourceFile is the object TempFile.
        $origPath = $this->source;

        // TODO Is there a way to get the image size from vips? Or use database?
        $imageData = getimagesize($this->source);
        if ($imageData) {
            $origWidth = $imageData[0];
            $origHeight = $imageData[1];
        } else {
            $origWidth = null;
            $origHeight = null;
        }

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
        $loadOptions = [];
        if (in_array($mediaType, $supportPages)) {
            $page = (int) $this->getOption('page', 0);
            $loadOptions[] = "page=$page";
        }
        if (in_array($mediaType, $supportDpi)) {
            $loadOptions[] = 'dpi=150';
        }
        if (in_array($mediaType, $supportBackground)) {
            $loadOptions[] = 'background=255 255 255 255';
        }
        if (count($loadOptions)) {
            $origPath .= '[' . implode(',', $loadOptions) . ']';
        }

        // Params on destination are managed via vips.
        if ($strategy === 'square') {
            $newWidth = $constraint;
            $newHeight = $constraint;
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
            $gravity = isset($options['gravity']) ? strtolower($options['gravity']) : 'attention';
            if (isset($mapImagickToVips[$gravity])) {
                $gravity = $mapImagickToVips[$gravity];
            } elseif (!in_array($gravity, $vipsCrop)) {
                $gravity = 'attention';
            }
            $crop = ' --crop ' . $gravity;
        } else {
            if ($imageData) {
                if ($origWidth < $constraint && $origHeight < $constraint) {
                    // Original is smaller than constraint.
                    $newWidth = $origWidth;
                    $newHeight = $origHeight;
                } elseif ($origWidth > $origHeight) {
                    // Original is paysage.
                    $newWidth = $constraint;
                    $newHeight = round($origHeight * $constraint / $origWidth);
                } elseif ($origWidth < $origHeight) {
                    // Original is portrait.
                    $newWidth = round($origWidth * $constraint / $origHeight);
                    $newHeight = $constraint;
                } else {
                    // Original is square.
                    $newWidth = $constraint;
                    $newHeight = $constraint;
                }
            } else {
                $newWidth = $constraint;
                $newHeight = $constraint;
            }
            $crop = '';
        }

        $tempFile = $this->tempFileFactory->build();
        $tempPath = $tempFile->getTempPath() . '.jpg';
        $tempPathCommand = $tempPath . '[background=255 255 255,optimize-coding]';
        $tempFile->delete();

        $command = sprintf(
            '%s thumbnail %s %s %d --height %d%s%s --size %s --linear --intent absolute',
            $this->vipsPath,
            escapeshellarg($origPath),
            escapeshellarg($tempPathCommand),
            (int) $newWidth,
            (int) $newHeight,
            $crop,
            $this->getOption('autoOrient', true) ? ' --no-rotate' : '',
            $strategy === 'square' ? 'both' : 'down'
        );

        $output = $this->cli->execute($command);
        if (false === $output) {
            throw new Exception\CannotCreateThumbnailException;
        }

        return $tempPath;
    }

    protected function createWithOldVips($strategy, $constraint, array $options = [])
    {
        // The command line vips is not pipable, so an intermediate file is
        // required when there are more than one operation.
        // So for old vips, use the basic thumbnailer currently.
        // @link https://libvips.github.io/libvips/API/current/using-cli.html
        // @see \Vips\Vips\Vips::transform()

        $origPath = $this->source;

        $crop = $strategy === 'square'
            ? ' --crop'
            : '';

        $tempFile = $this->tempFileFactory->build();
        $tempPath = $tempFile->getTempPath() . '.jpg';
        $tempFile->delete();

        $command = sprintf(
            '%sthumbnail --size=%dx%d%s --format=%s %s',
            $this->vipsPath,
            (int) $constraint,
            (int) $constraint,
            $crop,
            escapeshellarg($tempPath),
            escapeshellarg($origPath)
        );

        $output = $this->cli->execute($command);
        if (false === $output) {
            throw new Exception\CannotCreateThumbnailException;
        }

        return $tempPath;
    }

    /**
     * Set the path to the "vips" command.
     *
     * @param string $vipsDir
     */
    public function setVipsPath($vipsDir): self
    {
        if (is_null($vipsDir)) {
            $vipsPath = $this->cli->getCommandPath(self::VIPS_COMMAND);
            if (false === $vipsPath) {
                throw new Exception\InvalidThumbnailerException('Vips error: cannot determine path to vips command.');
            }
        } elseif ($vipsDir) {
            $vipsPath = $this->cli->validateCommand($vipsDir, self::VIPS_COMMAND);
            if (false === $vipsPath) {
                throw new Exception\InvalidThumbnailerException('Vips error: invalid vips command.');
            }
        } else {
            $vipsPath = false;
        }
        $this->vipsPath = $vipsPath;
        return $this;
    }

    public function setIsOldVips($isOldVips): self
    {
        $this->isOldVips = (bool) $isOldVips;
        return $this;
    }

    public function getIsOldVips(): bool
    {
        if (is_null($this->isOldVips)) {
            $version = (string) $this->cli->execute($this->vipsPath . ' --version');
            $this->isOldVips = version_compare($version, 'vips-8.6', '<');
        }
        return $this->isOldVips;
    }
}
