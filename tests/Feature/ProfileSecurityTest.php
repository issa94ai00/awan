<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The admin profile page: changing the password and managing the devices
 * (Sanctum tokens) the account is signed in on.
 */

function signedInUser(): array
{
    $user = User::factory()->create(['password' => Hash::make('secret123')]);
    $current = $user->createToken('Current browser')->plainTextToken;
    $other = $user->createToken('Phone app');

    return [$user, $current, $other];
}

it('answers a wrong current password with 422, not a session-ending 401', function () {
    [, $token] = signedInUser();

    $this->withToken($token)
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => 'not-it',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('current_password');
});

it('refuses a new password equal to the current one', function () {
    [, $token] = signedInUser();

    $this->withToken($token)
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => 'secret123',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

it('keeps the current device signed in and signs out the rest after a password change', function () {
    [$user, $token, $other] = signedInUser();

    $this->withToken($token)
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => 'secret123',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])
        ->assertOk();

    expect(Hash::check('newsecret123', $user->fresh()->password))->toBeTrue();
    expect($user->tokens()->pluck('name')->all())->toBe(['Current browser']);
    expect($user->tokens()->whereKey($other->accessToken->id)->exists())->toBeFalse();
});

it('lists the signed-in devices with the current one first', function () {
    [, $token] = signedInUser();

    $this->withToken($token)
        ->getJson('/api/v1/auth/sessions')
        ->assertOk()
        ->assertJsonCount(2, 'data.sessions')
        ->assertJsonPath('data.sessions.0.device', 'Current browser')
        ->assertJsonPath('data.sessions.0.is_current', true)
        ->assertJsonPath('data.sessions.1.is_current', false);
});

it('signs out a single other device', function () {
    [$user, $token, $other] = signedInUser();

    $this->withToken($token)
        ->deleteJson('/api/v1/auth/sessions/' . $other->accessToken->id)
        ->assertOk();

    expect($user->tokens()->count())->toBe(1);
});

it('will not revoke the current session or another user\'s session', function () {
    [$user, $token] = signedInUser();
    $currentId = $user->tokens()->where('name', 'Current browser')->value('id');
    $stranger = User::factory()->create()->createToken('Theirs');

    $this->withToken($token)
        ->deleteJson('/api/v1/auth/sessions/' . $currentId)
        ->assertStatus(422);

    $this->withToken($token)
        ->deleteJson('/api/v1/auth/sessions/' . $stranger->accessToken->id)
        ->assertNotFound();

    expect($stranger->accessToken->fresh())->not->toBeNull();
});

it('signs out every other device at once', function () {
    [$user, $token] = signedInUser();
    $user->createToken('Tablet');

    $this->withToken($token)
        ->deleteJson('/api/v1/auth/sessions')
        ->assertOk()
        ->assertJsonPath('data.revoked', 2);

    expect($user->tokens()->pluck('name')->all())->toBe(['Current browser']);
});
