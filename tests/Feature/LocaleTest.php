<?php

use App\Models\User;

it('stores the locale for guests in a cookie', function () {
    $response = $this->post(route('locale.update'), ['locale' => 'ar']);

    $response->assertRedirect();
    $response->assertCookie('locale', 'ar', false);
    expect(app()->getLocale())->toBe('en');
});

it('persists the locale for authenticated users', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($user)->post(route('locale.update'), ['locale' => 'ar']);

    $response->assertRedirect();
    $response->assertCookie('locale', 'ar', false);
    expect($user->fresh()->locale)->toBe('ar');
});

it('rejects unsupported locales', function () {
    $response = $this->post(route('locale.update'), ['locale' => 'fr']);

    $response->assertSessionHasErrors('locale');
});

it('sets the application locale from the user preference', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $this->actingAs($user)->get(route('home'));

    expect(app()->getLocale())->toBe('ar');
});

it('translates validation messages for the user preferred locale', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $this->actingAs($user)->get(route('home'));

    expect(__('validation.required', ['attribute' => __('validation.attributes.password')]))
        ->toBe('حقل كلمة المرور مطلوب.');
});

it('translates authentication failure messages for the user preferred locale', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $this->actingAs($user)->get(route('home'));

    expect(__('auth.failed'))->toBe('بيانات الاعتماد هذه غير متطابقة مع سجلاتنا.');
});
