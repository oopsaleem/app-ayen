<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class AdminSetupController extends Controller
{
    /**
     * Show the form for creating an Admin account.
     */
    public function create(): Response
    {
        return Inertia::render('setup/admin');
    }

    /**
     * Create a new user and grant it the Admin role.
     *
     * Only logs the new account in when the actor was a guest (the
     * first-run bootstrap case) — an already-authenticated Admin adding
     * another Admin stays logged in as themselves.
     */
    public function store(Request $request, CreatesNewUsers $creator, StatefulGuard $guard): RedirectResponse
    {
        $actingAsGuest = ! $request->user();

        $user = $creator->create($request->all());

        Admin::create(['user_id' => $user->id]);

        event(new Registered($user));

        if ($actingAsGuest) {
            $guard->login($user);

            $request->session()->regenerate();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Admin account created.')]);

        return to_route('companies.index');
    }
}
