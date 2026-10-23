<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Models\UserLlmSetting;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\OpenAiCompatibleClient;
use App\Services\Llm\StructuredLlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserLlmSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_llm_settings_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('settings.llm.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/LlmSettings')
            ->has('providers', 4)
            ->where('providers.0.provider', 'anthropic')
            ->where('providers.1.provider', 'openai')
            ->where('providers.2.provider', 'gemini')
            ->where('providers.3.provider', 'openai_compatible')
        );
    }

    public function test_user_can_save_encrypted_api_key_and_model(): void
    {
        $user = User::factory()->create();
        $rawKey = 'AIzaSyTestKey1234567890abcdef';

        $response = $this->actingAs($user)->post(route('settings.llm.store'), [
            'provider' => 'gemini',
            'model' => 'gemini-3.8-flash',
            'api_key' => $rawKey,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_llm_settings', [
            'user_id' => $user->id,
            'provider' => 'gemini',
            'model' => 'gemini-3.8-flash',
        ]);

        // Verify key is encrypted at rest in the database
        $rawDbRow = DB::table('user_llm_settings')
            ->where('user_id', $user->id)
            ->where('provider', 'gemini')
            ->first();

        $this->assertNotNull($rawDbRow);
        $this->assertNotEquals($rawKey, $rawDbRow->api_key, 'Raw database column must be encrypted');

        // Verify Eloquent model decrypts it transparently
        $setting = UserLlmSetting::where('user_id', $user->id)->where('provider', 'gemini')->first();
        $this->assertSame($rawKey, $setting->api_key);
        $this->assertStringContainsString('••••••••', $setting->masked_api_key);
    }

    public function test_only_one_provider_can_be_active_per_user(): void
    {
        $user = User::factory()->create();

        // 1. Setup Anthropic
        $this->actingAs($user)->post(route('settings.llm.store'), [
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-5-5',
            'api_key' => 'sk-ant-test-key-12345678',
        ]);
        $this->actingAs($user)->post(route('settings.llm.activate'), [
            'provider' => 'anthropic',
            'active' => true,
        ]);

        $anthropic = UserLlmSetting::where('user_id', $user->id)->where('provider', 'anthropic')->first();
        $this->assertTrue($anthropic->is_active);

        // 2. Setup Gemini and activate
        $this->actingAs($user)->post(route('settings.llm.store'), [
            'provider' => 'gemini',
            'model' => 'gemini-3.8-flash',
            'api_key' => 'AIzaSyGeminiKey12345678',
        ]);
        $this->actingAs($user)->post(route('settings.llm.activate'), [
            'provider' => 'gemini',
            'active' => true,
        ]);

        // Gemini should now be active, while Anthropic must be deactivated
        $gemini = UserLlmSetting::where('user_id', $user->id)->where('provider', 'gemini')->first();
        $anthropic->refresh();

        $this->assertTrue($gemini->is_active);
        $this->assertFalse($anthropic->is_active);

        // Active count for this user must be exactly 1
        $activeCount = UserLlmSetting::where('user_id', $user->id)->where('is_active', true)->count();
        $this->assertSame(1, $activeCount);
    }

    public function test_activating_provider_without_api_key_fails(): void
    {
        $user = User::factory()->create();

        // Save setting without API key
        $this->actingAs($user)->post(route('settings.llm.store'), [
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ]);

        $response = $this->actingAs($user)->post(route('settings.llm.activate'), [
            'provider' => 'openai',
            'active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['message']);

        $setting = UserLlmSetting::where('user_id', $user->id)->where('provider', 'openai')->first();
        $this->assertFalse($setting->is_active);
    }

    public function test_resolver_uses_active_user_provider_and_falls_back_when_none_active(): void
    {
        $user = User::factory()->create();
        $resolver = app(LlmClientResolver::class);

        // When user has no active personal setting, resolver falls back to container default
        $defaultClient = $resolver->resolveForUser($user);
        $this->assertInstanceOf(StructuredLlmClient::class, $defaultClient);

        // When user configures and activates Gemini
        UserLlmSetting::create([
            'user_id' => $user->id,
            'provider' => 'gemini',
            'label' => 'Google Gemini',
            'model' => 'gemini-3.8-flash',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai/',
            'api_key' => 'AIzaSyTestValidKey12345',
            'is_active' => true,
        ]);

        $resolvedClient = $resolver->resolveForUser($user);
        $this->assertInstanceOf(OpenAiCompatibleClient::class, $resolvedClient);
        $this->assertSame('gemini-3.8-flash', $resolvedClient->model());
        $this->assertSame('gemini', $resolvedClient->provider());
    }

    public function test_api_key_is_masked_in_inertia_props(): void
    {
        $user = User::factory()->create();
        $rawKey = 'AIzaSy1234567890abcd';

        UserLlmSetting::create([
            'user_id' => $user->id,
            'provider' => 'gemini',
            'label' => 'Google Gemini',
            'model' => 'gemini-3.8-flash',
            'api_key' => $rawKey,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('settings.llm.index'));
        $response->assertOk();

        $page = $response->viewData('page');
        $providers = $page['props']['providers'];
        $gemini = collect($providers)->firstWhere('provider', 'gemini');

        $this->assertTrue($gemini['is_configured']);
        $this->assertTrue($gemini['is_active']);
        $this->assertStringContainsString('••••••••', $gemini['masked_api_key']);
        $this->assertArrayNotHasKey('api_key', $gemini);
        $this->assertStringNotContainsString($rawKey, json_encode($page));
    }

    public function test_user_can_reset_provider_configuration(): void
    {
        $user = User::factory()->create();

        UserLlmSetting::create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'label' => 'Claude',
            'model' => 'claude-sonnet-5-5',
            'api_key' => 'sk-ant-test-key',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->delete(route('settings.llm.destroy', ['provider' => 'anthropic']));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('user_llm_settings', [
            'user_id' => $user->id,
            'provider' => 'anthropic',
        ]);
    }
}
