import CourierRecordShow from '@/components/courier-record-show';

interface DispatchRequest {
    id: number;
    status: string;
    queued_at: string;
    processed_at: string | null;
    failure_reason: string | null;
    parcel: {
        tracking_number: string;
        sender: { name: string } | null;
        recipient: { name: string } | null;
    } | null;
    assigned_hub: { name: string } | null;
}

export default function DispatchRequestShow({ dispatchRequest }: { dispatchRequest: DispatchRequest }) {
    return (
        <CourierRecordShow
            title={`Dispatch request #${dispatchRequest.id}`}
            subtitle="Dispatch queue"
            backUrl={route('dispatch-requests.index')}
            editUrl={route('dispatch-requests.edit', dispatchRequest.id)}
            sections={[
                {
                    title: 'Dispatch details',
                    fields: [
                        { label: 'Status', value: dispatchRequest.status.replaceAll('_', ' ') },
                        { label: 'Queued at', value: new Date(dispatchRequest.queued_at).toLocaleString() },
                        { label: 'Processed at', value: dispatchRequest.processed_at ? new Date(dispatchRequest.processed_at).toLocaleString() : '' },
                        { label: 'Failure reason', value: dispatchRequest.failure_reason ?? '' },
                        { label: 'Assigned hub', value: dispatchRequest.assigned_hub?.name ?? '' },
                    ],
                },
                {
                    title: 'Parcel',
                    fields: [
                        { label: 'Tracking number', value: dispatchRequest.parcel?.tracking_number ?? 'Unknown parcel' },
                        { label: 'Sender', value: dispatchRequest.parcel?.sender?.name ?? '' },
                        { label: 'Recipient', value: dispatchRequest.parcel?.recipient?.name ?? '' },
                    ],
                },
            ]}
        />
    );
}
