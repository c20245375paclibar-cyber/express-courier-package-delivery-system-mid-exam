<?php

namespace App\Http\Controllers;

use App\Models\TransitHub;
use App\Services\TransitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransitHubController extends Controller
{
    public function __construct(private readonly TransitHubService $hubService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->hubService->paginate());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('transit_hubs', 'name')],
        ]);

        return response()->json($this->hubService->create($data), 201);
    }

    public function show(int $transitHub): JsonResponse
    {
        return response()->json($this->hubService->find($transitHub));
    }

    public function update(Request $request, int $transitHub): JsonResponse
    {
        $hub = TransitHub::query()->findOrFail($transitHub);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('transit_hubs', 'name')->ignore($hub->id)],
        ]);

        return response()->json($this->hubService->update($hub, $data));
    }

    public function destroy(int $transitHub): JsonResponse
    {
        $hub = TransitHub::query()->findOrFail($transitHub);
        $this->hubService->delete($hub);

        return response()->json(status: 204);
    }
}
