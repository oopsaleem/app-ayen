<?php

use App\Models\Chef;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('a chef can be assigned to a kitchen with assignment metadata', function () {
    $chef = Chef::factory()->create();
    $kitchen = Kitchen::factory()->create();
    $manager = User::factory()->create();
    $assignedAt = now();

    $chef->kitchens()->attach($kitchen->id, [
        'assigned_at' => $assignedAt,
        'assigned_by' => $manager->id,
    ]);

    $pivot = $chef->fresh()->kitchens->first()->pivot;

    expect($chef->fresh()->kitchens)->toHaveCount(1)
        ->and($pivot->assigned_by)->toBe($manager->id)
        ->and($kitchen->fresh()->chefs)->toHaveCount(1);
});

test('deleting a chef removes their kitchen assignments', function () {
    $chef = Chef::factory()->create();
    $kitchen = Kitchen::factory()->create();

    $chef->kitchens()->attach($kitchen->id, ['assigned_at' => now()]);

    $chef->delete();

    expect(DB::table('chef_kitchen')->count())->toBe(0);
});

test('deleting a kitchen removes its chef assignments', function () {
    $chef = Chef::factory()->create();
    $kitchen = Kitchen::factory()->create();

    $chef->kitchens()->attach($kitchen->id, ['assigned_at' => now()]);

    $kitchen->delete();

    expect(DB::table('chef_kitchen')->count())->toBe(0);
});
