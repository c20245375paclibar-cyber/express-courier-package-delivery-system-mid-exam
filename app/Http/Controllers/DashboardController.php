<?php

namespace App\Http\Controllers;

use App\Models\CourierPayment;
use App\Models\Customer;
use App\Models\Parcel;
use App\Models\ShipmentStatusLog;
use App\Models\TransitHub;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $now = now();
        $validated = $request->validate([
            'activity_period' => ['sometimes', 'required', Rule::in(['today', 'this-week', 'last-week', 'this-month', 'last-30-days', 'this-year', 'custom'])],
            'activity_start' => ['nullable', 'required_if:activity_period,custom', 'date_format:Y-m-d'],
            'activity_end' => ['nullable', 'required_if:activity_period,custom', 'date_format:Y-m-d', 'after_or_equal:activity_start', 'before_or_equal:today'],
        ]);
        $period = $validated['activity_period'] ?? 'this-week';
        $rangeStart = $now->copy()->startOfWeek();
        $rangeEnd = $now->copy();
        $granularity = 'day';

        switch ($period) {
            case 'today':
                $rangeStart = $now->copy()->startOfDay();
                break;
            case 'last-week':
                $rangeStart = $now->copy()->startOfWeek()->subWeek()->startOfDay();
                $rangeEnd = $now->copy()->startOfWeek()->subDay()->endOfDay();
                break;
            case 'this-month':
                $rangeStart = $now->copy()->startOfMonth();
                break;
            case 'last-30-days':
                $rangeStart = $now->copy()->subDays(29)->startOfDay();
                break;
            case 'this-year':
                $rangeStart = $now->copy()->startOfYear();
                $granularity = 'month';
                break;
            case 'custom':
                $rangeStart = Carbon::createFromFormat('Y-m-d', $validated['activity_start'])->startOfDay();
                $rangeEnd = Carbon::createFromFormat('Y-m-d', $validated['activity_end'])->endOfDay();

                if ($rangeStart->diffInDays($rangeEnd) > 365) {
                    throw ValidationException::withMessages([
                        'activity_end' => 'Choose a custom range of no more than 366 days.',
                    ]);
                }
                break;
        }

        $rangeEnd = $rangeEnd->min($now);
        $activityCounts = ShipmentStatusLog::query()
            ->selectRaw('DATE(occurred_at) as activity_date, COUNT(*) as total')
            ->whereBetween('occurred_at', [$rangeStart, $rangeEnd])
            ->groupBy('activity_date')
            ->pluck('total', 'activity_date');

        $activityByBucket = [];
        foreach ($activityCounts as $date => $count) {
            $bucket = $granularity === 'month' ? substr($date, 0, 7) : $date;
            $activityByBucket[$bucket] = ($activityByBucket[$bucket] ?? 0) + (int) $count;
        }

        $shipmentActivity = collect();
        $cursor = $granularity === 'month' ? $rangeStart->copy()->startOfMonth() : $rangeStart->copy()->startOfDay();
        $lastBucket = $granularity === 'month' ? $rangeEnd->copy()->startOfMonth() : $rangeEnd->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($lastBucket)) {
            $bucket = $granularity === 'month' ? $cursor->format('Y-m') : $cursor->toDateString();
            $shipmentActivity->push([
                'date' => $cursor->toDateString(),
                'label' => $granularity === 'month'
                    ? $cursor->format('M')
                    : ($period === 'today' ? 'Today' : $cursor->format('D j')),
                'count' => $activityByBucket[$bucket] ?? 0,
            ]);

            if ($granularity === 'month') {
                $cursor->addMonth();
            } else {
                $cursor->addDay();
            }
        }

        $periodLabels = [
            'today' => 'Today',
            'this-week' => 'This week',
            'last-week' => 'Last week',
            'this-month' => 'This month',
            'last-30-days' => 'Last 30 days',
            'this-year' => 'This year',
            'custom' => 'Custom date range',
        ];
        $activityTotal = $shipmentActivity->sum('count');
        $activityRangeLabel = $period === 'custom'
            ? $rangeStart->format('M j, Y').' - '.$rangeEnd->format('M j, Y')
            : $periodLabels[$period];

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
            'shipmentActivity' => $shipmentActivity,
            'activityTotal' => $activityTotal,
            'activityPeriod' => $period,
            'activityRange' => [
                'start' => $rangeStart->toDateString(),
                'end' => $rangeEnd->toDateString(),
                'label' => $activityRangeLabel,
            ],
            'today' => $now->toDateString(),
        ]);
    }
}
