import CourierCrudForm, { CrudField } from '@/components/courier-crud-form';

interface ParcelOption {
    id: number;
    tracking_number: string;
    shipping_cost: string;
}

interface Payment {
    id: number;
    parcel_id: number;
    amount: string;
    payment_status: string;
    payment_method: string | null;
    paid_at: string | null;
}

export default function EditCourierPayment({
    payment,
    parcels,
    paymentStatuses,
    paymentMethods,
}: {
    payment: Payment;
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
            title="Edit courier payment"
            resource="Courier payments"
            action={route('courier-payments.update', payment.id)}
            method="put"
            cancelHref={route('courier-payments.index')}
            values={{
                parcel_id: String(payment.parcel_id),
                amount: payment.amount,
                payment_status: payment.payment_status,
                payment_method: payment.payment_method ?? '',
                paid_at: payment.paid_at?.slice(0, 16) ?? '',
            }}
            fields={fields}
        />
    );
}
