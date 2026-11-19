<?php

use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin']);
    $this->admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
    $this->other = User::factory()->create(['is_admin' => false]);
    Sanctum::actingAs($this->admin);
});

function notif(User $u, bool $read = false, string $title = 'T'): Notification
{
    return Notification::create([
        'user_id' => $u->id, 'title' => $title, 'message' => 'M', 'type' => 'info',
        'is_read' => $read, 'read_at' => $read ? now() : null,
    ]);
}

it('marks own notification read and unread', function () {
    $id = notif($this->admin)->id;

    $this->postJson("/api/v1/notifications/$id/read")->assertOk();
    $this->assertDatabaseHas('notifications', ['id' => $id, 'is_read' => true]);

    $this->postJson("/api/v1/notifications/$id/unread")->assertOk();
    $this->assertDatabaseHas('notifications', ['id' => $id, 'is_read' => false]);
});

it('does not let a user read, unread or delete another user\'s notification', function () {
    $id = notif($this->other)->id;
    $readId = notif($this->other, true)->id;

    $this->postJson("/api/v1/notifications/$id/read")->assertNotFound();
    $this->postJson("/api/v1/notifications/$readId/unread")->assertNotFound();
    $this->deleteJson("/api/v1/notifications/$id")->assertNotFound();

    $this->assertDatabaseHas('notifications', ['id' => $id, 'is_read' => false]);
    $this->assertDatabaseHas('notifications', ['id' => $readId, 'is_read' => true]);
});

it('deletes own notification', function () {
    $id = notif($this->admin)->id;
    $this->deleteJson("/api/v1/notifications/$id")->assertOk();
    $this->assertDatabaseMissing('notifications', ['id' => $id]);
});

it('marks all own notifications read without touching others', function () {
    notif($this->admin);
    notif($this->admin);
    $theirs = notif($this->other)->id;

    $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJson(['count' => 2]);
    expect(Notification::where('user_id', $this->admin->id)->where('is_read', false)->count())->toBe(0);
    $this->assertDatabaseHas('notifications', ['id' => $theirs, 'is_read' => false]);
});

it('marks multiple read and ignores foreign ids', function () {
    $a = notif($this->admin)->id;
    $b = notif($this->admin)->id;
    $foreign = notif($this->other)->id;

    $this->postJson('/api/v1/notifications/mark-multiple-read', [])->assertStatus(422);
    $this->postJson('/api/v1/notifications/mark-multiple-read', ['ids' => [$a, $foreign]])
        ->assertOk()->assertJson(['count' => 1]);

    $this->assertDatabaseHas('notifications', ['id' => $a, 'is_read' => true]);
    $this->assertDatabaseHas('notifications', ['id' => $b, 'is_read' => false]);
    $this->assertDatabaseHas('notifications', ['id' => $foreign, 'is_read' => false]);
});

it('deletes multiple own notifications and ignores foreign ids', function () {
    $a = notif($this->admin)->id;
    $foreign = notif($this->other)->id;

    $this->postJson('/api/v1/notifications/delete-multiple', [])->assertStatus(422);
    $this->postJson('/api/v1/notifications/delete-multiple', ['ids' => [$a, $foreign]])
        ->assertOk()->assertJson(['count' => 1]);

    $this->assertDatabaseMissing('notifications', ['id' => $a]);
    $this->assertDatabaseHas('notifications', ['id' => $foreign]);
});

it('deletes only own read notifications', function () {
    $read = notif($this->admin, true)->id;
    $unread = notif($this->admin, false)->id;
    $foreignRead = notif($this->other, true)->id;

    $this->deleteJson('/api/v1/notifications/read-all')->assertOk()->assertJson(['count' => 1]);

    $this->assertDatabaseMissing('notifications', ['id' => $read]);
    $this->assertDatabaseHas('notifications', ['id' => $unread]);
    $this->assertDatabaseHas('notifications', ['id' => $foreignRead]);
});

it('sends an in-app notification to one user', function () {
    $this->postJson('/api/v1/notifications/send', ['message' => 'x'])->assertStatus(422);
    $this->postJson('/api/v1/notifications/send', ['title' => 'a', 'message' => 'b', 'send_to' => 'user'])
        ->assertStatus(422)->assertJsonValidationErrors('user_id');

    $this->postJson('/api/v1/notifications/send', [
        'title' => 'Hello', 'message' => 'World', 'user_id' => $this->other->id, 'type' => 'warning',
    ])->assertCreated()->assertJson(['count' => 1]);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->other->id, 'title' => 'Hello', 'message' => 'World', 'type' => 'warning',
    ]);
});

it('sends to all users', function () {
    $this->postJson('/api/v1/notifications/send', ['title' => 'All', 'message' => 'hi', 'send_to' => 'all_users'])
        ->assertCreated()->assertJson(['count' => 2]);
    expect(Notification::where('title', 'All')->count())->toBe(2);
});

it('sends a bulk notification', function () {
    $third = User::factory()->create();
    $this->postJson('/api/v1/notifications/send-bulk', ['title' => 'x', 'message' => 'y'])->assertStatus(422);
    $this->postJson('/api/v1/notifications/send-bulk', ['user_ids' => [999999], 'title' => 'x', 'message' => 'y'])->assertStatus(422);

    $this->postJson('/api/v1/notifications/send-bulk', [
        'user_ids' => [$this->other->id, $third->id], 'title' => 'Bulk', 'message' => 'body',
    ])->assertCreated()->assertJson(['sent' => 2]);

    $this->assertDatabaseHas('notifications', ['user_id' => $this->other->id, 'title' => 'Bulk']);
    $this->assertDatabaseHas('notifications', ['user_id' => $third->id, 'title' => 'Bulk']);
});

it('forbids non-admins from sending notifications and managing templates', function () {
    Sanctum::actingAs($this->other);

    $this->postJson('/api/v1/notifications/send', ['title' => 'a', 'message' => 'b', 'user_id' => $this->admin->id])->assertForbidden();
    $this->postJson('/api/v1/notifications/send-bulk', ['user_ids' => [$this->admin->id], 'title' => 'a', 'message' => 'b'])->assertForbidden();
    expect(Notification::count())->toBe(0);

    $tpl = NotificationTemplate::create(['template_key' => 'k1', 'name' => 'N', 'body' => 'B', 'type' => 'email']);
    $id = $tpl->id;
    $this->putJson("/api/v1/notifications/templates/$id", ['name' => 'Hacked'])->assertForbidden();
    $this->deleteJson("/api/v1/notifications/templates/$id")->assertForbidden();
    $this->assertDatabaseHas('notification_templates', ['id' => $id, 'name' => 'N']);
});

it('updates and deletes a notification template', function () {
    $tpl = NotificationTemplate::create(['template_key' => 'k2', 'name' => 'N', 'body' => 'B', 'type' => 'email']);
    $id = $tpl->id;

    $this->putJson("/api/v1/notifications/templates/$id", ['name' => 'Renamed', 'type' => 'fax'])->assertStatus(422);
    $this->putJson("/api/v1/notifications/templates/$id", ['name' => 'Renamed', 'type' => 'sms'])->assertOk();
    $this->assertDatabaseHas('notification_templates', ['id' => $id, 'name' => 'Renamed', 'type' => 'sms']);

    $this->deleteJson("/api/v1/notifications/templates/$id")->assertOk();
    $this->assertDatabaseMissing('notification_templates', ['id' => $id]);
});
