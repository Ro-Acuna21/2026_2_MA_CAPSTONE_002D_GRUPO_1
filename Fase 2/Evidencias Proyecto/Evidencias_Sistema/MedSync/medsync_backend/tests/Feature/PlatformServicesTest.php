<?php

use App\Models\User;

it('keeps global services and base prices exclusive to SUPER_ADMIN', function () {
    $admin = User::create(['name' => 'Plataforma', 'email' => 'platform-services@example.test',
        'password' => 'Password123', 'system_role' => 'SUPER_ADMIN', 'is_active' => true]);
    $ordinary = User::create(['name' => 'Centro', 'email' => 'center-services@example.test',
        'password' => 'Password123', 'is_active' => true]);
    $this->actingAs($ordinary, 'sanctum')->postJson('/api/v1/platform/services', [
        'name' => 'Consulta general', 'category' => 'CONSULTATION', 'duration_minutes' => 30,
    ])->assertForbidden();
    $this->actingAs($admin, 'sanctum');
    $id = $this->postJson('/api/v1/platform/services', [
        'name' => 'Consulta general', 'category' => 'CONSULTATION', 'duration_minutes' => 30,
    ])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/platform/services/{$id}/prices", [
        'amount_clp' => 25000, 'effective_from' => today()->toDateString(),
    ])->assertCreated();
    $this->postJson("/api/v1/platform/services/{$id}/prices", [
        'amount_clp' => 30000, 'effective_from' => today()->toDateString(),
    ])->assertUnprocessable();
    $this->getJson('/api/v1/platform/services')->assertOk()
        ->assertJsonPath('data.0.base_price_clp', 25000);
    $this->getJson('/api/v1/center/platform-services')->assertForbidden();
    $this->actingAs($ordinary, 'sanctum')->getJson('/api/v1/platform/services')->assertForbidden();
});
