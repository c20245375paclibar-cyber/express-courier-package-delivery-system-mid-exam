import CourierResourceIndex, { ResourceRow } from '@/components/courier-resource-index';

interface Hub {
    id: number;
    name: string;
    parcels_count: number;
    status_logs_count: number;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export default function TransitHubsIndex({ hubs, search, archived }: { hubs: Paginator<Hub>; search: string; archived: boolean }) {
    const rows: Paginator<ResourceRow> = {
        ...hubs,
        data: hubs.data.map((hub) => ({
            id: hub.id,
            values: [hub.name, String(hub.parcels_count), String(hub.status_logs_count)],
        })),
    };

    return (
        <CourierResourceIndex
            title="Transit hubs"
            description={
                archived
                    ? 'Archived transit hubs. Restore a hub to assign it to parcels again.'
                    : 'Maintain hubs used for parcel locations and tracking events.'
            }
            resource="Transit hubs"
            indexUrl={route('transit-hubs.index')}
            createUrl={route('transit-hubs.create')}
            editUrl={(id) => route('transit-hubs.edit', id)}
            deleteUrl={(id) => route('transit-hubs.destroy', id)}
            restoreUrl={(id) => route('transit-hubs.restore', id)}
            columns={['Hub name', 'Current parcels', 'Status events']}
            rows={rows}
            search={search}
            archived={archived}
        />
    );
}
