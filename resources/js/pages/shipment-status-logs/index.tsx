import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Archive, ArchiveRestore, Plus, Search } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface ShipmentStatusLog {
    id: number;
    sequence: number;
    status: string;
    occurred_at: string;
    parcel: { tracking_number: string };
    transit_hub: { name: string } | null;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export default function ShipmentStatusLogsIndex({
    logs,
    search: initialSearch,
    archived,
}: {
    logs: Paginator<ShipmentStatusLog>;
    search: string;
    archived: boolean;
}) {
    const [search, setSearch] = useState(initialSearch);

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(route('shipment-status-logs.index'), { search, archived: archived ? 1 : undefined }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Status history', href: '/shipment-status-logs' },
            ]}
        >
            <Head title="Shipment status history" />
            <main className="flex flex-col gap-6 p-4 md:p-8">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Shipment status history</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {archived
                                ? 'Archived shipment events. Restore an event to return it to active history.'
                                : 'Status events are kept in chronological sequence per parcel.'}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(
                                    route('shipment-status-logs.index'),
                                    { search: search || undefined, archived: archived ? undefined : 1 },
                                    { preserveState: true, replace: true },
                                )
                            }
                        >
                            {archived ? 'View active' : 'View archive'}
                        </Button>
                        {!archived && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={route('parcels.index')}>Parcels</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={route('shipment-status-logs.create')}>
                                        <Plus /> Add status
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>
                <form onSubmit={submitSearch} className="flex w-full gap-2 sm:max-w-md">
                    <Input
                        aria-label="Search shipment statuses"
                        placeholder="Search tracking, status, or hub"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Button type="submit" variant="secondary">
                        <Search /> Search
                    </Button>
                    {initialSearch && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setSearch('');
                                router.get(
                                    route('shipment-status-logs.index'),
                                    { archived: archived ? 1 : undefined },
                                    { preserveState: true, replace: true },
                                );
                            }}
                        >
                            Clear
                        </Button>
                    )}
                </form>
                <div className="bg-card overflow-hidden rounded-xl border shadow-sm">
                    <Table>
                        <TableCaption>Most recent shipment events.</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tracking number</TableHead>
                                <TableHead>Sequence</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Transit hub</TableHead>
                                <TableHead>Date and time</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {logs.data.length ? (
                                logs.data.map((log) => (
                                    <TableRow key={log.id}>
                                        <TableCell className="font-medium">{log.parcel.tracking_number}</TableCell>
                                        <TableCell>#{log.sequence}</TableCell>
                                        <TableCell className="capitalize">{log.status.replaceAll('_', ' ')}</TableCell>
                                        <TableCell>{log.transit_hub?.name ?? '—'}</TableCell>
                                        <TableCell>{new Date(log.occurred_at).toLocaleString()}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                {archived ? (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.post(route('shipment-status-logs.restore', log.id), {}, { preserveScroll: true })
                                                        }
                                                    >
                                                        <ArchiveRestore /> Restore
                                                    </Button>
                                                ) : (
                                                    <>
                                                        <Button variant="outline" size="sm" asChild>
                                                            <Link href={route('shipment-status-logs.show', log.id)}>View</Link>
                                                        </Button>
                                                        <Button variant="outline" size="sm" asChild>
                                                            <Link href={route('shipment-status-logs.edit', log.id)}>Edit</Link>
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => {
                                                                if (window.confirm('Archive this shipment event? You can restore it later.')) {
                                                                    router.delete(route('shipment-status-logs.destroy', log.id));
                                                                }
                                                            }}
                                                        >
                                                            <Archive /> Archive
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground h-32 text-center">
                                        No status updates have been recorded.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
                <div className="text-muted-foreground flex items-center justify-between text-sm">
                    {logs.prev_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={logs.prev_page_url}>Previous</Link>
                        </Button>
                    ) : (
                        <span />
                    )}
                    <span>
                        Page {logs.current_page} of {logs.last_page}
                    </span>
                    {logs.next_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={logs.next_page_url}>Next</Link>
                        </Button>
                    ) : (
                        <span />
                    )}
                </div>
            </main>
        </AppLayout>
    );
}
