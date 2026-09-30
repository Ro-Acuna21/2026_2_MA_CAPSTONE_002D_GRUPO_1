<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\Service;
use App\Services\Appointments\AppointmentService;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService
    ) {
    }

    /**
     * GET /api/v1/services
     *
     * Lista las prestaciones activas (con su especialidad) que un
     * paciente puede reservar.
     */
    public function index(Request $request)
    {
        $services = $this->appointmentService->listActiveServices();

        return response()->json([
            'data' => $services->map(
                fn (Service $service) => $this->presentService($service)
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

    private function presentService(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'duration_minutes' => $service->duration_minutes,
            'specialty' => $service->specialty ? [
                'id' => $service->specialty->id,
                'name' => $service->specialty->name,
            ] : null,
        ];
    }
}
