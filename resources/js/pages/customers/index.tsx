import CourierResourceIndex, { ResourceRow } from '@/components/courier-resource-index';

interface Customer {
    id: number;
    name: string;
    address: string;
    sent_parcels_count: number;
    received_parcels_count: number;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export default function CustomersIndex({ customers, search, archived }: { customers: Paginator<Customer>; search: string; archived: boolean }) {
    const rows: Paginator<ResourceRow> = {
        ...customers,
        data: customers.data.map((customer) => ({
            id: customer.id,
            values: [customer.name, customer.address, String(customer.sent_parcels_count), String(customer.received_parcels_count)],
        })),
    };

    return (
        <CourierResourceIndex
            title="Customers"
            description={
                archived
                    ? 'Archived customer records. Restore a customer to use it again.'
                    : 'Manage the sender and recipient records linked to parcel shipments.'
            }
            resource="Customers"
            indexUrl={route('customers.index')}
            createUrl={route('customers.create')}
            viewUrl={(id) => route('customers.show', id)}
            editUrl={(id) => route('customers.edit', id)}
            deleteUrl={(id) => route('customers.destroy', id)}
            restoreUrl={(id) => route('customers.restore', id)}
            columns={['Name', 'Address', 'Sent parcels', 'Received parcels']}
            rows={rows}
            search={search}
            archived={archived}
        />
    );
}
