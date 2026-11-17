<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;

it('counts distinct patient accounts and center registrations without opening tenant data', function () {
    $admin = User::create(['name' => 'Super Admin', 'email' => 'admin@example.test', 'password' => 'Password123', 'system_role' => 'SUPER_ADMIN', 'is_active' => true]);
    $patient = User::create(['name' => 'Paciente', 'email' => 'patient@example.test', 'password' => 'Password123', 'is_active' => true]);
    $single = User::create(['name' => 'Otra paciente', 'email' => 'single@example.test', 'password' => 'Password123', 'is_active' => true]);
    User::create(['name' => 'Sin centro', 'email' => 'none@example.test', 'password' => 'Password123', 'is_active' => true]);
    $staff = User::create(['name' => 'Recepción', 'email' => 'staff@example.test', 'password' => 'Password123', 'is_active' => true]);
    $first = MedicalCenter::create(['name' => 'Centro A', 'slug' => 'centro-a', 'rut' => '76543210-3', 'database_name' => 'center_a', 'is_active' => true]);
    $second = MedicalCenter::create(['name' => 'Centro B', 'slug' => 'centro-b', 'rut' => '12345678-5', 'database_name' => 'center_b', 'is_active' => false]);

    foreach ([[$first, $patient, 1], [$second, $patient, 2], [$first, $single, 3]] as [$center, $user, $patientId]) {
        CenterUser::create(['medical_center_id' => $center->id, 'user_id' => $user->id, 'role' => 'PACIENTE', 'patient_id' => $patientId, 'is_active' => true]);
    }
    CenterUser::create(['medical_center_id' => $first->id, 'user_id' => $staff->id, 'role' => 'RECEPCIONISTA', 'is_active' => true]);

    $this->actingAs($admin)->getJson('/api/v1/platform/reports/summary')->assertOk()
        ->assertJsonPath('data.total_centers', 2)
        ->assertJsonPath('data.active_centers', 1)
        ->assertJsonPath('data.total_platform_users', 5)
        ->assertJsonPath('data.unique_patients', 2)
        ->assertJsonPath('data.patient_center_registrations', 3)
        ->assertJsonPath('data.patients_in_multiple_centers', 1)
        ->assertJsonMissingPath('data.users_without_center')
        ->assertJsonPath('data.centers.0.patient_count', 2)
        ->assertJsonPath('data.centers.1.patient_count', 1);
    $this->actingAs($patient)->getJson('/api/v1/platform/reports/summary')->assertForbidden();
});
