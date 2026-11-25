import CourierRecordShow from '@/components/courier-record-show';
import { formatPeso } from '@/lib/utils';

interface Payment {
    id: number;
    amount: string;
    payment_status: string;
    payment_method: string | null;
    paid_at: string | null;
    parcel: {
        tracking_number: string;
        sender: { name: string } | null;
        recipient: { name: string } | null;
    };
}

export default function CourierPaymentShow({ payment }: { payment: Payment }) {
    return (
        <CourierRecordShow
            title={`Payment #${payment.id}`}
            subtitle="Courier payment"
            backUrl={route('courier-payments.index')}
            editUrl={route('courier-payments.edit', payment.id)}
            sections={[
                {
                    title: 'Payment information',
                    fields: [
                        { label: 'Amount', value: formatPeso(payment.amount) },
                        { label: 'Status', value: payment.payment_status.replaceAll('_', ' ') },
                        { label: 'Method', value: payment.payment_method?.replaceAll('_', ' ') ?? '' },
                        { label: 'Paid at', value: payment.paid_at ? new Date(payment.paid_at).toLocaleString() : '' },
                    ],
                },
                {
                    title: 'Parcel',
                    fields: [
                        { label: 'Tracking number', value: payment.parcel.tracking_number },
                        { label: 'Sender', value: payment.parcel.sender?.name ?? '' },
                        { label: 'Recipient', value: payment.parcel.recipient?.name ?? '' },
                    ],
                },
            ]}
        />
    );
}
