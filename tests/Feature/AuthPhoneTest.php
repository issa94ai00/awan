<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * The two auth paths that a missing column had disabled outright.
 *
 * `AuthController` wrote `phone` on every registration and read it back with
 * `User::where('phone', …)` on sign-in, but `users` had no such column. So
 * registration answered 500 to every caller and phone sign-in — the field app's
 * normal way in — could never succeed. Neither failure was visible in the test
 * suite, because SQLite tolerates in a WHERE clause what MySQL rejects.
 */

it('has the phone column the auth controller reads and writes', function () {
    expect(Schema::hasColumn('users', 'phone'))->toBeTrue();
});

it('registers an account', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'عيسى',
        'email' => 'issa@example.test',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])
        ->assertCreated()
        ->assertJsonPath('success', true);

    expect(User::where('email', 'issa@example.test')->exists())->toBeTrue();
});

it('registers an account with a phone number and keeps it', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'مندوب',
        'email' => 'rep@example.test',
        'phone' => '0999111222',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertCreated();

    // The whole point: the number survives the insert instead of being dropped.
    expect(User::where('email', 'rep@example.test')->value('phone'))->toBe('0999111222');
});

it('signs in with a phone number', function () {
    User::create([
        'name' => 'مندوب',
        'email' => 'rep2@example.test',
        'phone' => '0988777666',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'phone' => '0988777666',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token']]);
});

it('still signs in with an email address', function () {
    User::create([
        'name' => 'مدير',
        'email' => 'admin@example.test',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'secret123',
    ])->assertOk();
});

it('refuses a wrong password on the phone path', function () {
    User::create([
        'name' => 'مندوب',
        'email' => 'rep3@example.test',
        'phone' => '0911222333',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'phone' => '0911222333',
        'password' => 'wrong-password',
    ])->assertStatus(401);
});

/**
 * Sign-in resolves the account with `->first()`, so a shared number would make
 * the answer depend on which row was inserted first — an authentication
 * decision nobody made.
 */
it('refuses to register a phone number that is already taken', function () {
    User::create([
        'name' => 'الأول',
        'email' => 'first@example.test',
        'phone' => '0900000001',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'الثاني',
        'email' => 'second@example.test',
        'phone' => '0900000001',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.phone.0', 'رقم الهاتف مستخدم بالفعل');
});

it('lets many accounts exist without a phone number', function () {
    foreach (['a', 'b', 'c'] as $name) {
        User::create([
            'name' => $name,
            'email' => "{$name}@example.test",
            'password' => Hash::make('secret123'),
        ]);
    }

    // A unique index must still permit repeated NULLs.
    expect(User::whereNull('phone')->count())->toBe(3);
});

it('lets a user save their profile without reporting their own number as taken', function () {
    $user = User::create([
        'name' => 'مندوب',
        'email' => 'keep@example.test',
        'phone' => '0955444333',
        'password' => Hash::make('secret123'),
    ]);

    $this->actingAs($user)
        ->putJson('/api/v1/auth/profile', [
            'name' => 'مندوب معدَّل',
            'phone' => '0955444333',
        ])
        ->assertOk();
});
