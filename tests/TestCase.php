<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private bool $createdViteManifest = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureViteManifest();
    }

    protected function tearDown(): void
    {
        $this->cleanupViteManifest();

        parent::tearDown();
    }

    private function ensureViteManifest(): void
    {
        $manifestPath = public_path('build/manifest.json');

        if (is_file($manifestPath)) {
            return;
        }

        $buildDirectory = dirname($manifestPath);

        if (! is_dir($buildDirectory)) {
            mkdir($buildDirectory, 0777, true);
        }

        file_put_contents($manifestPath, json_encode([
            'resources/css/app.css' => [
                'file' => 'assets/app.css',
                'src' => 'resources/css/app.css',
                'isEntry' => true,
            ],
            'resources/js/app.js' => [
                'file' => 'assets/app.js',
                'src' => 'resources/js/app.js',
                'isEntry' => true,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->createdViteManifest = true;
    }

    private function cleanupViteManifest(): void
    {
        if (! $this->createdViteManifest) {
            return;
        }

        $manifestPath = public_path('build/manifest.json');

        if (is_file($manifestPath)) {
            unlink($manifestPath);
        }

        $buildDirectory = dirname($manifestPath);

        if (is_dir($buildDirectory) && count(scandir($buildDirectory)) === 2) {
            rmdir($buildDirectory);
        }
    }
}
