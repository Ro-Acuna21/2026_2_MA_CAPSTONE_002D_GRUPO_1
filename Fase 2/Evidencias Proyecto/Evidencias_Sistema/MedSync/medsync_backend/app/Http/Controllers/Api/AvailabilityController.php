<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\Availability;
use App\Models\Center\Professional;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AvailabilityController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function index()
    {
        $this->ensureAdmin();

        $availabilities = Availability::query()
            ->whereHas('professional')
            ->orderBy('professional_id')
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'data' => $availabilities->map(fn (Availability $availability) => $this->present($availability)),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();
        $data = $this->validatedData($request);

        $availability = DB::connection('center')->transaction(function () use ($data) {
            $professional = $this->lockProfessional((int) $data['professional_id']);
            $this->ensureNoOverlap($data);

            return $professional->availabilities()->create([
                'weekday' => (int) $data['weekday'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'is_active' => $data['is_active'] ?? true,
            ]);
        });

        return response()->json(['data' => $this->present($availability)], 201);
    }

    public function update(Request $request, Availability $availability)
    {
        $this->ensureAdmin();
        $data = $this->validatedData($request);

        $availability = DB::connection('center')->transaction(function () use ($availability, $data) {
            $professional = $this->lockProfessional((int) $data['professional_id']);
            $this->ensureNoOverlap($data, $availability->id);

            $availability->fill([
                'professional_id' => $professional->id,
                'weekday' => (int) $data['weekday'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'is_active' => $data['is_active'] ?? true,
            ])->save();

            return $availability->refresh();
        });

        return response()->json(['data' => $this->present($availability)]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'professional_id' => [
                'required',
                'integer',
                Rule::exists('center.professionals', 'id'),
            ],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function lockProfessional(int $professionalId): Professional
    {
        $professional = Professional::query()
            ->whereKey($professionalId)
            ->lockForUpdate()
            ->first();

        if (! $professional) {
            throw ValidationException::withMessages([
                'professional_id' => ['El profesional no existe en este centro.'],
            ]);
        }

        return $professional;
    }

    private function ensureNoOverlap(array $data, ?int $ignoreId = null): void
    {
        if (! ($data['is_active'] ?? true)) {
            return;
        }

        $query = Availability::query()
            ->where('professional_id', $data['professional_id'])
            ->where('weekday', $data['weekday'])
            ->where('is_active', true)
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time']);

        if ($ignoreId !== null) {
            $query->where('id', '<>', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_time' => ['El horario se superpone con otro bloque activo de este profesional.'],
            ]);
        }
    }

    private function ensureAdmin(): void
    {
        abort_unless(
            auth()->user()?->can('availability.manage'),
            403,
            'Solo el administrador del centro puede gestionar los horarios.'
        );
    }

    private function present(Availability $availability): array
    {
        return [
            'id' => $availability->id,
            'professional_id' => $availability->professional_id,
            'weekday' => (int) $availability->weekday,
            'start_time' => substr((string) $availability->start_time, 0, 5),
            'end_time' => substr((string) $availability->end_time, 0, 5),
            'is_active' => (bool) $availability->is_active,
        ];
    }
}
