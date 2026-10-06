<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\Patient;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    /** Lista mínima para que recepción pueda reservar a nombre de un paciente. */
    public function index(Request $request)
    {
        abort_unless(
            $this->tenantContext->centerUser()->role === 'RECEPCIONISTA',
            403,
            'No tienes permisos para consultar pacientes del centro.'
        );

        $patients = Patient::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'rut']);

        return response()->json([
            'data' => $patients->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'rut' => $patient->rut,
            ]),
        ]);
    }
}
