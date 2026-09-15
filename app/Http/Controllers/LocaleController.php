<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('app.available_locales', ['en' => 'English', 'ar' => 'Arabic'])))],
        ]);

        $user = $request->user();

        if ($user) {
            $user->fill(['locale' => $validated['locale']])->save();
        }

        return redirect()
            ->back()
            ->withCookie(cookie('locale', $validated['locale'], 60 * 24 * 365, path: '/'));
    }
}
