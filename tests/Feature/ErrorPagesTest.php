<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_existent_page_renders_custom_blade_404(): void
    {
        $response = $this->get('/non-existent-page-url-that-does-not-exist');

        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee('Halaman Tidak Ditemukan');
        $response->assertSee('Foresight Radar');
    }

    private function inertiaHeaders(): array
    {
        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return array_filter([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ]);
    }

    public function test_non_existent_page_via_inertia_renders_inertia_error_component(): void
    {
        $response = $this->withHeaders($this->inertiaHeaders())
            ->get('/non-existent-page-url-that-does-not-exist');

        $response->assertStatus(404);
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 404,
            ],
        ]);
    }

    public function test_unauthorized_page_renders_custom_blade_403_for_unprivileged_user(): void
    {
        $user = User::factory()->create();

        // signals page requires view-signal permission; user doesn't have it
        $response = $this->actingAs($user)->get('/signals');

        $response->assertStatus(403);
        $response->assertSee('403');
        $response->assertSee('Akses Ditolak');
        $response->assertSee('Foresight Radar');
    }

    public function test_unauthorized_page_via_inertia_renders_inertia_error_component(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders($this->inertiaHeaders())
            ->get('/signals');

        $response->assertStatus(403);
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJson([
            'component' => 'Error',
            'props' => [
                'status' => 403,
            ],
        ]);
    }

    public function test_json_api_request_does_not_render_html_error_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/signals/fetch');

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'This action is unauthorized.',
        ]);
    }
}
