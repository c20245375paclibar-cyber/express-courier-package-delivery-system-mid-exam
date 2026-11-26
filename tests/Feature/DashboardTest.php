<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Parcel;
use App\Models\ShipmentStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/dashboard')->assertOk();
    }

    public function test_dashboard_shows_shipment_activity_for_this_and_last_week()
    {
        $this->travelTo(now()->startOfWeek()->addDays(3)->setTime(12, 0));
        $this->actingAs(User::factory()->create());

        $sender = Customer::query()->create(['name' => 'Sender', 'address' => 'Sender address']);
        $recipient = Customer::query()->create(['name' => 'Recipient', 'address' => 'Recipient address']);
        $parcel = Parcel::query()->create([
            'tracking_number' => 'TEST-001',
            'sender_customer_id' => $sender->id,
            'recipient_customer_id' => $recipient->id,
            'parcel_weight_kg' => 1,
            'delivery_type' => 'standard',
            'shipping_cost' => 10,
        ]);
        $thisWeek = now()->startOfWeek()->addDays(2);
        $lastWeek = $thisWeek->copy()->subWeek();
        $tomorrow = now()->copy()->addDay();

        ShipmentStatusLog::query()->create([
            'parcel_id' => $parcel->id,
            'sequence' => 1,
            'status' => 'received',
            'occurred_at' => $lastWeek,
        ]);
        ShipmentStatusLog::query()->create([
            'parcel_id' => $parcel->id,
            'sequence' => 2,
            'status' => 'in_transit',
            'occurred_at' => $thisWeek,
        ]);
        ShipmentStatusLog::query()->create([
            'parcel_id' => $parcel->id,
            'sequence' => 3,
            'status' => 'out_for_delivery',
            'occurred_at' => $tomorrow,
        ]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('shipmentActivity', now()->dayOfWeekIso)
                ->where('shipmentActivity.2.count', 1)
                ->where('activityTotal', 1)
                ->where('activityPeriod', 'this-week')
                ->where('today', now()->toDateString())
            );

        $this->get('/dashboard?activity_period=last-week')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('shipmentActivity', 7)
                ->where('shipmentActivity.2.count', 1)
                ->where('activityTotal', 1)
                ->where('activityPeriod', 'last-week')
            );

        $this->get('/dashboard?activity_period=today')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('shipmentActivity', 1)
                ->where('activityTotal', 0)
                ->where('activityPeriod', 'today')
            );

        $this->get('/dashboard?activity_period=this-month')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('shipmentActivity', now()->day)
                ->where('activityPeriod', 'this-month')
            );

        $this->get('/dashboard?activity_period=last-30-days')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('shipmentActivity', 30)
                ->where('activityTotal', 2)
                ->where('activityPeriod', 'last-30-days')
            );

        $this->get('/dashboard?activity_period=this-year')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('shipmentActivity', now()->month)
                ->where('activityPeriod', 'this-year')
            );

        $this->get('/dashboard?activity_period=custom&activity_start='.$lastWeek->toDateString().'&activity_end='.$thisWeek->toDateString())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('shipmentActivity', 8)
                ->where('activityTotal', 2)
                ->where('activityPeriod', 'custom')
            );
    }
}
