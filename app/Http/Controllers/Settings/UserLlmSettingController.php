<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateUserLlmSettingRequest;
use App\Http\Requests\SaveUserLlmSettingRequest;
use App\Http\Requests\TestUserLlmSettingRequest;
use App\Services\Llm\UserLlmSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class UserLlmSettingController extends Controller
{
    use HandlesApiErrors;

    public function __construct(
        protected UserLlmSettingService $settingService,
    ) {}

    /**
     * Render the LLM settings page with current user configurations.
     */
    public function index(Request $request): Response
    {
        $providers = $this->settingService->getProvidersCatalog($request->user());

        return Inertia::render('Settings/LlmSettings', [
            'providers' => $providers,
            'title' => 'LLM Configuration',
        ]);
    }

    /**
     * Store or update provider settings for the authenticated user.
     */
    public function store(SaveUserLlmSettingRequest $request): RedirectResponse
    {
        try {
            $this->settingService->saveProvider($request->user(), $request->validated());

            return back()->with('success', 'Provider configuration saved.');
        } catch (\Throwable $th) {
            return back()->withErrors(['message' => 'Failed to save configuration: '.$th->getMessage()]);
        }
    }

    /**
     * Activate or deactivate a provider for the authenticated user.
     */
    public function activate(ActivateUserLlmSettingRequest $request): RedirectResponse
    {
        $provider = $request->validated('provider');
        $shouldActivate = $request->boolean('active', true);

        try {
            if ($shouldActivate) {
                $this->settingService->activateProvider($request->user(), $provider);
                $message = 'Provider activated as primary LLM.';
            } else {
                $this->settingService->deactivateProvider($request->user(), $provider);
                $message = 'Provider deactivated. Falling back to system default.';
            }

            return back()->with('success', $message);
        } catch (\Throwable $th) {
            return back()->withErrors(['message' => $th->getMessage()]);
        }
    }

    /**
     * Live test connection for candidate or stored credentials.
     */
    public function test(TestUserLlmSettingRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // If API key is not provided in test payload, use stored key if present
            if (empty($data['api_key'])) {
                $existing = $request->user()?->llmSettings()->where('provider', $data['provider'])->first();
                if ($existing && ! empty($existing->api_key)) {
                    $data['api_key'] = $existing->api_key;
                }
            }

            $result = $this->settingService->testConnection($data);

            return response()->json($result, $result['success'] ? HttpResponse::HTTP_OK : HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: '.$th->getMessage(),
            ], HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reset and delete configuration for a specific provider.
     */
    public function destroy(string $provider, Request $request): RedirectResponse
    {
        try {
            $this->settingService->deleteProvider($request->user(), $provider);

            return back()->with('success', 'Provider configuration reset.');
        } catch (\Throwable $th) {
            return back()->withErrors(['message' => 'Failed to reset configuration: '.$th->getMessage()]);
        }
    }
}
