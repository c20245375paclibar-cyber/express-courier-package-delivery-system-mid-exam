<?php

namespace Tests\Feature;

use App\Models\CourierPayment;
use App\Models\Customer;
use App\Models\Parcel;
use App\Models\ShipmentStatusLog;
use App\Models\User;
use App\Services\ParcelService;
use App\Services\ShipmentStatusLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_parcel_creation_normalizes_data_and_creates_related_records(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('parcels.store'), [
            'tracking_number' => '<b>cxp-0001</b>',
            'sender_name' => '<i>Sender</i>',
            'sender_address' => '  12 MAIN   street  ',
            'recipient_name' => 'Recipient',
            'recipient_address' => '45 Oak Road',
            'parcel_weight_kg' => 2,
            'delivery_type' => 'express',
            'current_hub_name' => 'Central Hub',
            'initial_status' => 'received',
            'status_occurred_at' => null,
        ])->assertRedirect(route('parcels.index'));

        $parcel = Parcel::query()->with(['sender', 'recipient', 'currentHub'])->firstOrFail();

        $this->assertSame('CXP-0001', $parcel->tracking_number);
        $this->assertSame('Sender', $parcel->sender->name);
        $this->assertSame('12 Main Street', $parcel->sender->address);
        $this->assertSame('Central Hub', $parcel->currentHub->name);
        $this->assertSame('15.00', $parcel->shipping_cost);
        $this->assertSame(1, $parcel->statusLogs()->count());
        $this->assertSame('received', $parcel->statusLogs()->first()->status);
        $this->assertSame('pending', $parcel->payments()->first()->payment_status);
        $this->assertSame('15.00', $parcel->payments()->first()->amount);
    }

    public function test_status_updates_are_numbered_and_cannot_precede_the_previous_event(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();

        $this->post(route('shipment-status-logs.store'), [
            'parcel_id' => $parcel->id,
            'status' => '<b>IN_TRANSIT</b>',
            'current_hub_name' => ' <i>North Hub</i> ',
            'occurred_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
        ])->assertRedirect(route('shipment-status-logs.index'));

        $this->assertSame(2, ShipmentStatusLog::query()->where('parcel_id', $parcel->id)->orderByDesc('sequence')->firstOrFail()->sequence);
        $this->assertSame('in_transit', ShipmentStatusLog::query()->where('parcel_id', $parcel->id)->orderByDesc('sequence')->firstOrFail()->status);
        $this->assertSame('North Hub', $parcel->fresh()->currentHub->name);

        $this->from(route('shipment-status-logs.create'))
            ->post(route('shipment-status-logs.store'), [
                'parcel_id' => $parcel->id,
                'status' => 'delivered',
                'occurred_at' => now()->addHour()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('occurred_at');
    }

    public function test_parcel_update_recalculates_shipping_and_updates_pending_payment(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel([
            'tracking_number' => 'CXP-OLD',
            'parcel_weight_kg' => 1,
            'delivery_type' => 'standard',
            'shipping_cost' => 5,
        ]);
        $payment = CourierPayment::query()->create([
            'parcel_id' => $parcel->id,
            'amount' => 5,
            'payment_status' => 'pending',
        ]);

        $this->patch(route('parcels.update', $parcel), [
            'tracking_number' => 'cxp-new',
            'sender_name' => 'Sender',
            'sender_address' => '1 Example Street',
            'recipient_name' => 'Recipient',
            'recipient_address' => '2 Example Street',
            'parcel_weight_kg' => 3,
            'delivery_type' => 'overnight',
            'current_hub_name' => '',
        ])->assertRedirect(route('parcels.index'));

        $this->assertSame('CXP-NEW', $parcel->fresh()->tracking_number);
        $this->assertSame('37.50', $parcel->fresh()->shipping_cost);
        $this->assertSame('37.50', $payment->fresh()->amount);
    }

    public function test_courier_payment_endpoints_create_update_and_delete_payments(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();

        $this->postJson(route('courier-payments.store'), [
            'parcel_id' => $parcel->id,
            'amount' => 12.50,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ])->assertCreated()
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('amount', '12.50');

        $payment = CourierPayment::query()->firstOrFail();

        $this->patchJson(route('courier-payments.update', $payment), [
            'parcel_id' => $parcel->id,
            'amount' => 15,
            'payment_status' => 'pending',
            'payment_method' => null,
        ])->assertOk()
            ->assertJsonPath('payment_status', 'pending')
            ->assertJsonPath('paid_at', null);

        $this->deleteJson(route('courier-payments.destroy', $payment))->assertNoContent();
        $this->assertDatabaseMissing('courier_payments', ['id' => $payment->id]);
    }

    public function test_courier_payment_creation_rejects_invalid_parcels_and_statuses(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('courier-payments.store'), [
            'parcel_id' => 999,
            'amount' => 0,
            'payment_status' => 'unknown',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['parcel_id', 'amount', 'payment_status']);
    }

    public function test_status_log_update_preserves_sequence_and_updates_latest_hub(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();
        $log = $parcel->statusLogs()->firstOrFail();

        $this->patch(route('shipment-status-logs.update', $log), [
            'status' => 'picked_up',
            'current_hub_name' => 'Airport Hub',
            'occurred_at' => now()->addDay()->setTime(9, 30)->toDateTimeString(),
        ])->assertRedirect(route('shipment-status-logs.index'));

        $this->assertSame(1, $log->fresh()->sequence);
        $this->assertSame('picked_up', $log->fresh()->status);
        $this->assertSame('Airport Hub', $parcel->fresh()->currentHub->name);
    }

    public function test_parcel_index_search_is_normalized_and_filters_results(): void
    {
        $this->actingAs(User::factory()->create());
        $matchingParcel = $this->makeParcel(['tracking_number' => 'CXP-SEARCH']);
        $this->makeParcel(['tracking_number' => 'CXP-OTHER']);

        $service = app(ParcelService::class);
        $results = $service->paginate($service->normalizeSearch('<b>cxp-search</b>'));

        $this->assertCount(1, $results->items());
        $this->assertSame($matchingParcel->id, $results->items()[0]->id);
    }

    public function test_status_log_index_search_filters_by_tracking_number(): void
    {
        $this->actingAs(User::factory()->create());
        $matchingParcel = $this->makeParcel(['tracking_number' => 'CXP-STATUS']);
        $this->makeParcel(['tracking_number' => 'CXP-OTHER']);

        $service = app(ShipmentStatusLogService::class);
        $results = $service->paginate($service->normalizeSearch('CXP-STATUS'));

        $this->assertCount(1, $results->items());
        $this->assertSame($matchingParcel->id, $results->items()[0]->parcel->id);
    }

    public function test_required_text_fields_reject_whitespace_only_input(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('parcels.store'), [
            'tracking_number' => 'CXP-WHITESPACE',
            'sender_name' => '   ',
            'sender_address' => '   ',
            'recipient_name' => 'Recipient',
            'recipient_address' => 'Address',
            'parcel_weight_kg' => 1,
            'delivery_type' => 'standard',
            'initial_status' => 'received',
        ])->assertSessionHasErrors(['sender_name', 'sender_address']);
    }

    public function test_parcel_creation_rejects_gibberish_contact_and_hub_input(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('parcels.store'), [
            'tracking_number' => 'CXP-GIBBERISH',
            'sender_name' => 'jdhfjksdhfjkshd543',
            'sender_address' => 'dsahfjkasdhfkjashdf',
            'recipient_name' => 'ajkdhjashdjas',
            'recipient_address' => 'fsajkhdfjksadhfs',
            'parcel_weight_kg' => 1,
            'delivery_type' => 'standard',
            'current_hub_name' => 'dasjghduasghdfuyas',
            'initial_status' => 'received',
        ])->assertSessionHasErrors([
            'sender_name',
            'sender_address',
            'recipient_name',
            'recipient_address',
            'current_hub_name',
        ]);
    }

    public function test_parcel_creation_rejects_the_same_gibberish_value_in_all_contact_and_location_fields(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('parcels.create'))
            ->post(route('parcels.store'), [
                'tracking_number' => 'CXP-REPEATED-GIBBERISH',
                'sender_name' => 'slakfnkjasdhfjsa',
                'sender_address' => 'slakfnkjasdhfjsa',
                'recipient_name' => 'slakfnkjasdhfjsa',
                'recipient_address' => 'slakfnkjasdhfjsa',
                'parcel_weight_kg' => 1,
                'delivery_type' => 'standard',
                'current_hub_name' => 'slakfnkjasdhfjsa',
                'initial_status' => 'received',
            ])
            ->assertSessionHasErrors([
                'sender_name',
                'sender_address',
                'recipient_name',
                'recipient_address',
                'current_hub_name',
            ]);

        $this->assertDatabaseMissing('parcels', ['tracking_number' => 'CXP-REPEATED-GIBBERISH']);
    }

    public function test_parcel_creation_rejects_repeated_values_between_contact_and_location_fields(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('parcels.create'))
            ->post(route('parcels.store'), [
                'tracking_number' => 'CXP-REPEATED-VALUES',
                'sender_name' => 'Main Street',
                'sender_address' => 'Main Street',
                'recipient_name' => 'Main Street',
                'recipient_address' => 'Main Street',
                'parcel_weight_kg' => 1,
                'delivery_type' => 'standard',
                'current_hub_name' => 'Main Street',
                'initial_status' => 'received',
            ])
            ->assertSessionHasErrors([
                'sender_address',
                'recipient_name',
                'recipient_address',
                'current_hub_name',
            ]);

        $this->assertDatabaseMissing('parcels', ['tracking_number' => 'CXP-REPEATED-VALUES']);
    }

    public function test_duplicate_check_uses_sanitized_case_and_whitespace_normalized_values(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('parcels.create'))
            ->post(route('parcels.store'), [
                'tracking_number' => ' cxp-sanitized-duplicates ',
                'sender_name' => '  Alex   Smith ',
                'sender_address' => '  1 MAIN   street ',
                'recipient_name' => '<b>alex smith</b>',
                'recipient_address' => '1 Main Street',
                'parcel_weight_kg' => 1,
                'delivery_type' => 'standard',
                'current_hub_name' => ' 1 main street ',
                'initial_status' => 'received',
            ])
            ->assertSessionHasErrors(['recipient_name', 'recipient_address', 'current_hub_name']);

        $this->assertDatabaseMissing('parcels', ['tracking_number' => 'CXP-SANITIZED-DUPLICATES']);
    }

    public function test_parcel_update_rejects_duplicate_contact_values_too(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel(['tracking_number' => 'CXP-UPDATE-DUPLICATE']);

        $this->from(route('parcels.edit', $parcel))
            ->patch(route('parcels.update', $parcel), [
                'tracking_number' => 'CXP-UPDATE-DUPLICATE',
                'sender_name' => 'Taylor Reed',
                'sender_address' => '1 Oak Avenue',
                'recipient_name' => '  taylor   reed ',
                'recipient_address' => '2 Pine Road',
                'parcel_weight_kg' => 1,
                'delivery_type' => 'standard',
                'current_hub_name' => '',
            ])
            ->assertSessionHasErrors('recipient_name');

        $this->assertSame('CXP-UPDATE-DUPLICATE', $parcel->fresh()->tracking_number);
    }

    public function test_status_log_rejects_gibberish_optional_hub_name(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();

        $this->post(route('shipment-status-logs.store'), [
            'parcel_id' => $parcel->id,
            'status' => 'in_transit',
            'current_hub_name' => 'dasjghduasghdfuyas',
            'occurred_at' => now()->addDay()->toDateTimeString(),
        ])->assertSessionHasErrors('current_hub_name');
    }

    public function test_blank_optional_hub_is_stored_as_null(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();

        $this->post(route('shipment-status-logs.store'), [
            'parcel_id' => $parcel->id,
            'status' => 'in_transit',
            'current_hub_name' => '   ',
            'occurred_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect(route('shipment-status-logs.index'));

        $this->assertNull(ShipmentStatusLog::query()->where('parcel_id', $parcel->id)->orderByDesc('sequence')->firstOrFail()->transit_hub_id);
    }

    public function test_unrecognized_status_input_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();

        $this->post(route('shipment-status-logs.store'), [
            'parcel_id' => $parcel->id,
            'status' => 'in_transit',
            'current_hub_name' => '',
            'unexpected_field' => 'not allowed',
        ])->assertSessionHasErrors('input');
    }

    private function makeParcel(array $attributes = []): Parcel
    {
        $trackingNumber = $attributes['tracking_number'] ?? 'CXP-TEST';
        $sender = Customer::query()->create(['name' => "Test Sender {$trackingNumber}", 'address' => "1 {$trackingNumber} Street"]);
        $recipient = Customer::query()->create(['name' => "Test Recipient {$trackingNumber}", 'address' => "2 {$trackingNumber} Street"]);
        $parcel = Parcel::query()->create(array_merge([
            'tracking_number' => $trackingNumber,
            'sender_customer_id' => $sender->id,
            'recipient_customer_id' => $recipient->id,
            'parcel_weight_kg' => 1,
            'delivery_type' => 'standard',
            'shipping_cost' => 5,
        ], $attributes));

        $parcel->statusLogs()->create([
            'sequence' => 1,
            'status' => 'received',
            'occurred_at' => now()->startOfDay()->toDateTimeString(),
        ]);

        return $parcel;
    }
}
