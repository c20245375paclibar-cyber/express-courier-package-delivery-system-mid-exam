<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShipmentStatusLogRequest;
use App\Http\Requests\UpdateShipmentStatusLogRequest;
use App\Services\ShipmentStatusLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShipmentStatusLogController extends Controller
{
    public function __construct(private readonly ShipmentStatusLogService $statusLogService) {}

    public function index(Request $request): Response
    {
        $search = $this->statusLogService->normalizeSearch($request->query('search'));

        return Inertia::render('shipment-status-logs/index', [
            'logs' => $this->statusLogService->paginate($search),
            'search' => $search ?? '',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('shipment-status-logs/create', $this->statusLogService->getFormOptions());
    }

    public function edit(int $shipmentStatusLog): Response
    {
        return Inertia::render('shipment-status-logs/edit', [
            ...$this->statusLogService->getFormOptions(),
            'log' => $this->statusLogService->find($shipmentStatusLog)->load(['parcel', 'transitHub']),
        ]);
    }

    public function store(StoreShipmentStatusLogRequest $request): RedirectResponse
    {
        $this->statusLogService->create($request->validated());

        return to_route('shipment-status-logs.index')->with('success', 'Shipment status added successfully.');
    }

    public function update(UpdateShipmentStatusLogRequest $request, int $shipmentStatusLog): RedirectResponse
    {
        $this->statusLogService->update(
            $this->statusLogService->find($shipmentStatusLog),
            $request->validated(),
        );

        return to_route('shipment-status-logs.index')->with('success', 'Shipment status updated successfully.');
    }

    public function destroy(int $shipmentStatusLog): RedirectResponse
    {
        $this->statusLogService->delete($this->statusLogService->find($shipmentStatusLog));

        return to_route('shipment-status-logs.index')->with('success', 'Shipment status deleted successfully.');
    }
}
