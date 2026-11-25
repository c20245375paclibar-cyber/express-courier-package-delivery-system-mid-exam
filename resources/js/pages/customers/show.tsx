import CourierRecordShow from '@/components/courier-record-show';

interface Parcel {
    tracking_number: string;
    sender?: { name: string };
    recipient?: { name: string };
}

interface Customer {
    id: number;
    name: string;
    address: string;
    sent_parcels: Parcel[];
    received_parcels: Parcel[];
}

export default function CustomerShow({ customer }: { customer: Customer }) {
    const sentParcels = customer.sent_parcels.length
        ? customer.sent_parcels.map((parcel) => `${parcel.tracking_number} - To ${parcel.recipient?.name ?? 'Unknown recipient'}`).join('\n')
        : 'No parcels sent';
    const receivedParcels = customer.received_parcels.length
        ? customer.received_parcels.map((parcel) => `${parcel.tracking_number} - From ${parcel.sender?.name ?? 'Unknown sender'}`).join('\n')
        : 'No parcels received';

    return (
        <CourierRecordShow
            title={customer.name}
            subtitle="Customer"
            backUrl={route('customers.index')}
            editUrl={route('customers.edit', customer.id)}
            sections={[
                {
                    title: 'Customer information',
                    fields: [
                        { label: 'Name', value: customer.name },
                        { label: 'Address', value: customer.address },
                    ],
                },
                {
                    title: 'Shipment activity',
                    fields: [
                        { label: 'Sent parcels', value: sentParcels },
                        { label: 'Received parcels', value: receivedParcels },
                    ],
                },
            ]}
        />
    );
}
