<?php declare(strict_types=1);

namespace VipsTest;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\File\TempFile;

/**
 * Shared test helpers for Vips module tests.
 */
trait VipsTestTrait
{
    /**
     * @var bool Whether admin is logged in.
     */
    protected bool $isLoggedIn = false;

    /**
     * Get the service locator.
     */
    protected function getServiceLocator(): ServiceLocatorInterface
    {
        if (isset($this->application) && $this->application !== null) {
            return $this->application->getServiceManager();
        }
        return $this->getApplication()->getServiceManager();
    }

    /**
     * Login as admin user.
     */
    protected function loginAdmin(): void
    {
        $this->isLoggedIn = true;
        $this->ensureLoggedIn();
    }

    /**
     * Ensure admin is logged in on the current application instance.
     */
    protected function ensureLoggedIn(): void
    {
        $services = $this->getServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');

        if ($auth->hasIdentity()) {
            return;
        }

        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    /**
     * Logout current user.
     */
    protected function logout(): void
    {
        $this->isLoggedIn = false;
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    /**
     * Get the path to the fixtures directory.
     */
    protected function getFixturesPath(): string
    {
        return dirname(__DIR__) . '/VipsTest/fixtures';
    }

    /**
     * Get a fixture file path.
     */
    protected function getFixturePath(string $name): string
    {
        $path = $this->getFixturesPath() . '/' . $name;
        if (!file_exists($path)) {
            throw new \RuntimeException("Fixture not found: $path");
        }
        return $path;
    }

    /**
     * Check if the vips CLI command is available.
     */
    protected function hasVipsCli(): bool
    {
        $cli = $this->getServiceLocator()->get('Omeka\Cli');
        return (bool) $cli->getCommandPath('vips');
    }

    /**
     * Check if a PHP vips extension is loaded (ext-vips or ext-ffi).
     */
    protected function hasVipsExtension(): bool
    {
        return extension_loaded('vips') || extension_loaded('ffi');
    }

    /**
     * Check if a PHP vips extension AND the jcupitt/vips library are both
     * available (required for PHP extension mode).
     *
     * v1 requires ext-vips, v2 requires ext-ffi.
     */
    protected function hasVipsLibrary(): bool
    {
        return (extension_loaded('vips') || extension_loaded('ffi'))
            && class_exists(\Jcupitt\Vips\Image::class);
    }

    /**
     * Get a temporary file path for test output.
     */
    protected function getTempPath(string $extension = 'jpg'): string
    {
        return tempnam(sys_get_temp_dir(), 'vips_test_') . '.' . $extension;
    }

    /**
     * @var string[] Temporary files to clean up.
     */
    protected array $tempFiles = [];

    /**
     * Register a temporary file for cleanup.
     */
    protected function registerTempFile(string $path): void
    {
        $this->tempFiles[] = $path;
    }

    /**
     * Clean up temporary files.
     */
    protected function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    /**
     * Create a TempFile from a fixture file.
     *
     * Copies the fixture content into a real TempFile object that can be
     * passed to AbstractThumbnailer::setSource().
     */
    protected function createTempFileFromFixture(string $fixtureName): TempFile
    {
        $fixturePath = $this->getFixturePath($fixtureName);
        $factory = $this->getServiceLocator()->get('Omeka\File\TempFileFactory');
        $tempFile = $factory->build();
        copy($fixturePath, $tempFile->getTempPath());
        $this->tempFiles[] = $tempFile->getTempPath();
        return $tempFile;
    }
}
