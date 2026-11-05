<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\Appointment;
use App\Models\Center\MedicalResult;
use App\Models\Center\MedicalResultFile;
use App\Models\Center\ResultType;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MedicalResultController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request)
    {
        $member = $this->tenant->centerUser();
        $query = MedicalResult::with(['resultType', 'patient', 'professional', 'files'])->orderByDesc('performed_at');
        if ($member->role === 'PROFESIONAL' && $member->professional_id && $request->user()->can('results.manage.own')) {
            $query->where('professional_id', $member->professional_id);
        } elseif ($member->role === 'PACIENTE' && $member->patient_id && $request->user()->can('results.view.own')) {
            $query->where('patient_id', $member->patient_id)->where('status', 'PUBLISHED');
        } else {
            abort(403);
        }
        return response()->json(['data' => $query->get()->map(fn ($result) => $this->present($result))]);
    }

    public function store(Request $request)
    {
        $professionalId = $this->professionalId($request);
        $data = $request->validate([
            'appointment_id' => ['required', 'integer'],
            'result_type_id' => ['required', 'integer'],
            'performed_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'file' => ['required', 'file', 'mimetypes:application/pdf,image/png,image/jpeg,text/plain', 'max:5120'],
        ]);
        $appointment = Appointment::findOrFail($data['appointment_id']);
        if ($appointment->professional_id !== $professionalId || $appointment->status !== 'ATENDIDA') {
            throw ValidationException::withMessages(['appointment_id' => 'Selecciona una atención realizada bajo tu responsabilidad.']);
        }
        $type = ResultType::findOrFail($data['result_type_id']);
        abort_unless($type->is_active, 422, 'El tipo de informe está inactivo.');
        $uploaded = $request->file('file');
        $path = $uploaded->storeAs('medical-results/'.$this->tenant->medicalCenter()->id, Str::uuid()->toString(), 'local');
        try {
            $result = DB::connection('center')->transaction(function () use ($appointment, $professionalId, $data, $request, $uploaded, $path) {
                $result = MedicalResult::create([
                    'patient_id' => $appointment->patient_id,
                    'professional_id' => $professionalId,
                    'appointment_id' => $appointment->id,
                    'result_type_id' => $data['result_type_id'],
                    'performed_at' => $data['performed_at'].' 12:00:00',
                    'status' => 'DRAFT',
                    'created_by' => $request->user()->id,
                ]);
                $result->files()->create([
                    'disk' => 'local',
                    'storage_path' => $path,
                    'original_filename' => basename($uploaded->getClientOriginalName()),
                    'mime_type' => $uploaded->getMimeType(),
                    'size_bytes' => $uploaded->getSize(),
                    'checksum' => hash_file('sha256', $uploaded->getRealPath()),
                    'uploaded_by' => $request->user()->id,
                ]);
                return $result;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        return response()->json(['data' => $this->present($result->load(['resultType', 'patient', 'professional', 'files']))], 201);
    }

    public function publish(Request $request, MedicalResult $result)
    {
        $this->ensureResponsible($request, $result);
        abort_unless($result->status === 'DRAFT' && $result->files()->exists(), 422, 'Solo se puede publicar un borrador con archivo.');
        $result->update(['status' => 'PUBLISHED', 'published_at' => now(), 'published_by' => $request->user()->id]);
        return response()->json(['data' => $this->present($result->load(['resultType', 'patient', 'professional', 'files']))]);
    }

    public function void(Request $request, MedicalResult $result)
    {
        $this->ensureResponsible($request, $result);
        abort_unless(in_array($result->status, ['DRAFT', 'PUBLISHED'], true), 422);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $result->update(['status' => 'VOIDED', 'voided_at' => now(), 'voided_by' => $request->user()->id, 'void_reason' => $data['reason']]);
        return response()->json(['data' => $this->present($result->load(['resultType', 'patient', 'professional', 'files']))]);
    }

    public function download(Request $request, MedicalResult $result, MedicalResultFile $file)
    {
        abort_unless($file->medical_result_id === $result->id, 404);
        $member = $this->tenant->centerUser();
        $professional = $member->role === 'PROFESIONAL' && $member->professional_id === $result->professional_id && $request->user()->can('results.manage.own');
        $patient = $member->role === 'PACIENTE' && $member->patient_id === $result->patient_id && $result->status === 'PUBLISHED' && $request->user()->can('results.view.own');
        abort_unless($professional || $patient, 403);
        abort_unless($file->disk === 'local' && Storage::disk('local')->exists($file->storage_path), 404);
        return Storage::disk('local')->download($file->storage_path, $file->original_filename, ['Content-Type' => $file->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function professionalId(Request $request): int
    {
        $member = $this->tenant->centerUser();
        abort_unless($member->role === 'PROFESIONAL' && $member->professional_id && $request->user()->can('results.manage.own'), 403);
        return (int) $member->professional_id;
    }

    private function ensureResponsible(Request $request, MedicalResult $result): void
    {
        abort_unless($this->professionalId($request) === $result->professional_id, 403);
    }

    private function present(MedicalResult $result): array
    {
        return [
            'id' => $result->id, 'appointment_id' => $result->appointment_id,
            'patient_id' => $result->patient_id, 'patient_name' => trim(($result->patient?->first_name ?? '').' '.($result->patient?->last_name ?? '')),
            'professional_id' => $result->professional_id, 'professional_name' => trim(($result->professional?->first_name ?? '').' '.($result->professional?->last_name ?? '')),
            'result_type_id' => $result->result_type_id, 'result_type_name' => $result->resultType?->name,
            'performed_at' => $result->performed_at?->toDateString(), 'status' => $result->status,
            'published_at' => $result->published_at?->toIso8601String(), 'voided_at' => $result->voided_at?->toIso8601String(),
            'void_reason' => $result->void_reason,
            'files' => $result->files->map(fn ($file) => ['id' => $file->id, 'filename' => $file->original_filename, 'mime_type' => $file->mime_type, 'size_bytes' => $file->size_bytes]),
        ];
    }
}
