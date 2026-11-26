import CourierCrudForm, { CrudField } from '@/components/courier-crud-form';

interface Parcel {
    id: number;
    tracking_number: string;
    parcel_weight_kg: string;
    delivery_type: string;
    sender: { name: string; address: string };
    recipient: { name: string; address: string };
    current_hub: { name: string } | null;
    photos: { id: number; url: string }[];
}

const fields: CrudField[] = [
    { name: 'tracking_number', label: 'Tracking number', required: true },
    { name: 'sender_name', label: 'Sender name', required: true },
    { name: 'sender_address', label: 'Sender address', required: true },
    { name: 'recipient_name', label: 'Recipient name', required: true },
    { name: 'recipient_address', label: 'Recipient address', required: true },
    { name: 'parcel_weight_kg', label: 'Weight (kg)', type: 'number', step: '0.001', required: true },
    {
        name: 'delivery_type',
        label: 'Delivery speed',
        type: 'select',
        required: true,
        options: [
            { value: 'standard', label: 'Standard' },
            { value: 'express', label: 'Express' },
            { value: 'overnight', label: 'Overnight' },
        ],
    },
    { name: 'current_hub_name', label: 'Current transit hub' },
];

export default function EditParcel({ parcel, deliveryTypes }: { parcel: Parcel; deliveryTypes: string[] }) {
    const parcelFields = [
        ...fields.map((field) =>
            field.name === 'delivery_type'
                ? { ...field, options: deliveryTypes.map((type) => ({ value: type, label: type.replaceAll('_', ' ') })) }
                : field,
        ),
        ...(parcel.photos.length < 5
            ? [
                  {
                      name: 'photos',
                      label: 'Add parcel photos',
                      type: 'file' as const,
                      accept: 'image/jpeg,image/png,image/webp,image/avif',
                      multiple: true,
                      maxFiles: 5 - parcel.photos.length,
                  },
              ]
            : []),
    ];

    return (
        <CourierCrudForm
            title="Edit parcel"
            resource="Parcels"
            action={route('parcels.update', parcel.id)}
            method="put"
            cancelHref={route('parcels.index')}
            values={{
                tracking_number: parcel.tracking_number,
                sender_name: parcel.sender.name,
                sender_address: parcel.sender.address,
                recipient_name: parcel.recipient.name,
                recipient_address: parcel.recipient.address,
                parcel_weight_kg: parcel.parcel_weight_kg,
                delivery_type: parcel.delivery_type,
                current_hub_name: parcel.current_hub?.name ?? '',
            }}
            fields={parcelFields}
        />
    );
}
