<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

function adminUser(): User
{
    $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin']);

    return User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
}

it('revokes another of my sessions but not the current one', function () {
    $user = User::factory()->create();
    $current = $user->createToken('current');
    $second = $user->createToken('second');
    $secondId = $second->accessToken->id;
    $currentId = $current->accessToken->id;

    $this->withToken($current->plainTextToken)
        ->deleteJson("/api/v1/auth/sessions/$currentId")->assertStatus(422);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentId]);

    $this->withToken($current->plainTextToken)
        ->deleteJson("/api/v1/auth/sessions/$secondId")->assertOk()->assertJsonPath('success', true);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $secondId]);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentId]);

    $this->withToken($current->plainTextToken)
        ->deleteJson("/api/v1/auth/sessions/$secondId")->assertNotFound();
});

it('cannot revoke another user\'s session', function () {
    $me = User::factory()->create();
    $victim = User::factory()->create();
    $victimId = $victim->createToken('phone')->accessToken->id;
    $mine = $me->createToken('mine')->plainTextToken;

    $this->withToken($mine)->deleteJson("/api/v1/auth/sessions/$victimId")->assertNotFound();
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $victimId]);
});

it('requires authentication to revoke a session', function () {
    $this->deleteJson('/api/v1/auth/sessions/1')->assertUnauthorized();
});

it('logs out by deleting the current token only', function () {
    $user = User::factory()->create();
    $t1 = $user->createToken('one');
    $t2 = $user->createToken('two');
    $id1 = $t1->accessToken->id;
    $id2 = $t2->accessToken->id;

    $this->withToken($t1->plainTextToken)->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('success', true);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $id1]);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $id2]);
    $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => AuditLog::ACTION_LOGOUT]);
});

it('requires authentication to log out', function () {
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});

it('registers a customer and returns a working token', function () {
    $this->postJson('/api/v1/customer/auth/register', ['name' => 'A'])->assertStatus(422)
        ->assertJsonValidationErrors(['phone', 'password']);
    $this->postJson('/api/v1/customer/auth/register', ['name' => 'A', 'phone' => '0911', 'password' => '123'])
        ->assertStatus(422)->assertJsonValidationErrors('password');

    $res = $this->postJson('/api/v1/customer/auth/register', [
        'name' => 'Ali', 'phone' => '0911000111', 'email' => 'ali@example.com', 'password' => 'secret12',
    ])->assertCreated()->assertJsonPath('success', true);

    $token = $res->json('data.token');
    $c = Customer::where('phone', '0911000111')->first();
    expect($c)->not->toBeNull()
        ->and($c->auth_token)->toBe($token)
        ->and(Hash::check('secret12', $c->password))->toBeTrue()
        ->and($c->source)->toBe('customer_register');

    $this->postJson('/api/v1/customer/auth/register', ['name' => 'B', 'phone' => '0911000111', 'password' => 'secret12'])
        ->assertStatus(422)->assertJsonValidationErrors('phone');
    expect(Customer::where('phone', '0911000111')->count())->toBe(1);
});

it('logs a customer in by phone or email and rejects bad credentials', function () {
    Customer::create([
        'name' => 'Ali', 'phone' => '0922000222', 'email' => 'c@example.com',
        'password' => Hash::make('secret12'), 'auth_token' => 'tok-abc', 'status' => 'active',
    ]);

    $this->postJson('/api/v1/customer/auth/login', ['password' => 'secret12'])->assertStatus(422);
    $this->postJson('/api/v1/customer/auth/login', ['phone' => '0922000222', 'password' => 'wrong'])->assertStatus(401);
    $this->postJson('/api/v1/customer/auth/login', ['phone' => '0000', 'password' => 'secret12'])->assertStatus(401);

    $this->postJson('/api/v1/customer/auth/login', ['phone' => '0922000222', 'password' => 'secret12'])
        ->assertOk()->assertJsonPath('data.token', 'tok-abc')->assertJsonPath('data.customer.phone', '0922000222');
    $this->postJson('/api/v1/customer/auth/login', ['email' => 'c@example.com', 'password' => 'secret12'])
        ->assertOk()->assertJsonPath('data.token', 'tok-abc');

    // a customer whose token was cleared gets a fresh one on login
    Customer::where('phone', '0922000222')->update(['auth_token' => null]);
    $new = $this->postJson('/api/v1/customer/auth/login', ['phone' => '0922000222', 'password' => 'secret12'])
        ->assertOk()->json('data.token');
    expect($new)->toBeString()->toHaveLength(80);
    $this->assertDatabaseHas('customers', ['phone' => '0922000222', 'auth_token' => $new]);
});

it('logs a customer out by clearing only their token', function () {
    Customer::create(['name' => 'A', 'phone' => '1', 'password' => Hash::make('x'), 'auth_token' => 'tok-a', 'status' => 'active']);
    Customer::create(['name' => 'B', 'phone' => '2', 'password' => Hash::make('x'), 'auth_token' => 'tok-b', 'status' => 'active']);

    $this->withToken('tok-a')->postJson('/api/v1/customer/auth/logout')->assertOk();
    $this->assertDatabaseHas('customers', ['phone' => '1', 'auth_token' => null]);
    $this->assertDatabaseHas('customers', ['phone' => '2', 'auth_token' => 'tok-b']);
});

it('cleans up audit logs older than the retention window, admin only', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $old = AuditLog::create(['user_id' => $user->id, 'action' => 'update', 'description' => 'old', 'module' => 'security']);
    $new = AuditLog::create(['user_id' => $user->id, 'action' => 'update', 'description' => 'new', 'module' => 'security']);
    DB::table('audit_logs')->where('id', $old->id)->update(['created_at' => now()->subDays(200)]);
    $oldId = $old->id;
    $newId = $new->id;

    Sanctum::actingAs($user);
    $this->postJson('/api/v1/audit/cleanup', ['days' => 30])->assertForbidden();
    $this->assertDatabaseHas('audit_logs', ['id' => $oldId]);

    Sanctum::actingAs(adminUser());
    $res = $this->postJson('/api/v1/audit/cleanup', ['days' => 30])->assertOk();
    expect($res->json('deleted'))->toBeGreaterThanOrEqual(1);
    $this->assertDatabaseMissing('audit_logs', ['id' => $oldId]);
    $this->assertDatabaseHas('audit_logs', ['id' => $newId]);
});

it('sends an email verification notification to an unverified user only', function () {
    Notification::fake();
    $unverified = User::factory()->unverified()->create();
    $verified = User::factory()->create();

    $this->actingAs($unverified)->post('/email/verification-notification')
        ->assertRedirect()->assertSessionHas('status', 'verification-link-sent');
    Notification::assertSentTo($unverified, VerifyEmail::class);

    $this->actingAs($verified)->post('/email/verification-notification')->assertRedirect();
    Notification::assertNotSentTo($verified, VerifyEmail::class);
});

it('does not send verification mail to guests', function () {
    Notification::fake();
    $this->post('/email/verification-notification')->assertRedirect();
    Notification::assertNothingSent();
});
