import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCaption, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatPeso } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { Archive, ArchiveRestore, PackagePlus, Plus, Search, Truck } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Parcel {
    id: number;
    tracking_number: string;
    parcel_weight_kg: string;
    delivery_type: string;
    shipping_cost: string;
    sender: { name: string };
    recipient: { name: string };
    current_hub: { name: string } | null;
    status_logs: Array<{ status: string }>;

    photos: Array<{ id: number; url: string }>;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const breadcrumbs = [{ title: 'Dashboard', href: '/dashboard' }];

function statusLabel(status?: string): string {
    return status ? status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()) : 'No status';
}

export default function ParcelsIndex({
    parcels,
    search: initialSearch,
    archived,
}: {
    parcels: Paginator<Parcel>;
    search: string;
    archived: boolean;
}) {
    const [search, setSearch] = useState(initialSearch);
    const pageTotal = parcels.data.reduce((total, parcel) => total + Number(parcel.shipping_cost), 0);

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(route('parcels.index'), { search, archived: archived ? 1 : undefined }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Parcels" />
            <main className="flex flex-col gap-6 p-4 md:p-8">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <div className="flex items-center gap-2">
                            <Truck className="text-primary size-6" />
                            <h1 className="text-2xl font-semibold tracking-tight">Parcel shipments</h1>
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {archived
                                ? 'Archived parcels. Restore a parcel to return it to active tracking.'
                                : 'Track packages, delivery progress, and shipping charges.'}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(
                                    route('parcels.index'),
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
                                    <Link href={route('shipment-status-logs.index')}>Status history</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={route('parcels.create')}>
                                        <Plus /> Create parcel
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <form onSubmit={submitSearch} className="flex w-full gap-2 sm:max-w-md">
                    <Input
                        aria-label="Search parcels"
                        placeholder="Search tracking, customer, hub, or status"
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
                                router.get(route('parcels.index'), { archived: archived ? 1 : undefined }, { preserveState: true, replace: true });
                            }}
                        >
                            Clear
                        </Button>
                    )}
                </form>

                <div className="bg-card overflow-hidden rounded-xl border shadow-sm">
                    <Table>
                        <TableCaption>Recent parcels and their latest tracking status.</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Photo</TableHead>
                                <TableHead>Tracking number</TableHead>
                                <TableHead>Sender → Recipient</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Hub</TableHead>
                                <TableHead>Delivery</TableHead>
                                <TableHead className="text-right">Weight</TableHead>
                                <TableHead className="text-right">Shipping</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {parcels.data.length ? (
                                parcels.data.map((parcel) => (
                                    <TableRow key={parcel.id}>
                                        <TableCell>
                                            {parcel.photos[0] ? (
                                                <Link
                                                    href={route('parcels.show', parcel.id)}
                                                    aria-label={`View photos for ${parcel.tracking_number}`}
                                                    className="relative block size-12 overflow-hidden rounded-md border"
                                                >
                                                    <img
                                                        src={parcel.photos[0].url}
                                                        alt={`Parcel ${parcel.tracking_number}`}
                                                        className="size-full object-cover"
                                                        loading="lazy"
                                                    />
                                                    {parcel.photos.length > 1 && (
                                                        <span className="absolute inset-x-0 bottom-0 bg-black/70 text-center text-[10px] leading-4 text-white">
                                                            +{parcel.photos.length - 1}
                                                        </span>
                                                    )}
                                                </Link>
                                            ) : (
                                                <Link
                                                    href={route('parcels.edit', parcel.id)}
                                                    className="text-muted-foreground text-xs underline-offset-4 hover:underline"
                                                >
                                                    Add photo
                                                </Link>
                                            )}
                                        </TableCell>
                                        <TableCell className="font-medium">{parcel.tracking_number}</TableCell>
                                        <TableCell>
                                            {parcel.sender.name} <span className="text-muted-foreground">→</span> {parcel.recipient.name}
                                        </TableCell>
                                        <TableCell>
                                            <span className="bg-secondary rounded-full px-2.5 py-1 text-xs font-medium">
                                                {statusLabel(parcel.status_logs.at(-1)?.status)}
                                            </span>
                                        </TableCell>
                                        <TableCell>{parcel.current_hub?.name ?? '—'}</TableCell>
                                        <TableCell className="capitalize">{parcel.delivery_type}</TableCell>
                                        <TableCell className="text-right">{Number(parcel.parcel_weight_kg).toFixed(3)} kg</TableCell>
                                        <TableCell className="text-right">{formatPeso(parcel.shipping_cost)}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                {archived ? (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => router.post(route('parcels.restore', parcel.id), {}, { preserveScroll: true })}
                                                    >
                                                        <ArchiveRestore /> Restore
                                                    </Button>
                                                ) : (
                                                    <>
                                                        <Button variant="outline" size="sm" asChild>
                                                            <Link href={route('parcels.show', parcel.id)}>View</Link>
                                                        </Button>
                                                        <Button variant="outline" size="sm" asChild>
                                                            <Link href={route('parcels.edit', parcel.id)}>Edit</Link>
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => {
                                                                if (
                                                                    window.confirm(
                                                                        'Archive this parcel? Its status logs and payments will be kept, and you can restore it later.',
                                                                    )
                                                                ) {
                                                                    router.delete(route('parcels.destroy', parcel.id));
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
                                    <TableCell colSpan={9} className="h-40 text-center">
                                        <PackagePlus className="text-muted-foreground mx-auto mb-2 size-8" />
                                        <p className="font-medium">No parcels yet</p>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            {archived ? 'No archived parcels found.' : 'Create your first parcel to start tracking deliveries.'}
                                        </p>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                        <TableFooter>
                            <TableRow>
                                <TableCell colSpan={8}>Shipping total on this page</TableCell>
                                <TableCell className="text-right">{formatPeso(pageTotal)}</TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                </div>
                <div className="text-muted-foreground flex items-center justify-between text-sm">
                    {parcels.prev_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={parcels.prev_page_url}>Previous</Link>
                        </Button>
                    ) : (
                        <span />
                    )}
                    <span>
                        Page {parcels.current_page} of {parcels.last_page}
                    </span>
                    {parcels.next_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={parcels.next_page_url}>Next</Link>
                        </Button>
                    ) : (
                        <span />
                    )}
                </div>
            </main>
        </AppLayout>
    );
}
