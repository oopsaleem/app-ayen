<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

dataset('complexityLevels', [
    'basic accepts plain 8 chars' => ['basic', 'password', true],
    'basic rejects short' => ['basic', 'short', false],
    'medium rejects letters only' => ['medium', 'passwords', false],
    'medium accepts letters and numbers' => ['medium', 'passw0rds', true],
    'strict rejects no symbols' => ['strict', 'Passw0rdsAreFun', false],
]);

test('password complexity follows auth.password_complexity', function (string $level, string $password, bool $passes) {
    config(['auth.password_complexity' => $level]);

    $validator = Validator::make(['password' => $password], ['password' => [Password::default()]]);

    expect($validator->passes())->toBe($passes);
})->with('complexityLevels');

test('register and reset password pages share the active password rules', function () {
    config(['auth.password_complexity' => 'strict']);

    $expected = 'minlength: 12; required: lower; required: upper; required: digit; required: special;';

    $this->get(route('register'))
        ->assertInertia(fn ($page) => $page->where('passwordRules', $expected));

    $this->get(route('password.reset', ['token' => 'token']))
        ->assertInertia(fn ($page) => $page->where('passwordRules', $expected));
});
