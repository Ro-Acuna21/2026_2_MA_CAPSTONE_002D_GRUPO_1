<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Core\CommercialInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommercialInquiryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['nullable', 'integer', Rule::exists('core.plans', 'id')->where('is_active', true)],
            'contact_name' => ['required', 'string', 'min:2', 'max:150'],
            'center_name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:2000'],
            'website' => ['prohibited'],
        ]);

        unset($data['website']);
        $data['email'] = mb_strtolower(trim($data['email']));
        foreach (['contact_name', 'center_name', 'phone', 'message'] as $field) {
            if (isset($data[$field])) $data[$field] = trim($data[$field]);
        }
        CommercialInquiry::create($data);

        return response()->json(['message' => 'Solicitud recibida. El equipo comercial podrá revisarla desde la plataforma.'], 201);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return response()->json(['data' => CommercialInquiry::query()
            ->with('plan:id,name,code')
            ->latest()
            ->limit(100)
            ->get()]);
    }
}
