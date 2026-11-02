<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * AGENTS.md §10 (roadmap R3.3): backend code uses the `Auth` facade or `$request->user()`, never the
 * global `auth()` helper, so static analysis and refactoring see every call.
 */
class AuthHelperPolicyTest extends TestCase
{
    public function test_no_backend_file_calls_the_auth_helper(): void
    {
        $offenders = [];
        foreach (['app', 'routes', 'database', 'tests'] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory), RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php' || $file->getRealPath() === __FILE__) {
                    continue;
                }
                foreach (file($file->getRealPath()) as $number => $line) {
                    // `auth()` as a function call, not `->auth()` or `Auth::`.
                    if (preg_match('/(?<![\w>$:])auth\(\)/', $line)) {
                        $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getRealPath()).':'.($number + 1);
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'Use Auth::user() / Auth::id() or $request->user() instead of auth().');
    }
}
