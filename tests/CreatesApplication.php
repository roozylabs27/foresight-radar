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
        if ($app->configurationIsCached()) {
            throw new \RuntimeException('Tests cannot run with cached configuration. Run `php artisan config:clear` first.');
        }

        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        $isSafe = ($connection === 'sqlite' && ($database === ':memory:' || str_contains($database, 'test')))
            || in_array($database, ['testing', 'test'], true)
            || str_ends_with($database, '_test')
            || str_ends_with($database, '_testing');

        if (! $isSafe) {
            throw new \RuntimeException("Tests must run on an in-memory SQLite database or a dedicated testing database (e.g. 'testing'). Detected {$connection}:{$database}. Run `php artisan config:clear` first.");
        }

        return $app;
    }
}
