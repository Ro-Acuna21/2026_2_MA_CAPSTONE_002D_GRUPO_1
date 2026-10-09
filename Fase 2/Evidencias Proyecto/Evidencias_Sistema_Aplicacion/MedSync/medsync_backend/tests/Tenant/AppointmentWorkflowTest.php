<?php

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function prepareAppointmentWorkflowDatabase(string $connection, string $path): void
{
    config(["database.connections.{$connection}" => array_merge(
        config('database.connections.center'),
        ['driver' => 'sqlite', 'url' => null, 'database' => $path],
    )]);
    DB::purge($connection);

    Schema::connection($connection)->create('patients', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('first_name');
        $table->string('last_name');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::connection($connection)->create('specialties', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    Schema::connection($connection)->create('professionals', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('first_name');
        $table->string('last_name');
        $table->string('rut');
        $table->string('email');
        $table->string('phone')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::connection($connection)->create('professional_specialty', function (Blueprint $table) {
        $table->unsignedBigInteger('professional_id');
        $table->unsignedBigInteger('specialty_id');
        $table->timestamps();
    });
    Schema::connection($connection)->create('services', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('specialty_id');
        $table->string('name');
        $table->text('description')->nullable();
        $table->smallInteger('duration_minutes');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    Schema::connection($connection)->create('availabilities', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('professional_id');
        $table->smallInteger('weekday');
        $table->time('start_time');
        $table->time('end_time');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    Schema::connection($connection)->create('appointments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('patient_id');
        $table->unsignedBigInteger('professional_id');
        $table->unsignedBigInteger('service_id');
        $table->date('appointment_date');
        $table->time('start_time');
        $table->time('end_time');
        $table->string('status');
        $table->string('source');
        $table->text('note')->nullable();
        $table->boolean('overbook')->default(false);
        $table->unsignedBigInteger('created_by');
        $table->timestamps();
    });
    Schema::connection($connection)->create('appointment_history', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('appointment_id');
        $table->unsignedBigInteger('actor_user_id')->nullable();
        $table->string('event_type');
        $table->string('previous_status')->nullable();
        $table->string('new_status')->nullable();
        $table->date('old_date')->nullable();
        $table->time('old_start_time')->nullable();
        $table->time('old_end_time')->nullable();
        $table->date('new_date')->nullable();
        $table->time('new_start_time')->nullable();
        $table->time('new_end_time')->nullable();
        $table->unsignedBigInteger('old_professional_id')->nullable();
        $table->unsignedBigInteger('new_professional_id')->nullable();
        $table->unsignedBigInteger('old_service_id')->nullable();
        $table->unsignedBigInteger('new_service_id')->nullable();
        $table->text('reason')->nullable();
        $table->timestamp('created_at')->useCurrent();
    });

    $now = now();
    DB::connection($connection)->table('patients')->insert([
        'id' => 1, 'first_name' => 'Roberto', 'last_name' => 'Acuña',
        'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::connection($connection)->table('specialties')->insert([
        'id' => 1, 'name' => 'Medicina general', 'is_active' => true,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::connection($connection)->table('professionals')->insert([
        ['id' => 1, 'first_name' => 'Profesional', 'last_name' => 'A', 'rut' => '11111111-1', 'email' => 'a@example.test', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 2, 'first_name' => 'Profesional', 'last_name' => 'B', 'rut' => '22222222-2', 'email' => 'b@example.test', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
    ]);
    DB::connection($connection)->table('professional_specialty')->insert([
        ['professional_id' => 1, 'specialty_id' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['professional_id' => 2, 'specialty_id' => 1, 'created_at' => $now, 'updated_at' => $now],
    ]);
    DB::connection($connection)->table('services')->insert([
        'id' => 1, 'specialty_id' => 1, 'name' => 'Consulta',
        'duration_minutes' => 30, 'is_active' => true,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::connection($connection)->table('availabilities')->insert([
        'id' => 1, 'professional_id' => 2, 'weekday' => 1,
        'start_time' => '09:00:00', 'end_time' => '13:00:00',
        'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::connection($connection)->table('availabilities')->insert([
        'id' => 2, 'professional_id' => 1, 'weekday' => 1,
        'start_time' => '09:00:00', 'end_time' => '13:00:00',
        'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::connection($connection)->table('appointments')->insert([
        'id' => 19, 'patient_id' => 1, 'professional_id' => 1, 'service_id' => 1,
        'appointment_date' => '2026-10-12', 'start_time' => '09:00:00',
        'end_time' => '09:30:00', 'status' => 'PENDIENTE', 'source' => 'WEB',
        'overbook' => false, 'created_by' => 1,
        'created_at' => $now, 'updated_at' => $now,
    ]);
}

function appointmentWorkflowClient(
    object $test,
    string $databasePath,
    string $slug,
    string $role,
    ?int $patientId = null,
    ?int $professionalId = null
): array {
    $center = MedicalCenter::create([
        'name' => 'Centro de prueba', 'slug' => $slug,
        'database_name' => $databasePath, 'rut' => '76543210-3', 'is_active' => true,
    ]);
    $user = User::create([
        'name' => 'Usuario de prueba', 'email' => $slug.'@example.test',
        'password' => 'Password123', 'is_active' => true,
    ]);
    CenterUser::create([
        'medical_center_id' => $center->id, 'user_id' => $user->id,
        'role' => $role, 'patient_id' => $patientId,
        'professional_id' => $professionalId, 'is_active' => true,
    ]);

    return [$center, $user, $test->withHeader('Origin', 'http://localhost:3000')
        ->withSession(['active_medical_center_id' => $center->id])
        ->actingAs($user, 'sanctum')];
}

it('reasigna una cita de recepción actualizando la misma reserva y conservando el historial', function () {
    $path = tempnam(sys_get_temp_dir(), 'appointment-reassignment-');
    try {
        prepareAppointmentWorkflowDatabase('center', $path);
        [$center, , $client] = appointmentWorkflowClient($this, $path, 'appointment-reassignment', 'RECEPCIONISTA');

        $client->patchJson('/api/v1/appointments/19/reschedule', [
            'professional_id' => 2,
            'reassignment_reason' => 'Profesional A está con licencia médica.',
            'appointment_date' => '2026-10-12',
            'start_time' => '10:00',
        ])->assertOk()
            ->assertJsonPath('data.id', 19)
            ->assertJsonPath('data.professional.id', 2);

        expect(DB::connection('center')->table('appointments')->count())->toBe(1)
            ->and(DB::connection('center')->table('appointments')->where('id', 19)->value('professional_id'))->toBe(2)
            ->and(DB::connection('center')->table('appointments')->where('id', 19)->value('appointment_date'))->toBe('2026-10-12')
            ->and(DB::connection('center')->table('appointment_history')->count())->toBe(1)
            ->and(DB::connection('center')->table('appointment_history')->value('event_type'))->toBe('REASIGNACION')
            ->and(DB::connection('center')->table('appointment_history')->value('appointment_id'))->toBe(19);
    } finally {
        DB::purge('center');
        DB::purge('center');
        @unlink($path);
    }
});

it('persiste cambios de estado hechos por recepción con su registro de auditoría', function () {
    $path = tempnam(sys_get_temp_dir(), 'appointment-status-');
    try {
        prepareAppointmentWorkflowDatabase('center', $path);
        [, , $client] = appointmentWorkflowClient($this, $path, 'appointment-status', 'RECEPCIONISTA');

        $client->patchJson('/api/v1/appointments/19/status', ['status' => 'CONFIRMADA'])
            ->assertOk()->assertJsonPath('data.status', 'CONFIRMADA');
        $client->patchJson('/api/v1/appointments/19/status', ['status' => 'NO_SHOW'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $client->patchJson('/api/v1/appointments/19/status', [
            'status' => 'CANCELADA', 'reason' => 'Cancelada por recepción.',
        ])->assertOk()->assertJsonPath('data.status', 'CANCELADA');

        expect(DB::connection('center')->table('appointments')->where('id', 19)->value('status'))->toBe('CANCELADA')
            ->and(DB::connection('center')->table('appointment_history')->count())->toBe(2)
            ->and(DB::connection('center')->table('appointment_history')->orderBy('id')->value('event_type'))->toBe('CAMBIO_ESTADO')
            ->and(DB::connection('center')->table('appointment_history')->orderByDesc('id')->value('event_type'))->toBe('CANCELACION');
    } finally {
        DB::purge('center');
        DB::purge('center');
        @unlink($path);
    }
});

it('permite al paciente reprogramar y cancelar su cita existente, sin crear otra', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-09 10:00:00', 'America/Santiago'));
    $path = tempnam(sys_get_temp_dir(), 'appointment-patient-');
    try {
        prepareAppointmentWorkflowDatabase('center', $path);
        [, , $client] = appointmentWorkflowClient($this, $path, 'appointment-patient', 'PACIENTE', patientId: 1);

        $client->patchJson('/api/v1/appointments/19/reschedule', [
            'appointment_date' => '2026-10-12', 'start_time' => '10:00',
        ])->assertOk()->assertJsonPath('data.id', 19);

        $client->patchJson('/api/v1/appointments/19/cancel', ['reason' => 'Cambio de planes.'])
            ->assertOk()->assertJsonPath('data.status', 'CANCELADA');

        expect(DB::connection('center')->table('appointments')->count())->toBe(1)
            ->and(DB::connection('center')->table('appointments')->where('id', 19)->value('status'))->toBe('CANCELADA')
            ->and(DB::connection('center')->table('appointment_history')->where('event_type', 'REPROGRAMACION')->count())->toBe(1)
            ->and(DB::connection('center')->table('appointment_history')->where('event_type', 'CANCELACION')->count())->toBe(1);
    } finally {
        Carbon::setTestNow();
        DB::purge('center');
        DB::purge('center');
        @unlink($path);
    }
});

it('permite al profesional marcar atendida solo su propia cita y registra el cambio', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-12 09:00:00', 'America/Santiago'));
    $path = tempnam(sys_get_temp_dir(), 'appointment-professional-');
    try {
        prepareAppointmentWorkflowDatabase('center', $path);
        DB::connection('center')->table('appointments')->where('id', 19)->update([
            'professional_id' => 2,
        ]);
        [, , $client] = appointmentWorkflowClient(
            $this, $path, 'appointment-professional', 'PROFESIONAL', professionalId: 2
        );

        $client->patchJson('/api/v1/appointments/19/status', ['status' => 'ATENDIDA'])
            ->assertOk()->assertJsonPath('data.status', 'ATENDIDA');

        expect(DB::connection('center')->table('appointments')->where('id', 19)->value('status'))->toBe('ATENDIDA')
            ->and(DB::connection('center')->table('appointment_history')->value('event_type'))->toBe('CAMBIO_ESTADO');
    } finally {
        Carbon::setTestNow();
        DB::purge('center');
        DB::purge('center');
        @unlink($path);
    }
});

it('impide que un profesional cambie el estado de una cita asignada a otro profesional', function () {
    $path = tempnam(sys_get_temp_dir(), 'appointment-wrong-professional-');
    try {
        prepareAppointmentWorkflowDatabase('center', $path);
        [, , $client] = appointmentWorkflowClient(
            $this, $path, 'appointment-wrong-professional', 'PROFESIONAL', professionalId: 2
        );

        $client->patchJson('/api/v1/appointments/19/status', ['status' => 'ATENDIDA'])
            ->assertForbidden();

        expect(DB::connection('center')->table('appointments')->where('id', 19)->value('status'))->toBe('PENDIENTE')
            ->and(DB::connection('center')->table('appointment_history')->count())->toBe(0);
    } finally {
        DB::purge('center');
        DB::purge('center');
        @unlink($path);
    }
});
