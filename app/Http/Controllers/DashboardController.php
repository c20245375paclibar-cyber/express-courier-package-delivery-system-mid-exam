<?php

namespace App\Http\Controllers;

use App\Models\CourierPayment;
use App\Models\Customer;
use App\Models\Parcel;
use App\Models\TransitHub;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $recentParcels = Parcel::query()
            ->with([
                'sender:id,name',
                'recipient:id,name',
                'currentHub:id,name',
                'statusLogs:id,parcel_id,status,sequence',
            ])
            ->latest()
            ->limit(6)
            ->get([
                'id',
                'tracking_number',
                'sender_customer_id',
                'recipient_customer_id',
                'current_hub_id',
                'parcel_weight_kg',
                'delivery_type',
                'shipping_cost',
                'created_at',
            ])
            ->map(fn (Parcel $parcel) => [
                'id' => $parcel->id,
                'tracking_number' => $parcel->tracking_number,
                'sender' => $parcel->sender?->name,
                'recipient' => $parcel->recipient?->name,
                'hub' => $parcel->currentHub?->name,
                'status' => $parcel->statusLogs->sortByDesc('sequence')->first()?->status ?? 'received',
                'delivery_type' => $parcel->delivery_type,
                'shipping_cost' => $parcel->shipping_cost,
            ]);

        return Inertia::render('dashboard', [
            'metrics' => [
                'total_parcels' => Parcel::count(),
                'customers' => Customer::count(),
                'transit_hubs' => TransitHub::count(),
                'pending_payments' => CourierPayment::query()->where('payment_status', 'pending')->count(),
                'paid_total' => (float) CourierPayment::query()->where('payment_status', 'paid')->sum('amount'),
                'shipping_total' => (float) Parcel::sum('shipping_cost'),
            ],
            'recentParcels' => $recentParcels,
        ]);
    }
}
