<?php

use App\Models\Core\CommercialInquiry;
use App\Models\Core\Plan;
use App\Models\User;

it('accepts a public commercial inquiry and shows it only to platform administration', function () {
    $plan = Plan::create(['name' => 'Profesional', 'code' => 'PRO', 'monthly_price_clp' => 89990, 'features' => ['Agenda'], 'is_active' => true]);
    $payload = ['plan_id' => $plan->id, 'contact_name' => 'Persona de Prueba', 'center_name' => 'Centro Ficticio', 'email' => 'CONTACTO@EXAMPLE.TEST', 'message' => 'Solicito información'];

    $this->postJson('/api/public/commercial-inquiries', $payload)->assertCreated();
    expect(CommercialInquiry::firstOrFail()->email)->toBe('contacto@example.test');
    $this->getJson('/api/v1/platform/commercial-inquiries')->assertUnauthorized();

    $clinical = User::create(['name' => 'Clínico', 'email' => 'clinico-inquiry@example.test', 'password' => 'Password123', 'is_active' => true]);
    $this->actingAs($clinical, 'sanctum')->getJson('/api/v1/platform/commercial-inquiries')->assertForbidden();

    $platform = User::create(['name' => 'Plataforma', 'email' => 'platform-inquiry@example.test', 'password' => 'Password123', 'system_role' => 'SUPER_ADMIN', 'is_active' => true]);
    $this->actingAs($platform, 'sanctum')->getJson('/api/v1/platform/commercial-inquiries')
        ->assertOk()->assertJsonPath('data.0.plan.name', 'Profesional')->assertJsonPath('data.0.center_name', 'Centro Ficticio');

    $plan->update(['is_active' => false]);
    $this->postJson('/api/public/commercial-inquiries', $payload)->assertUnprocessable();
});
