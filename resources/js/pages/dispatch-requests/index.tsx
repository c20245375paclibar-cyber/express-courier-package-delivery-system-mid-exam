import CourierResourceIndex, { ResourceRow } from '@/components/courier-resource-index';

interface DispatchRequest {
    id: number;
    status: string;
    queued_at: string;
    processed_at: string | null;
    failure_reason: string | null;
    parcel: { tracking_number: string } | null;
    assigned_hub: { name: string } | null;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

function label(value: string) {
    return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default function DispatchRequestsIndex({
    dispatchRequests,
    search,
    archived,
}: {
    dispatchRequests: Paginator<DispatchRequest>;
    search: string;
    archived: boolean;
}) {
    const rows: Paginator<ResourceRow> = {
        ...dispatchRequests,
        data: dispatchRequests.data.map((request) => ({
            id: request.id,
            values: [
                request.parcel?.tracking_number ?? 'Unknown parcel',
                label(request.status),
                request.assigned_hub?.name ?? 'Awaiting assignment',
                new Date(request.queued_at).toLocaleString(),
                request.failure_reason ?? '',
            ],
        })),
    };

    return (
        <CourierResourceIndex
            title="Dispatch queue"
            description={
                archived
                    ? 'Archived dispatch requests. Restore a request to return it to the active queue.'
                    : 'Queue parcel dispatches and distribute assignments across transit hubs in round-robin order.'
            }
            resource="Dispatch requests"
            indexUrl={route('dispatch-requests.index')}
            createUrl={route('dispatch-requests.create')}
            viewUrl={(id) => route('dispatch-requests.show', id)}
            editUrl={(id) => route('dispatch-requests.edit', id)}
            deleteUrl={(id) => route('dispatch-requests.destroy', id)}
            restoreUrl={(id) => route('dispatch-requests.restore', id)}
            columns={['Tracking number', 'Status', 'Assigned hub', 'Queued at', 'Failure']}
            rows={rows}
            search={search}
            archived={archived}
        />
    );
}
