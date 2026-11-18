import CourierCrudForm, { CrudField } from '@/components/courier-crud-form';

interface ParcelOption {
    id: number;
    tracking_number: string;
    shipping_cost: string;
}

export default function CreateCourierPayment({
    parcels,
    paymentStatuses,
    paymentMethods,
}: {
    parcels: ParcelOption[];
    paymentStatuses: string[];
    paymentMethods: string[];
}) {
    const fields: CrudField[] = [
        { name: 'parcel_id', label: 'Parcel', type: 'select', required: true, options: parcels.map((parcel) => ({ value: String(parcel.id), label: `${parcel.tracking_number} ($${Number(parcel.shipping_cost).toFixed(2)})` })) },
        { name: 'amount', label: 'Amount', type: 'number', step: '0.01', required: true },
        { name: 'payment_status', label: 'Payment status', type: 'select', required: true, options: paymentStatuses.map((status) => ({ value: status, label: status.replaceAll('_', ' ') })) },
        { name: 'payment_method', label: 'Payment method', type: 'select', options: paymentMethods.map((method) => ({ value: method, label: method.replaceAll('_', ' ') })) },
        { name: 'paid_at', label: 'Paid at', type: 'datetime-local' },
    ];

    return (
        <CourierCrudForm
            title="Add courier payment"
            resource="Courier payments"
            action={route('courier-payments.store')}
            method="post"
            cancelHref={route('courier-payments.index')}
            values={{ parcel_id: '', amount: '', payment_status: 'pending', payment_method: '', paid_at: '' }}
            fields={fields}
        />
    );
}
