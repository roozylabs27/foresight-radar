<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Dimension;
use App\Models\Environment;
use App\Services\DimensionService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Dimensi (plan part B2): the organisation's own dimensions and environments. Every
 * route needs `manage-organization`; route model binding is tenant-scoped, so another
 * organisation's rows are not found.
 */
class DimensionController extends Controller
{
    public function __construct(protected DimensionService $dimensions) {}

    public function index(): Response
    {
        return Inertia::render('Settings/Dimensions', [
            'environments' => $this->dimensions->present(),
            'colors' => Dimension::COLORS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->dimensions->create($this->validated($request));

        return back()->with('success', 'Dimensi ditambahkan.');
    }

    public function update(Request $request, Dimension $dimension): RedirectResponse
    {
        $this->dimensions->update($dimension, $this->validated($request));

        return back()->with('success', 'Dimensi disimpan.');
    }

    public function move(Request $request, Dimension $dimension): RedirectResponse
    {
        $this->dimensions->move($dimension, $this->direction($request));

        return back();
    }

    public function archive(Dimension $dimension): RedirectResponse
    {
        $this->dimensions->archive($dimension, true);

        return back()->with('success', 'Dimensi diarsipkan. Data lama tetap memakainya; data baru tidak bisa memilihnya.');
    }

    public function unarchive(Dimension $dimension): RedirectResponse
    {
        $this->dimensions->archive($dimension, false);

        return back()->with('success', 'Dimensi dipakai lagi.');
    }

    public function destroy(Dimension $dimension): RedirectResponse
    {
        $this->dimensions->delete($dimension);

        return back()->with('success', 'Dimensi dihapus.');
    }

    public function storeEnvironment(Request $request): RedirectResponse
    {
        $this->dimensions->createEnvironment($request->validate(['name' => ['required', 'string', 'max:100']], [], ['name' => 'nama'])['name']);

        return back()->with('success', 'Lingkungan ditambahkan.');
    }

    public function updateEnvironment(Request $request, Environment $environment): RedirectResponse
    {
        $this->dimensions->renameEnvironment($environment, $request->validate(['name' => ['required', 'string', 'max:100']], [], ['name' => 'nama'])['name']);

        return back()->with('success', 'Lingkungan disimpan.');
    }

    public function moveEnvironment(Request $request, Environment $environment): RedirectResponse
    {
        $this->dimensions->moveEnvironment($environment, $this->direction($request));

        return back();
    }

    public function destroyEnvironment(Environment $environment): RedirectResponse
    {
        $this->dimensions->deleteEnvironment($environment);

        return back()->with('success', 'Lingkungan dihapus.');
    }

    /** @return array{name: string, environment: Environment, color: string|null} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'environment' => ['required', 'uuid', TenantRule::exists('environments', 'uuid')],
            'color' => ['nullable', Rule::in(Dimension::COLORS)],
        ], [], ['name' => 'nama', 'environment' => 'lingkungan', 'color' => 'warna']);

        return [
            'name' => $validated['name'],
            'environment' => Environment::where('uuid', $validated['environment'])->firstOrFail(),
            'color' => $validated['color'] ?? null,
        ];
    }

    private function direction(Request $request): string
    {
        return $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
    }
}
