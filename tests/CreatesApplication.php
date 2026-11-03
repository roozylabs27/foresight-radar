<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // A cached config ignores phpunit.xml, so RefreshDatabase would wipe the dev database.
        $connection = $app['config']->get('database.default');
        if ($app->configurationIsCached() || $connection !== 'sqlite' || $app['config']->get("database.connections.{$connection}.database") !== ':memory:') {
            throw new \RuntimeException('Tests must run on the in-memory sqlite database. Run `php artisan config:clear` first.');
        }

        return $app;
    }
}
