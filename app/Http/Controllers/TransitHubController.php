<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransitHubRequest;
use App\Http\Requests\UpdateTransitHubRequest;
use App\Services\TransitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransitHubController extends Controller
{
    public function __construct(private readonly TransitHubService $transitHubService) {}

    public function index(Request $request): Response|JsonResponse
    {
        $search = $this->transitHubService->normalizeSearch($request->query('search'));
        $archived = $request->boolean('archived');
        $hubs = $this->transitHubService->paginate($search, $archived);

        if ($request->expectsJson()) {
            return response()->json($hubs);
        }

        return Inertia::render('transit-hubs/index', [
            'hubs' => $hubs,
            'search' => $search ?? '',
            'archived' => $archived,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('transit-hubs/create');
    }

    public function store(StoreTransitHubRequest $request): RedirectResponse|JsonResponse
    {
        $hub = $this->transitHubService->create($request->validated());

        if ($request->expectsJson()) {
            return response()->json($hub, 201);
        }

        return to_route('transit-hubs.index')->with('success', 'Transit hub created successfully.');
    }

    public function show(int $transitHub): JsonResponse
    {
        return response()->json($this->transitHubService->find($transitHub));
    }

    public function edit(int $transitHub): Response
    {
        return Inertia::render('transit-hubs/edit', ['hub' => $this->transitHubService->find($transitHub)]);
    }

    public function update(UpdateTransitHubRequest $request, int $transitHub): RedirectResponse|JsonResponse
    {
        $hub = $this->transitHubService->update($this->transitHubService->find($transitHub), $request->validated());

        if ($request->expectsJson()) {
            return response()->json($hub);
        }

        return to_route('transit-hubs.index')->with('success', 'Transit hub updated successfully.');
    }

    public function destroy(Request $request, int $transitHub): RedirectResponse|JsonResponse
    {
        $this->transitHubService->archive($this->transitHubService->find($transitHub));

        if ($request->expectsJson()) {
            return response()->json(status: 204);
        }

        return to_route('transit-hubs.index')->with('success', 'Transit hub archived successfully.');
    }

    public function restore(int $transitHub): RedirectResponse
    {
        $this->transitHubService->restore($transitHub);

        return to_route('transit-hubs.index')->with('success', 'Transit hub restored successfully.');
    }
}
