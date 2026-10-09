<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class ContainerHardeningTest extends TestCase
{
    public function test_dockerfile_configures_non_root_user(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertStringContainsString('USER www-data', $dockerfile, 'Dockerfile must specify non-root USER www-data');
        $this->assertStringContainsString('chown -R www-data:www-data', $dockerfile, 'Dockerfile must ensure permissions for www-data');
    }

    public function test_docker_compose_binds_mysql_to_localhost(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));

        $this->assertStringContainsString('127.0.0.1:3306:3306', $compose, 'docker-compose.yml must restrict MySQL port mapping to 127.0.0.1');
        $this->assertStringNotContainsString('"3306:3306"', $compose, 'docker-compose.yml must not expose MySQL on 0.0.0.0');
    }
}
