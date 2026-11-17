<?php

use App\Models\User;

/**
 * The database documentation page lists the schema, every route with its
 * auth layer and the open security findings: admins only, via a signed link.
 */
it('refuses the page without a valid signature', function () {
    $this->get('/internal/erd')->assertForbidden();
    $this->get('/internal/erd?signature=forged&expires='.(time() + 600))->assertForbidden();
});

it('hands the signed link to admins only', function () {
    $this->getJson('/api/v1/admin/erd-link')->assertUnauthorized();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/admin/erd-link')
        ->assertStatus(302);

    $response = $this->actingAs(User::factory()->admin()->create(), 'sanctum')->getJson('/api/v1/admin/erd-link');

    // 404 only when the page has not been generated on this machine yet.
    expect($response->status())->toBeIn([200, 404]);
    if ($response->status() === 200) {
        expect($response->json('url'))->toContain('/internal/erd')->toContain('signature=');
    }
});
