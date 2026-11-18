import CourierCrudForm, { CrudField } from '@/components/courier-crud-form';

interface ParcelOption {
    id: number;
    tracking_number: string;
    sender: { name: string };
    recipient: { name: string };
}

interface DispatchRequest {
    id: number;
    parcel_id: number;
}

export default function EditDispatchRequest({ dispatchRequest, parcels }: { dispatchRequest: DispatchRequest; parcels: ParcelOption[] }) {
    const fields: CrudField[] = [
        {
            name: 'parcel_id',
            label: 'Parcel (saving queues it again)',
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
            title="Edit dispatch request"
            resource="Dispatch queue"
            action={route('dispatch-requests.update', dispatchRequest.id)}
            method="put"
            cancelHref={route('dispatch-requests.index')}
            values={{ parcel_id: String(dispatchRequest.parcel_id) }}
            fields={fields}
        />
    );
}
