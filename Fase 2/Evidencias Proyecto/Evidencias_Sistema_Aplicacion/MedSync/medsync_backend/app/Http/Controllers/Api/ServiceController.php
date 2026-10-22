<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\Service;
use App\Services\Appointments\AppointmentService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly TenantContext $tenantContext
    ) {
    }

    /**
     * GET /api/v1/services
     *
     * Para un paciente, lista solo las prestaciones activas (con
     * especialidad activa) que puede reservar. Para el administrador
     * del centro, lista todas (activas e inactivas) con los campos
     * que necesita la pantalla de gestión.
     */
    public function index(Request $request)
    {
        $isAdmin = $this->tenantContext->centerUser()->role === 'ADMIN';

        if ($isAdmin) {
            $services = Service::with('specialty')
                ->orderBy('name')
                ->get();
        } else {
            $services = $this->appointmentService->listActiveServices();
        }

        return response()->json([
            'data' => $services->map(
                fn (Service $service) => $this->presentService($service, $isAdmin)
            ),
        ]);
    }

    /**
     * GET /api/v1/services/{service}/professionals
     *
     * Lista los profesionales activos que atienden la especialidad
     * de la prestación seleccionada.
     */
    public function professionals(Request $request, Service $service)
    {
        $professionals = $this->appointmentService->listProfessionalsForService($service);

        return response()->json([
            'data' => $professionals->map(fn ($professional) => [
                'id' => $professional->id,
                'first_name' => $professional->first_name,
                'last_name' => $professional->last_name,
            ]),
        ]);
    }

    /**
     * POST /api/v1/services
     *
     * Crea una prestación dentro de una especialidad activa del centro.
     */
    public function store(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'specialty_id' => [
                'required',
                'integer',
                Rule::exists('center.specialties', 'id')->where('is_active', true),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $name = trim($data['name']);

        $this->ensureNameAvailable((int) $data['specialty_id'], $name);

        $service = Service::create([
            'specialty_id' => $data['specialty_id'],
            'name' => $name,
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => $this->presentService($service->load('specialty'), true),
        ], 201);
    }

    /**
     * PATCH /api/v1/services/{service}
     *
     * Actualiza nombre, descripción, duración, especialidad o estado
     * de una prestación. Desactivarla no afecta las reservas ya
     * creadas; AppointmentService solo ofrece prestaciones activas
     * para reservar (ver findActiveService).
     */
    public function update(Request $request, int $service)
    {
        $this->ensureAdmin();

        $record = Service::findOrFail($service);

        $data = $request->validate([
            'specialty_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('center.specialties', 'id')->where('is_active', true),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:480'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $specialtyId = (int) ($data['specialty_id'] ?? $record->specialty_id);

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            $this->ensureNameAvailable($specialtyId, $name, $record->id);
            $data['name'] = $name;
        }

        $record->update($data);

        return response()->json([
            'data' => $this->presentService($record->fresh('specialty'), true),
        ]);
    }

    /**
     * Replica en PHP la restricción única de BD
     * (services_specialty_name_unique_ci): dentro de una misma
     * especialidad no puede haber dos prestaciones con el mismo
     * nombre, ignorando mayúsculas y espacios exteriores.
     */
    private function ensureNameAvailable(int $specialtyId, string $name, ?int $excludeId = null): void
    {
        $exists = Service::query()
            ->where('specialty_id', $specialtyId)
            ->whereRaw('LOWER(BTRIM(name)) = ?', [mb_strtolower($name)])
            ->when(
                $excludeId,
                fn ($query) => $query->where('id', '!=', $excludeId)
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['Ya existe una prestación con ese nombre en esta especialidad.'],
            ]);
        }
    }

    /**
     * Verifica que el usuario autenticado sea
     * administrador de un centro médico.
     */
    private function ensureAdmin(): void
    {
        $centerUser = $this->tenantContext->centerUser();

        abort_unless(
            $centerUser->role === 'ADMIN',
            403,
            'No tienes permisos para gestionar prestaciones.'
        );
    }

    /**
     * $includeAdminFields agrega is_active y specialty_id, que la
     * pantalla de administración necesita para editar, pero que el
     * flujo de reserva del paciente no usa.
     */
    private function presentService(Service $service, bool $includeAdminFields = false): array
    {
        $payload = [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'duration_minutes' => $service->duration_minutes,
            'specialty' => $service->specialty ? [
                'id' => $service->specialty->id,
                'name' => $service->specialty->name,
            ] : null,
        ];

        if ($includeAdminFields) {
            $payload['is_active'] = $service->is_active;
            $payload['specialty_id'] = $service->specialty_id;
        }

        return $payload;
    }
}
