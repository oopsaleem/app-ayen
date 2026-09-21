<?php

namespace App\Http\Controllers\Chef;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChefKitchenController extends Controller
{
    /**
     * Display the chef's assigned kitchens and their dishes.
     */
    public function index(Request $request): Response
    {
        $chef = $request->user()->chef;

        abort_unless($chef !== null, 403);

        return Inertia::render('chef/kitchens', [
            'kitchens' => $chef->kitchens()
                ->with(['dishes' => fn ($query) => $query->select('id', 'kitchen_id', 'name_en', 'name_ar', 'price')])
                ->get(['kitchens.id', 'kitchens.name_en', 'kitchens.name_ar']),
        ]);
    }
}
