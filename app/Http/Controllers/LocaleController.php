<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetAppLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Switch and persist application locale strictly in session and cookie.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(SetAppLocale::SUPPORTED_LOCALES)],
        ]);

        $locale = strtolower($validated['locale']);

        $request->session()->put('locale', $locale);

        return redirect()->back()
            ->withCookie(cookie()->forever('app_locale', $locale))
            ->with('success', __('messages.locale_updated'));
    }
}
