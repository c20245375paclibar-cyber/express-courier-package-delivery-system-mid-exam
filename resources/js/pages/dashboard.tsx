import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { formatPeso } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import { ClipboardList, CreditCard, MapPin, PackagePlus, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface Metrics {
    total_parcels: number;
    customers: number;
    transit_hubs: number;
    pending_payments: number;
    paid_total: number;
    shipping_total: number;
}

interface RecentParcel {
    id: number;
    tracking_number: string;
    sender: string | null;
    recipient: string | null;
    hub: string | null;
    status: string;
    delivery_type: string;
    shipping_cost: string;
}

export default function Dashboard({ metrics, recentParcels }: { metrics: Metrics; recentParcels: RecentParcel[] }) {
    const cards = [
        { label: 'Total parcels', value: metrics.total_parcels.toLocaleString(), icon: ClipboardList, href: '/parcels' },
        { label: 'Customers', value: metrics.customers.toLocaleString(), icon: Users, href: '/customers' },
        { label: 'Transit hubs', value: metrics.transit_hubs.toLocaleString(), icon: MapPin, href: '/transit-hubs' },
        { label: 'Pending payments', value: metrics.pending_payments.toLocaleString(), icon: CreditCard, href: '/courier-payments' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Dashboard</h1>
                        <p className="text-muted-foreground text-sm">Overview of your package delivery operations.</p>
                    </div>
                    <Link
                        href="/parcels/create"
                        className="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium"
                    >
                        <PackagePlus className="size-4" />
                        Create parcel
                    </Link>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map(({ label, value, icon: Icon, href }) => (
                        <Link key={label} href={href} className="border-sidebar-border/70 rounded-xl border p-5 transition-colors hover:bg-accent/40">
                            <div className="flex items-center justify-between">
                                <p className="text-muted-foreground text-sm font-medium">{label}</p>
                                <Icon className="text-muted-foreground size-5" />
                            </div>
                            <p className="mt-3 text-3xl font-semibold">{value}</p>
                        </Link>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="border-sidebar-border/70 rounded-xl border p-5">
                        <p className="text-muted-foreground text-sm font-medium">Paid shipments</p>
                        <p className="mt-2 text-2xl font-semibold">{formatPeso(metrics.paid_total)}</p>
                    </div>
                    <div className="border-sidebar-border/70 rounded-xl border p-5">
                        <p className="text-muted-foreground text-sm font-medium">Shipping value</p>
                        <p className="mt-2 text-2xl font-semibold">{formatPeso(metrics.shipping_total)}</p>
                    </div>
                    <div className="border-sidebar-border/70 rounded-xl border p-5">
                        <p className="text-muted-foreground text-sm font-medium">Delivery network</p>
                        <p className="mt-2 text-2xl font-semibold">{metrics.transit_hubs} hubs</p>
                    </div>
                </div>

                <div className="border-sidebar-border/70 overflow-hidden rounded-xl border">
                    <div className="flex items-center justify-between border-b px-5 py-4">
                        <div>
                            <h2 className="font-semibold">Recent parcels</h2>
                            <p className="text-muted-foreground text-sm">Latest shipments and delivery status.</p>
                        </div>
                        <Link href="/parcels" className="text-sm font-medium text-primary hover:underline">View all</Link>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-muted-foreground bg-muted/30 text-xs uppercase">
                                <tr>
                                    <th className="px-5 py-3 text-left">Tracking</th>
                                    <th className="px-5 py-3 text-left">Route</th>
                                    <th className="px-5 py-3 text-left">Status</th>
                                    <th className="px-5 py-3 text-left">Hub</th>
                                    <th className="px-5 py-3 text-right">Shipping</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentParcels.map((parcel) => (
                                    <tr key={parcel.id} className="border-t">
                                        <td className="px-5 py-4 font-medium">{parcel.tracking_number}</td>
                                        <td className="px-5 py-4">{parcel.sender ?? '—'} <span className="text-muted-foreground">→</span> {parcel.recipient ?? '—'}</td>
                                        <td className="px-5 py-4 capitalize">{parcel.status.replaceAll('_', ' ')}</td>
                                        <td className="px-5 py-4">{parcel.hub ?? '—'}</td>
                                        <td className="px-5 py-4 text-right">{formatPeso(parcel.shipping_cost)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
