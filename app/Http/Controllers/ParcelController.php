<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreParcelRequest;
use App\Http\Requests\UpdateParcelRequest;
use App\Services\ParcelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParcelController extends Controller
{
    public function __construct(private readonly ParcelService $parcelService) {}

    public function index(Request $request): Response
    {
        $search = $this->parcelService->normalizeSearch($request->query('search'));

        return Inertia::render('parcels/index', [
            'parcels' => $this->parcelService->paginate($search),
            'search' => $search ?? '',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('parcels/create', [
            'generated_tracking_number' => $this->parcelService->generateTrackingNumber(),
            'deliveryTypes' => ParcelService::DELIVERY_TYPES,
            'shipmentStatuses' => ParcelService::SHIPMENT_STATUSES,
        ]);
    }

    public function edit(int $parcel): Response
    {
        return Inertia::render('parcels/edit', [
            'parcel' => $this->parcelService->find($parcel)->load(['sender', 'recipient', 'currentHub']),
            'deliveryTypes' => ParcelService::DELIVERY_TYPES,
        ]);
    }

    public function store(StoreParcelRequest $request): RedirectResponse
    {
        $this->parcelService->create($request->validated());

        return to_route('parcels.index')->with('success', 'Parcel created successfully.');
    }

    public function update(UpdateParcelRequest $request, int $parcel): RedirectResponse
    {
        $this->parcelService->update($this->parcelService->find($parcel), $request->validated());

        return to_route('parcels.index')->with('success', 'Parcel updated successfully.');
    }

    public function destroy(int $parcel): RedirectResponse
    {
        $this->parcelService->delete($this->parcelService->find($parcel));

        return to_route('parcels.index')->with('success', 'Parcel deleted successfully.');
    }
}