<?php

namespace Tests\Feature;

use App\Models\CourierPayment;
use App\Models\Customer;
use App\Models\Parcel;
use App\Models\User;
use App\Models\TransitHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_crud_normalizes_fields_and_can_archive_and_restore(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('customers.store'), [
            'name' => '<b>  Alex   Courier </b>',
            'address' => '  12 MAIN   street ',
        ])->assertRedirect(route('customers.index'));

        $customer = Customer::query()->firstOrFail();
        $this->assertSame('Alex Courier', $customer->name);
        $this->assertSame('12 Main Street', $customer->address);

        $this->patch(route('customers.update', $customer), [
            'name' => 'Alex Updated',
            'address' => '88 Oak Avenue',
        ])->assertRedirect(route('customers.index'));
        $this->assertSame('Alex Updated', $customer->fresh()->name);

        $this->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success', 'Customer archived successfully.');
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->getJson(route('customers.index'))->assertJsonMissing(['id' => $customer->id]);
        $this->getJson(route('customers.index', ['archived' => 1]))
            ->assertJsonPath('data.0.id', $customer->id);

        $this->post(route('customers.restore', $customer->id))
            ->assertRedirect(route('customers.index'));
        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_referenced_customer_can_be_archived_without_breaking_parcel_history(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();

        $this->delete(route('customers.destroy', $parcel->sender))
            ->assertRedirect(route('customers.index'));

        $this->assertSoftDeleted('customers', ['id' => $parcel->sender_customer_id]);
        $this->assertSame('Sender', $parcel->fresh()->sender->name);
    }

    public function test_transit_hub_crud_updates_normalized_name_and_can_archive_and_restore(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('transit-hubs.store'), ['name' => '<i>  north   hub </i>'])
            ->assertRedirect(route('transit-hubs.index'));
        $hub = TransitHub::query()->firstOrFail();
        $this->assertSame('north hub', $hub->name);

        $this->patch(route('transit-hubs.update', $hub), ['name' => 'South Hub'])
            ->assertRedirect(route('transit-hubs.index'));
        $this->assertSame('South Hub', $hub->fresh()->name);

        $this->delete(route('transit-hubs.destroy', $hub))
            ->assertRedirect(route('transit-hubs.index'));
        $this->assertSoftDeleted('transit_hubs', ['id' => $hub->id]);

        $this->post(route('transit-hubs.restore', $hub->id))
            ->assertRedirect(route('transit-hubs.index'));
        $this->assertNotSoftDeleted('transit_hubs', ['id' => $hub->id]);
    }

    public function test_payment_crud_normalizes_enums_and_manages_paid_timestamp(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();

        $this->post(route('courier-payments.store'), [
            'parcel_id' => $parcel->id,
            'amount' => '12.50',
            'payment_status' => '<b>PAID</b>',
            'payment_method' => 'CARD',
        ])->assertRedirect(route('courier-payments.index'));

        $payment = CourierPayment::query()->latest('id')->firstOrFail();
        $this->assertSame('paid', $payment->payment_status);
        $this->assertSame('card', $payment->payment_method);
        $this->assertNotNull($payment->paid_at);

        $this->patch(route('courier-payments.update', $payment), [
            'parcel_id' => $parcel->id,
            'amount' => '15.00',
            'payment_status' => 'pending',
            'payment_method' => 'mobile_wallet',
            'paid_at' => null,
        ])->assertRedirect(route('courier-payments.index'));

        $this->assertSame('pending', $payment->fresh()->payment_status);
        $this->assertNull($payment->fresh()->paid_at);
        $this->assertSame('mobile_wallet', $payment->fresh()->payment_method);

        $this->delete(route('courier-payments.destroy', $payment))
            ->assertRedirect(route('courier-payments.index'));
        $this->assertSoftDeleted('courier_payments', ['id' => $payment->id]);
        $this->post(route('courier-payments.restore', $payment->id))
            ->assertRedirect(route('courier-payments.index'));
        $this->assertNotSoftDeleted('courier_payments', ['id' => $payment->id]);
    }

    public function test_parcel_archive_and_restore_preserve_status_logs_and_payments(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();
        $log = $parcel->statusLogs()->create([
            'sequence' => 1,
            'status' => 'received',
            'occurred_at' => now(),
        ]);
        $payment = $parcel->payments()->create([
            'amount' => 5,
            'payment_status' => 'pending',
        ]);

        $this->delete(route('parcels.destroy', $parcel))
            ->assertRedirect(route('parcels.index'))
            ->assertSessionHas('success', 'Parcel archived successfully.');

        $this->assertSoftDeleted('parcels', ['id' => $parcel->id]);
        $this->assertDatabaseHas('shipment_status_logs', ['id' => $log->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('courier_payments', ['id' => $payment->id, 'deleted_at' => null]);

        $this->post(route('parcels.restore', $parcel->id))
            ->assertRedirect(route('parcels.index'));
        $this->assertNotSoftDeleted('parcels', ['id' => $parcel->id]);
    }

    public function test_status_log_archive_and_restore_keep_its_sequence(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();
        $log = $parcel->statusLogs()->create([
            'sequence' => 1,
            'status' => 'received',
            'occurred_at' => now(),
        ]);

        $this->delete(route('shipment-status-logs.destroy', $log))
            ->assertRedirect(route('shipment-status-logs.index'));
        $this->assertSoftDeleted('shipment_status_logs', ['id' => $log->id]);
        $this->assertSame(1, $log->fresh()->sequence);

        $this->post(route('shipment-status-logs.restore', $log->id))
            ->assertRedirect(route('shipment-status-logs.index'));
        $this->assertNotSoftDeleted('shipment_status_logs', ['id' => $log->id]);
        $this->assertSame(1, $log->fresh()->sequence);
    }

    public function test_invalid_domain_enum_values_are_rejected_after_sanitization(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();

        $this->from(route('parcels.create'))
            ->post(route('parcels.store'), [
                'tracking_number' => 'CXP-INVALID',
                'sender_name' => 'Sender',
                'sender_address' => '1 Example Street',
                'recipient_name' => 'Recipient',
                'recipient_address' => '2 Example Street',
                'parcel_weight_kg' => 1,
                'delivery_type' => '<b>invalid</b>',
                'initial_status' => 'received',
            ])
            ->assertSessionHasErrors('delivery_type');

        $this->from(route('shipment-status-logs.create'))
            ->post(route('shipment-status-logs.store'), [
                'parcel_id' => $parcel->id,
                'status' => '<b>invalid</b>',
            ])
            ->assertSessionHasErrors('status');

        $this->from(route('courier-payments.create'))
            ->post(route('courier-payments.store'), [
                'parcel_id' => $parcel->id,
                'amount' => 5,
                'payment_status' => '<b>invalid</b>',
                'payment_method' => 'card',
            ])
            ->assertSessionHasErrors('payment_status');
    }

    public function test_each_courier_resource_renders_its_index_and_create_page(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();
        $log = $parcel->statusLogs()->create([
            'sequence' => 1,
            'status' => 'received',
            'occurred_at' => now(),
        ]);
        $customer = $parcel->sender;
        $hub = TransitHub::query()->create(['name' => 'Screen test hub']);
        $payment = $parcel->payments()->create([
            'amount' => 5,
            'payment_status' => 'pending',
        ]);

        foreach ([
            'parcels.index',
            'parcels.create',
            'parcels.edit',
            'shipment-status-logs.index',
            'shipment-status-logs.create',
            'shipment-status-logs.edit',
            'customers.index',
            'customers.create',
            'customers.edit',
            'transit-hubs.index',
            'transit-hubs.create',
            'transit-hubs.edit',
            'courier-payments.index',
            'courier-payments.create',
            'courier-payments.edit',
        ] as $routeName) {
            $parameters = match ($routeName) {
                'parcels.edit' => [$parcel],
                'shipment-status-logs.edit' => [$log],
                'customers.edit' => [$customer],
                'transit-hubs.edit' => [$hub],
                'courier-payments.edit' => [$payment],
                default => [],
            };

            $this->get(route($routeName, $parameters))->assertOk();
        }
    }

    private function makeParcel(): Parcel
    {
        $sender = Customer::query()->create(['name' => 'Sender', 'address' => '1 Example Street']);
        $recipient = Customer::query()->create(['name' => 'Recipient', 'address' => '2 Example Street']);

        return Parcel::query()->create([
            'tracking_number' => 'CXP-CRUD-001',
            'sender_customer_id' => $sender->id,
            'recipient_customer_id' => $recipient->id,
            'parcel_weight_kg' => 1,
            'delivery_type' => 'standard',
            'shipping_cost' => 5,
        ]);
    }
}
