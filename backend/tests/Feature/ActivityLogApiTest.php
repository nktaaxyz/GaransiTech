<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_logout_and_data_changes_are_logged(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password-secret',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password-secret',
        ])->assertOk();

        Sanctum::actingAs($user);
        $customer = $this->postJson('/api/customers', [
            'name' => 'Budi',
            'phone' => '08123456789',
        ])->assertCreated()->json();

        $this->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseHas('activity_logs', ['action' => 'login', 'user_id' => $user->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'created',
            'auditable_type' => 'App\\Models\\Customer',
            'auditable_id' => $customer['id'],
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'logout', 'user_id' => $user->id]);

        $this->assertStringNotContainsString(
            'password-secret',
            ActivityLog::query()->get()->toJson(),
        );

        $this->assertNotEmpty($login->json('token'));
    }

    public function test_activity_log_endpoint_is_protected_and_filterable(): void
    {
        $this->getJson('/api/activity-logs')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        ActivityLog::create([
            'action' => 'created',
            'auditable_type' => 'App\\Models\\Customer',
            'auditable_id' => 1,
        ]);

        $this->getJson('/api/activity-logs?action=created')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
