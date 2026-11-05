<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Center\ResultType;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class ResultTypeController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request)
    {
        $role = $this->tenant->centerUser()->role;
        abort_unless(in_array($role, ['ADMIN', 'PROFESIONAL'], true), 403);
        $query = ResultType::query()->orderBy('name');
        if ($role === 'PROFESIONAL') $query->where('is_active', true);
        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000']]);
        $type = ResultType::create([...$data, 'name' => trim($data['name']), 'is_active' => true]);
        return response()->json(['data' => $type], 201);
    }

    public function update(Request $request, ResultType $resultType)
    {
        $this->authorizeManage($request);
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'is_active' => ['sometimes', 'boolean']]);
        $resultType->update($data);
        return response()->json(['data' => $resultType->refresh()]);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($this->tenant->centerUser()->role === 'ADMIN' && $request->user()->can('results.types.manage'), 403);
    }
}
