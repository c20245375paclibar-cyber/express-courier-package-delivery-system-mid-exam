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

export default function CustomersIndex({ customers, search }: { customers: Paginator<Customer>; search: string }) {
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
            description="Manage the sender and recipient records linked to parcel shipments."
            resource="Customers"
            indexUrl={route('customers.index')}
            createUrl={route('customers.create')}
            editUrl={(id) => route('customers.edit', id)}
            deleteUrl={(id) => route('customers.destroy', id)}
            columns={['Name', 'Address', 'Sent parcels', 'Received parcels']}
            rows={rows}
            search={search}
        />
    );
}
