<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class SessionAndHeaderHardeningTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('APP_ENV=testing');
        putenv('SESSION_SECURE_COOKIE');
        unset($_ENV['APP_ENV'], $_ENV['SESSION_SECURE_COOKIE'], $_SERVER['APP_ENV'], $_SERVER['SESSION_SECURE_COOKIE']);

        parent::tearDown();
    }

    public function test_nginx_configuration_contains_required_security_headers(): void
    {
        $confPath = base_path('docker/nginx/default.conf');
        $this->assertFileExists($confPath);
        $confContent = file_get_contents($confPath);

        $this->assertStringContainsString('add_header X-Frame-Options "SAMEORIGIN" always;', $confContent);
        $this->assertStringContainsString('add_header X-Content-Type-Options "nosniff" always;', $confContent);
        $this->assertStringContainsString('add_header Referrer-Policy "strict-origin-when-cross-origin" always;', $confContent);
        $this->assertStringContainsString('add_header X-XSS-Protection "1; mode=block" always;', $confContent);
    }

    public function test_session_secure_cookie_defaults_to_production_check(): void
    {
        $sessionConfigPath = config_path('session.php');
        $this->assertFileExists($sessionConfigPath);
        $content = file_get_contents($sessionConfigPath);

        $this->assertMatchesRegularExpression(
            "/'secure'\s*=>\s*env\(\s*'SESSION_SECURE_COOKIE'\s*,\s*env\(\s*'APP_ENV'\s*\)\s*===\s*'production'\s*\)/",
            $content
        );
    }

    public function test_session_secure_cookie_behavior_in_simulated_production(): void
    {
        // When SESSION_SECURE_COOKIE is not set, but APP_ENV is production
        putenv('SESSION_SECURE_COOKIE');
        unset($_ENV['SESSION_SECURE_COOKIE'], $_SERVER['SESSION_SECURE_COOKIE']);
        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';
        $_SERVER['APP_ENV'] = 'production';
        $config = require config_path('session.php');
        $this->assertTrue($config['secure']);

        // When APP_ENV is local
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';
        $_SERVER['APP_ENV'] = 'local';
        $config = require config_path('session.php');
        $this->assertFalse($config['secure']);

        // When SESSION_SECURE_COOKIE is explicitly false even in production
        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';
        $_SERVER['APP_ENV'] = 'production';
        putenv('SESSION_SECURE_COOKIE=false');
        $_ENV['SESSION_SECURE_COOKIE'] = 'false';
        $_SERVER['SESSION_SECURE_COOKIE'] = 'false';
        $config = require config_path('session.php');
        $this->assertFalse($config['secure']);

        // When SESSION_SECURE_COOKIE is explicitly true in local
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';
        $_SERVER['APP_ENV'] = 'local';
        putenv('SESSION_SECURE_COOKIE=true');
        $_ENV['SESSION_SECURE_COOKIE'] = 'true';
        $_SERVER['SESSION_SECURE_COOKIE'] = 'true';
        $config = require config_path('session.php');
        $this->assertTrue($config['secure']);
    }

    public function test_env_example_documents_session_secure_cookie(): void
    {
        $envExamplePath = base_path('.env.example');
        $this->assertFileExists($envExamplePath);
        $content = file_get_contents($envExamplePath);

        $this->assertStringContainsString('SESSION_SECURE_COOKIE', $content);
    }
}
