<?php

use App\Models\AnalyticsMetric;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin']);
    $this->admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
    Sanctum::actingAs($this->admin);
});

function makeMetric(array $o = []): AnalyticsMetric
{
    return AnalyticsMetric::create($o + [
        'metric_key' => 'm_' . uniqid(), 'name' => 'Metric', 'category' => 'sales',
        'data_type' => 'number', 'aggregation' => 'sum',
    ]);
}

function makeDashboard(int $by): Dashboard
{
    return Dashboard::create(['name' => 'Dash', 'type' => 'custom', 'created_by' => $by]);
}

it('creates, updates and deletes a dashboard', function () {
    $this->postJson('/api/v1/analytics/dashboards', ['name' => 'Exec'])->assertStatus(422)->assertJsonValidationErrors('type');

    $id = $this->postJson('/api/v1/analytics/dashboards', ['name' => 'Exec', 'type' => 'executive'])
        ->assertCreated()->json('id');
    $this->assertDatabaseHas('dashboards', ['id' => $id, 'name' => 'Exec', 'created_by' => $this->admin->id]);

    $this->putJson("/api/v1/analytics/dashboards/$id", ['name' => 'Renamed'])->assertOk();
    $this->assertDatabaseHas('dashboards', ['id' => $id, 'name' => 'Renamed']);

    $this->putJson("/api/v1/analytics/dashboards/$id", ['type' => 'bogus'])->assertStatus(422);

    $this->deleteJson("/api/v1/analytics/dashboards/$id")->assertOk();
    expect(Dashboard::find($id))->toBeNull();
    $this->deleteJson("/api/v1/analytics/dashboards/$id")->assertNotFound();
});

it('creates, updates and deletes a metric', function () {
    $this->postJson('/api/v1/analytics/metrics', ['name' => 'x'])->assertStatus(422);

    $payload = ['metric_key' => 'rev', 'name' => 'Revenue', 'category' => 'sales', 'data_type' => 'currency', 'aggregation' => 'sum'];
    $id = $this->postJson('/api/v1/analytics/metrics', $payload)->assertCreated()->json('id');
    $this->assertDatabaseHas('analytics_metrics', ['id' => $id, 'metric_key' => 'rev']);

    $this->postJson('/api/v1/analytics/metrics', $payload)->assertStatus(422)->assertJsonValidationErrors('metric_key');

    $this->putJson("/api/v1/analytics/metrics/$id", ['name' => 'Revenue 2', 'metric_key' => 'rev'])->assertOk();
    $this->assertDatabaseHas('analytics_metrics', ['id' => $id, 'name' => 'Revenue 2']);
    $this->putJson("/api/v1/analytics/metrics/$id", ['aggregation' => 'median'])->assertStatus(422);

    $this->deleteJson("/api/v1/analytics/metrics/$id")->assertOk();
    expect(AnalyticsMetric::find($id))->toBeNull();
});

it('creates, updates, runs and deletes a report', function () {
    $this->postJson('/api/v1/analytics/reports', ['name' => 'R'])->assertStatus(422);

    $payload = ['name' => 'Sales R', 'type' => 'sales', 'format' => 'table', 'query_config' => ['a' => 1]];
    $id = $this->postJson('/api/v1/analytics/reports', $payload)->assertCreated()->json('id');
    $this->assertDatabaseHas('reports', ['id' => $id, 'name' => 'Sales R', 'created_by' => $this->admin->id]);

    $this->putJson("/api/v1/analytics/reports/$id", ['name' => 'Sales R2'])->assertOk();
    $this->assertDatabaseHas('reports', ['id' => $id, 'name' => 'Sales R2']);

    expect(Report::find($id)->last_run_at)->toBeNull();
    $this->postJson("/api/v1/analytics/reports/$id/run")->assertOk();
    expect(Report::find($id)->last_run_at)->not->toBeNull();
    $this->postJson('/api/v1/analytics/reports/999999/run')->assertNotFound();

    $this->deleteJson("/api/v1/analytics/reports/$id")->assertOk();
    expect(Report::find($id))->toBeNull();
});

it('adds, updates and deletes dashboard widgets', function () {
    $dash = makeDashboard($this->admin->id);
    $metric = makeMetric();
    $dashId = $dash->id;

    $this->postJson("/api/v1/analytics/dashboards/$dashId/widgets", ['title' => 'W'])->assertStatus(422);
    $this->postJson("/api/v1/analytics/dashboards/$dashId/widgets", [
        'widget_type' => 'number_card', 'title' => 'W', 'config' => [], 'metric_id' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('metric_id');
    $this->postJson('/api/v1/analytics/dashboards/999999/widgets', [
        'widget_type' => 'number_card', 'title' => 'W', 'config' => ['x' => 1],
    ])->assertNotFound();

    $id = $this->postJson("/api/v1/analytics/dashboards/$dashId/widgets", [
        'widget_type' => 'number_card', 'title' => 'W', 'config' => ['x' => 1], 'metric_id' => $metric->id,
    ])->assertCreated()->json('id');
    $this->assertDatabaseHas('dashboard_widgets', ['id' => $id, 'dashboard_id' => $dashId, 'title' => 'W', 'metric_id' => $metric->id]);

    $this->putJson("/api/v1/analytics/widgets/$id", ['title' => 'W2', 'width' => 4])->assertOk();
    $this->assertDatabaseHas('dashboard_widgets', ['id' => $id, 'title' => 'W2', 'width' => 4]);
    $this->putJson("/api/v1/analytics/widgets/$id", ['widget_type' => 'pie'])->assertStatus(422);

    $this->deleteJson("/api/v1/analytics/widgets/$id")->assertOk();
    expect(DashboardWidget::find($id))->toBeNull();
});

it('refuses analytics writes to a sales-scoped account', function () {
    $role = Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells']);
    $rep = User::factory()->create(['is_admin' => false, 'role_id' => $role->id]);
    Sanctum::actingAs($rep);

    $this->postJson('/api/v1/analytics/dashboards', ['name' => 'X', 'type' => 'custom'])->assertForbidden();
    expect(Dashboard::count())->toBe(0);
});
