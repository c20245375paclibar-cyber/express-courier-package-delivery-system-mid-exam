import CourierResourceIndex, { ResourceRow } from '@/components/courier-resource-index';
import { formatPeso } from '@/lib/utils';

interface Payment {
    id: number;
    amount: string;
    payment_status: string;
    payment_method: string | null;
    paid_at: string | null;
    parcel: { tracking_number: string };
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export default function CourierPaymentsIndex({ payments, search, archived }: { payments: Paginator<Payment>; search: string; archived: boolean }) {
    const rows: Paginator<ResourceRow> = {
        ...payments,
        data: payments.data.map((payment) => ({
            id: payment.id,
            values: [
                payment.parcel.tracking_number,
                formatPeso(payment.amount),
                payment.payment_status.replaceAll('_', ' '),
                payment.payment_method?.replaceAll('_', ' ') ?? '',
                payment.paid_at ? new Date(payment.paid_at).toLocaleString() : '',
            ],
        })),
    };

    return (
        <CourierResourceIndex
            title="Courier payments"
            description={
                archived
                    ? 'Archived courier payment records. Restore a payment to return it to the active list.'
                    : 'Manage payment amounts and settlement status for parcel shipments.'
            }
            resource="Courier payments"
            indexUrl={route('courier-payments.index')}
            createUrl={route('courier-payments.create')}
            viewUrl={(id) => route('courier-payments.show', id)}
            editUrl={(id) => route('courier-payments.edit', id)}
            deleteUrl={(id) => route('courier-payments.destroy', id)}
            restoreUrl={(id) => route('courier-payments.restore', id)}
            columns={['Tracking number', 'Amount', 'Status', 'Method', 'Paid at']}
            rows={rows}
            search={search}
            archived={archived}
        />
    );
}
