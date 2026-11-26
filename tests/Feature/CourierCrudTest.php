<?php

namespace Tests\Feature;

use App\Models\CourierPayment;
use App\Models\Customer;
use App\Models\Parcel;
use App\Models\TransitHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_customer_details_include_parcels_with_only_required_related_fields(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();
        $hub = TransitHub::query()->create(['name' => 'North Hub']);
        $parcel->update(['current_hub_id' => $hub->id]);

        $this->getJson(route('customers.show', $parcel->sender_customer_id))
            ->assertOk()
            ->assertJsonPath('sent_parcels.0.tracking_number', $parcel->tracking_number)
            ->assertJsonPath('sent_parcels.0.recipient.name', 'Recipient')
            ->assertJsonPath('sent_parcels.0.current_hub.name', 'North Hub')
            ->assertJsonMissingPath('sent_parcels.0.created_at');
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

    public function test_transit_hub_details_include_optimized_parcel_and_status_log_relations(): void
    {
        $this->actingAs(User::factory()->create());
        $hub = TransitHub::query()->create(['name' => 'North Hub']);
        $parcel = $this->makeParcel();
        $parcel->update(['current_hub_id' => $hub->id]);
        $parcel->statusLogs()->create([
            'transit_hub_id' => $hub->id,
            'sequence' => 1,
            'status' => 'received',
            'occurred_at' => now(),
        ]);

        $this->getJson(route('transit-hubs.show', $hub))
            ->assertOk()
            ->assertJsonPath('parcels.0.tracking_number', $parcel->tracking_number)
            ->assertJsonPath('parcels.0.sender.name', 'Sender')
            ->assertJsonPath('parcels.0.recipient.name', 'Recipient')
            ->assertJsonPath('status_logs.0.parcel.tracking_number', $parcel->tracking_number);
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

    public function test_parcel_details_show_route_renders_parcel_and_tracking_history(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();
        $parcel->statusLogs()->create([
            'sequence' => 1,
            'status' => 'received',
            'occurred_at' => now(),
        ]);

        $this->get(route('parcels.show', $parcel))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('parcels/show')
                ->where('parcel.tracking_number', $parcel->tracking_number)
                ->where('parcel.sender.name', 'Sender')
                ->where('parcel.status_logs.0.status', 'received'));
    }

    public function test_parcel_listing_includes_photo_preview_urls(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();
        $path = 'parcels/list-preview.webp';
        Storage::disk('public')->put($path, 'image');
        $parcel->photos()->create(['path' => $path]);

        $this->get(route('parcels.index'))
            ->assertInertia(fn ($page) => $page
                ->where('parcels.data.0.tracking_number', $parcel->tracking_number)
                ->where('parcels.data.0.photos.0.url', Storage::disk('public')->url($path)));
    }

    public function test_parcel_photos_are_converted_to_webp_and_can_be_deleted(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $this->post(route('parcels.store'), [
            'tracking_number' => 'CXP-PHOTO-001',
            'sender_name' => 'Photo Sender',
            'sender_address' => '1 Example Street',
            'recipient_name' => 'Photo Recipient',
            'recipient_address' => '2 Example Street',
            'parcel_weight_kg' => 1,
            'delivery_type' => 'standard',
            'initial_status' => 'received',
            'photos' => [UploadedFile::fake()->image('parcel.jpg', 20, 20)],
        ])->assertRedirect(route('parcels.index'));

        $parcel = Parcel::query()->where('tracking_number', 'CXP-PHOTO-001')->firstOrFail();
        $photo = $parcel->photos()->firstOrFail();
        $this->assertStringEndsWith('.webp', $photo->path);
        $contents = Storage::disk('public')->get($photo->path);
        $this->assertSame('RIFF', substr($contents, 0, 4));
        $this->assertSame('WEBP', substr($contents, 8, 4));

        $this->get(route('parcels.show', $parcel))
            ->assertInertia(fn ($page) => $page->where('parcel.photos.0.url', Storage::disk('public')->url($photo->path)));

        $this->from(route('parcels.show', $parcel))
            ->delete(route('parcels.photos.destroy', [$parcel, $photo]))
            ->assertRedirect(route('parcels.show', $parcel));
        $this->assertDatabaseMissing('parcel_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($photo->path);
    }

    public function test_parcel_upload_rejects_more_than_five_photos(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $photos = [];
        for ($index = 1; $index <= 6; $index++) {
            $photos[] = UploadedFile::fake()->image("parcel-{$index}.jpg", 10, 10);
        }

        $this->from(route('parcels.create'))
            ->post(route('parcels.store'), [
                'tracking_number' => 'CXP-PHOTO-002',
                'sender_name' => 'Photo Sender',
                'sender_address' => '1 Example Street',
                'recipient_name' => 'Photo Recipient',
                'recipient_address' => '2 Example Street',
                'parcel_weight_kg' => 1,
                'delivery_type' => 'standard',
                'initial_status' => 'received',
                'photos' => $photos,
            ])
            ->assertSessionHasErrors('photos');

        $this->assertDatabaseMissing('parcels', ['tracking_number' => 'CXP-PHOTO-002']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_parcel_photos_can_be_uploaded_during_an_edit_request(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();

        $this->post(route('parcels.update', $parcel), [
            '_method' => 'put',
            'tracking_number' => $parcel->tracking_number,
            'sender_name' => 'Sender',
            'sender_address' => '1 Example Street',
            'recipient_name' => 'Recipient',
            'recipient_address' => '2 Example Street',
            'parcel_weight_kg' => 1,
            'delivery_type' => 'standard',
            'current_hub_name' => '',
            'photos' => [UploadedFile::fake()->image('edit-parcel.jpg', 10, 10)],
        ])->assertRedirect(route('parcels.index'));

        $this->assertCount(1, $parcel->photos()->get());
        $this->assertSame('webp', pathinfo($parcel->photos()->firstOrFail()->path, PATHINFO_EXTENSION));
    }

    public function test_profile_photo_upload_is_saved_as_webp(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('profile.avatar'), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 20, 20),
        ])->assertRedirect();

        $path = $user->fresh()->avatar_path;
        $this->assertIsString($path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
        $contents = Storage::disk('public')->get($path);
        $this->assertSame('RIFF', substr($contents, 0, 4));
        $this->assertSame('WEBP', substr($contents, 8, 4));
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
            'parcels.show',
            'parcels.edit',
            'shipment-status-logs.index',
            'shipment-status-logs.create',
            'shipment-status-logs.show',
            'shipment-status-logs.edit',
            'customers.index',
            'customers.create',
            'customers.show',
            'customers.edit',
            'transit-hubs.index',
            'transit-hubs.create',
            'transit-hubs.show',
            'transit-hubs.edit',
            'courier-payments.index',
            'courier-payments.create',
            'courier-payments.show',
            'courier-payments.edit',
        ] as $routeName) {
            $parameters = match ($routeName) {
                'parcels.edit' => [$parcel],
                'parcels.show' => [$parcel],
                'shipment-status-logs.show' => [$log],
                'shipment-status-logs.edit' => [$log],
                'customers.show' => [$customer],
                'customers.edit' => [$customer],
                'transit-hubs.show' => [$hub],
                'transit-hubs.edit' => [$hub],
                'courier-payments.show' => [$payment],
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
