<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Determine whether the user can view the company.
     */
    public function view(User $user, Company $company): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $company->id;
    }

    /**
     * Determine whether the user can create companies.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the company.
     */
    public function update(User $user, Company $company): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $company->id;
    }

    /**
     * Determine whether the user can delete the company.
     */
    public function delete(User $user, Company $company): bool
    {
        return $user->isAdmin();
    }
}
