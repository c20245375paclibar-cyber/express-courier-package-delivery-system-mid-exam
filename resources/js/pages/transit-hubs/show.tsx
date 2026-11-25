import CourierRecordShow from '@/components/courier-record-show';

interface Parcel {
    tracking_number: string;
    sender: { name: string };
    recipient: { name: string };
}

interface StatusLog {
    sequence: number;
    status: string;
    occurred_at: string;
    parcel: { tracking_number: string };
}

interface TransitHub {
    id: number;
    name: string;
    parcels: Parcel[];
    status_logs: StatusLog[];
}

export default function TransitHubShow({ hub }: { hub: TransitHub }) {
    const parcels = hub.parcels.length
        ? hub.parcels.map((parcel) => `${parcel.tracking_number} - ${parcel.sender.name} to ${parcel.recipient.name}`).join('\n')
        : 'No parcels at this hub';
    const statusLogs = hub.status_logs.length
        ? hub.status_logs
              .map(
                  (log) =>
                      `#${log.sequence} ${log.status.replaceAll('_', ' ')} - ${log.parcel.tracking_number} - ${new Date(log.occurred_at).toLocaleString()}`,
              )
              .join('\n')
        : 'No status events at this hub';

    return (
        <CourierRecordShow
            title={hub.name}
            subtitle="Transit hub"
            backUrl={route('transit-hubs.index')}
            editUrl={route('transit-hubs.edit', hub.id)}
            sections={[
                {
                    title: 'Hub information',
                    fields: [{ label: 'Name', value: hub.name }],
                },
                {
                    title: 'Current parcels',
                    fields: [{ label: 'Parcels', value: parcels }],
                },
                {
                    title: 'Status events',
                    fields: [{ label: 'History', value: statusLogs }],
                },
            ]}
        />
    );
}
