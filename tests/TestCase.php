<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;

abstract class TestCase extends BaseTestCase
{
    protected static ?string $passportKeyPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        //
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        //
    }

    /**
     * Generate throwaway Passport signing keys (once per test run) so real tokens can be issued and verified.
     */
    protected function usePassportKeys(): void
    {
        if (static::$passportKeyPath === null) {
            $path = sys_get_temp_dir().'/investbrain-passport-keys-'.getmypid();

            if (! is_dir($path)) {
                mkdir($path, 0700, true);
            }

            Passport::loadKeysFrom($path);
            Artisan::call('passport:keys', ['--force' => true]);

            static::$passportKeyPath = $path;

            register_shutdown_function(static function () use ($path): void {
                array_map('unlink', glob($path.'/*.key') ?: []);
                @rmdir($path);
            });
        }

        Passport::loadKeysFrom(static::$passportKeyPath);
    }
}
