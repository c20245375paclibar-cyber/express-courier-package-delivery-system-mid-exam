import CourierRecordShow from '@/components/courier-record-show';

interface StatusLog {
    id: number;
    sequence: number;
    status: string;
    occurred_at: string;
    parcel: { tracking_number: string };
    transit_hub: { name: string } | null;
}

export default function ShipmentStatusLogShow({ log }: { log: StatusLog }) {
    return (
        <CourierRecordShow
            title={`Status event #${log.sequence}`}
            subtitle="Shipment status history"
            backUrl={route('shipment-status-logs.index')}
            editUrl={route('shipment-status-logs.edit', log.id)}
            sections={[
                {
                    title: 'Event details',
                    fields: [
                        { label: 'Status', value: log.status.replaceAll('_', ' ') },
                        { label: 'Sequence', value: `#${log.sequence}` },
                        { label: 'Date and time', value: new Date(log.occurred_at).toLocaleString() },
                    ],
                },
                {
                    title: 'Shipment',
                    fields: [
                        { label: 'Tracking number', value: log.parcel.tracking_number },
                        { label: 'Transit hub', value: log.transit_hub?.name ?? '' },
                    ],
                },
            ]}
        />
    );
}
