<?php

namespace App\Http\Controllers\Companies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\SaveCompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * Display a listing of all companies.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Inertia::render('companies/index', [
            'companies' => Company::query()
                ->withCount('restaurants')
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'description']),
        ]);
    }

    /**
     * Show the form for creating a new company.
     */
    public function create(): Response
    {
        Gate::authorize('create', Company::class);

        return Inertia::render('companies/create');
    }

    /**
     * Store a newly created company.
     */
    public function store(SaveCompanyRequest $request): RedirectResponse
    {
        Gate::authorize('create', Company::class);

        $company = Company::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company created.')]);

        return to_route('companies.edit', $company);
    }

    /**
     * Show the form for editing a company.
     */
    public function edit(Company $company): Response
    {
        Gate::authorize('view', $company);

        return Inertia::render('companies/edit', [
            'company' => $company->only(['id', 'display_name', 'description']),
        ]);
    }

    /**
     * Update the specified company.
     */
    public function update(SaveCompanyRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('update', $company);

        $company->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company updated.')]);

        return to_route('companies.edit', $company);
    }
}
