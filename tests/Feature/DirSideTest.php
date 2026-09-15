<?php

use App\Models\User;

test('sidebar side reflects locale', function () {
    $user = User::factory()->create(['locale' => 'en']);
    $team = $user->currentTeam;

    $response = $this->actingAs($user)->get('/'.$team->slug.'/dashboard');

    $body = $response->getContent();
    preg_match_all('/data-side="[a-z]+"/', $body, $m);
    preg_match('/<html[^>]*dir="([^"]+)"/i', $body, $dir);

    expect(array_unique($m[0]))->toBe(['data-side="left"']);
    expect($dir[1])->toBe('ltr');
    $response->assertOk();
});

test('sidebar flips to the right side for rtl locales', function () {
    $user = User::factory()->create(['locale' => 'ar']);
    $team = $user->currentTeam;

    $response = $this->actingAs($user)->get('/'.$team->slug.'/dashboard');

    $body = $response->getContent();
    preg_match_all('/data-side="[a-z]+"/', $body, $m);
    preg_match('/<html[^>]*dir="([^"]+)"/i', $body, $dir);

    expect(array_unique($m[0]))->toBe(['data-side="right"']);
    expect($dir[1])->toBe('rtl');
    $response->assertOk();
});

test('switching locale via the endpoint updates user preference and flips direction', function () {
    $user = User::factory()->create(['locale' => 'ar']);
    $team = $user->currentTeam;

    $this
        ->actingAs($user)
        ->post('/locale', ['locale' => 'en'])
        ->assertRedirect();

    $response = $this->actingAs($user)->get('/'.$team->slug.'/dashboard');

    $body = $response->getContent();
    preg_match_all('/data-side="[a-z]+"/', $body, $m);
    preg_match('/<html[^>]*dir="([^"]+)"/i', $body, $dir);

    expect($user->fresh()->locale)->toBe('en');
    expect(array_unique($m[0]))->toBe(['data-side="left"']);
    expect($dir[1])->toBe('ltr');
    $response->assertOk();
});
