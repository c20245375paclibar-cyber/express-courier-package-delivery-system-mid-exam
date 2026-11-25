<?php

namespace Tests\Feature;

use App\Services\CourierDispatchStatus;
use App\Jobs\ProcessCourierDispatch;
use App\Models\CourierDispatchRequest;
use App\Models\Customer;
use App\Models\Parcel;
use App\Models\TransitHub;
use App\Models\User;
use App\Services\CourierDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CourierDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_request_crud_sanitizes_input_and_queues_processing(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel('CXP-DISPATCH-CRUD-1');
        $otherParcel = $this->makeParcel('CXP-DISPATCH-CRUD-2');

        $this->post(route('dispatch-requests.store'), [
            'parcel_id' => " <b> {$parcel->id} </b> ",
        ])->assertRedirect(route('dispatch-requests.index'));

        $dispatchRequest = CourierDispatchRequest::query()->firstOrFail();
        $this->assertSame($parcel->id, $dispatchRequest->parcel_id);
        $this->assertSame(CourierDispatchStatus::Pending, $dispatchRequest->status);
        Queue::assertPushed(ProcessCourierDispatch::class, 1);

        $this->patch(route('dispatch-requests.update', $dispatchRequest), [
            'parcel_id' => (string) $otherParcel->id,
        ])->assertRedirect(route('dispatch-requests.index'));
        $this->assertSame($otherParcel->id, $dispatchRequest->fresh()->parcel_id);
        $this->assertSame(CourierDispatchStatus::Pending, $dispatchRequest->fresh()->status);
        Queue::assertPushed(ProcessCourierDispatch::class, 2);

        $this->delete(route('dispatch-requests.destroy', $dispatchRequest))
            ->assertRedirect(route('dispatch-requests.index'));
        $this->assertSoftDeleted('courier_dispatch_requests', ['id' => $dispatchRequest->id]);
        $this->assertCount(0, app(CourierDispatchService::class)->paginate()->items());
        $this->assertCount(1, app(CourierDispatchService::class)->paginate(archived: true)->items());

        $this->post(route('dispatch-requests.restore', $dispatchRequest))
            ->assertRedirect(route('dispatch-requests.index'));
        $this->assertNotSoftDeleted('courier_dispatch_requests', ['id' => $dispatchRequest->id]);
        $this->assertSame(CourierDispatchStatus::Pending, $dispatchRequest->fresh()->status);
        Queue::assertPushed(ProcessCourierDispatch::class, 3);
    }

    public function test_dispatch_request_rejects_invalid_and_unrecognized_input(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('dispatch-requests.create'))
            ->post(route('dispatch-requests.store'), [
                'parcel_id' => '<b>not-an-id</b>',
                'status' => 'assigned',
            ])
            ->assertSessionHasErrors(['parcel_id', 'input']);

        $this->assertDatabaseCount('courier_dispatch_requests', 0);
    }

    public function test_new_dispatch_request_is_persisted_to_the_database_queue(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel('CXP-DISPATCH-DATABASE-QUEUE');

        $this->post(route('dispatch-requests.store'), ['parcel_id' => $parcel->id])
            ->assertRedirect(route('dispatch-requests.index'));

        $this->assertDatabaseCount('jobs', 1);
        $payload = json_decode(
            (string) DB::table('jobs')->value('payload'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertSame(ProcessCourierDispatch::class, $payload['data']['commandName']);
    }

    public function test_dispatch_job_assigns_hubs_in_round_robin_order_and_appends_status_logs(): void
    {
        $firstHub = TransitHub::query()->create(['name' => 'North Hub']);
        $secondHub = TransitHub::query()->create(['name' => 'South Hub']);
        $requests = collect([
            $this->makeDispatchRequest('CXP-DISPATCH-RR-1'),
            $this->makeDispatchRequest('CXP-DISPATCH-RR-2'),
            $this->makeDispatchRequest('CXP-DISPATCH-RR-3'),
        ]);
        $service = app(CourierDispatchService::class);

        foreach ($requests as $request) {
            $service->process($request->id);
        }

        $this->assertSame($firstHub->id, $requests[0]->fresh()->assigned_hub_id);
        $this->assertSame($secondHub->id, $requests[1]->fresh()->assigned_hub_id);
        $this->assertSame($firstHub->id, $requests[2]->fresh()->assigned_hub_id);

        foreach ($requests as $request) {
            $parcel = $request->parcel()->with('statusLogs')->firstOrFail();
            $this->assertSame(2, $parcel->statusLogs->count());
            $this->assertSame(2, $parcel->statusLogs->last()->sequence);
            $this->assertSame('in_transit', $parcel->statusLogs->last()->status);
        }
    }

    public function test_delivered_parcels_cannot_be_queued(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel('CXP-DISPATCH-DELIVERED');
        $parcel->statusLogs()->create([
            'sequence' => 2,
            'status' => 'delivered',
            'occurred_at' => now(),
        ]);

        $this->from(route('dispatch-requests.create'))
            ->post(route('dispatch-requests.store'), ['parcel_id' => $parcel->id])
            ->assertSessionHasErrors('parcel_id');
    }

    public function test_dispatch_crud_pages_render(): void
    {
        $this->actingAs(User::factory()->create());
        $dispatchRequest = $this->makeDispatchRequest('CXP-DISPATCH-PAGES');

        $this->get(route('dispatch-requests.index'))->assertOk();
        $this->get(route('dispatch-requests.create'))->assertOk();
        $this->get(route('dispatch-requests.show', $dispatchRequest))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dispatch-requests/show')
                ->where('dispatchRequest.id', $dispatchRequest->id));
        $this->get(route('dispatch-requests.edit', $dispatchRequest))->assertOk();
    }

    public function test_dispatch_queue_keeps_archived_parcels_visible(): void
    {
        $this->actingAs(User::factory()->create());
        $dispatchRequest = $this->makeDispatchRequest('CXP-DISPATCH-ARCHIVED-PARCEL');
        $dispatchRequest->parcel()->firstOrFail()->delete();

        $this->get(route('dispatch-requests.index'))->assertOk();
        $this->assertSame(
            'CXP-DISPATCH-ARCHIVED-PARCEL',
            $dispatchRequest->fresh()->load('parcel')->parcel->tracking_number,
        );
    }

    public function test_restoring_assigned_dispatch_request_does_not_queue_it_again(): void
    {
        Queue::fake();
        $dispatchRequest = $this->makeDispatchRequest('CXP-DISPATCH-RESTORE-ASSIGNED');
        $dispatchRequest->update([
            'status' => CourierDispatchStatus::Assigned,
            'processed_at' => now(),
        ]);
        $dispatchRequest->delete();

        app(CourierDispatchService::class)->restore($dispatchRequest->id);

        $this->assertSame(CourierDispatchStatus::Assigned, $dispatchRequest->fresh()->status);
        Queue::assertNotPushed(ProcessCourierDispatch::class);
    }

    private function makeDispatchRequest(string $trackingNumber): CourierDispatchRequest
    {
        $parcel = $this->makeParcel($trackingNumber);

        return CourierDispatchRequest::query()->create([
            'parcel_id' => $parcel->id,
            'status' => CourierDispatchStatus::Pending,
            'queued_at' => now(),
        ]);
    }

    private function makeParcel(string $trackingNumber): Parcel
    {
        $sender = Customer::query()->create([
            'name' => "Sender {$trackingNumber}",
            'address' => "1 {$trackingNumber} Street",
        ]);
        $recipient = Customer::query()->create([
            'name' => "Recipient {$trackingNumber}",
            'address' => "2 {$trackingNumber} Street",
        ]);
        $parcel = Parcel::query()->create([
            'tracking_number' => $trackingNumber,
            'sender_customer_id' => $sender->id,
            'recipient_customer_id' => $recipient->id,
            'parcel_weight_kg' => 1,
            'delivery_type' => 'standard',
            'shipping_cost' => 5,
        ]);
        $parcel->statusLogs()->create([
            'sequence' => 1,
            'status' => 'received',
            'occurred_at' => now()->subMinute(),
        ]);

        return $parcel;
    }
}
