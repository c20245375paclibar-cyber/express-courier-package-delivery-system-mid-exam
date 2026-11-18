import CourierCrudForm, { CrudField } from '@/components/courier-crud-form';

interface ParcelOption {
    id: number;
    tracking_number: string;
    sender: { name: string };
    recipient: { name: string };
}

export default function CreateDispatchRequest({ parcels }: { parcels: ParcelOption[] }) {
    const fields: CrudField[] = [
        {
            name: 'parcel_id',
            label: 'Parcel',
            type: 'select',
            required: true,
            options: parcels.map((parcel) => ({
                value: String(parcel.id),
                label: `${parcel.tracking_number} · ${parcel.sender.name} → ${parcel.recipient.name}`,
            })),
        },
    ];

    return (
        <CourierCrudForm
            title="Queue a dispatch request"
            resource="Dispatch queue"
            action={route('dispatch-requests.store')}
            method="post"
            cancelHref={route('dispatch-requests.index')}
            values={{ parcel_id: '' }}
            fields={fields}
        />
    );
}
