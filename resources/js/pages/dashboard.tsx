import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { formatPeso } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, CreditCard, MapPin, PackagePlus, Users } from 'lucide-react';
import { type FormEvent, useState } from 'react';

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

interface ActivityDay {
    date: string;
    label: string;
    count: number;
}

interface ActivityRange {
    start: string;
    end: string;
    label: string;
}

const activityPeriodOptions = [
    { value: 'today', label: 'Today' },
    { value: 'this-week', label: 'This week' },
    { value: 'last-week', label: 'Last week' },
    { value: 'this-month', label: 'This month' },
    { value: 'last-30-days', label: 'Last 30 days' },
    { value: 'this-year', label: 'This year' },
    { value: 'custom', label: 'Custom date range' },
] as const;

type ActivityPeriod = (typeof activityPeriodOptions)[number]['value'];

export default function Dashboard({
    metrics,
    recentParcels,
    shipmentActivity,
    activityTotal,
    activityPeriod: initialActivityPeriod,
    activityRange,
    today,
}: {
    metrics: Metrics;
    recentParcels: RecentParcel[];
    shipmentActivity: ActivityDay[];
    activityTotal: number;
    activityPeriod: ActivityPeriod;
    activityRange: ActivityRange;
    today: string;
}) {
    const [activityPeriod, setActivityPeriod] = useState<ActivityPeriod>(initialActivityPeriod);
    const [customStart, setCustomStart] = useState(activityRange.start);
    const [customEnd, setCustomEnd] = useState(activityRange.end);
    const cards = [
        { label: 'Total parcels', value: metrics.total_parcels.toLocaleString(), icon: ClipboardList, href: '/parcels' },
        { label: 'Customers', value: metrics.customers.toLocaleString(), icon: Users, href: '/customers' },
        { label: 'Transit hubs', value: metrics.transit_hubs.toLocaleString(), icon: MapPin, href: '/transit-hubs' },
        { label: 'Pending payments', value: metrics.pending_payments.toLocaleString(), icon: CreditCard, href: '/courier-payments' },
    ];
    const largestActivity = Math.max(...shipmentActivity.map((day) => day.count), 0);
    const chartMax = largestActivity === 0 ? 1 : largestActivity <= 5 ? largestActivity : Math.ceil(largestActivity / 5) * 5;
    const chartTickCount = Math.min(chartMax, 5);
    const chartTop = 18;
    const chartBottom = 170;
    const chartHeight = chartBottom - chartTop;
    const plotLeft = 62;
    const plotRight = 980;
    const bandWidth = (plotRight - plotLeft) / Math.max(shipmentActivity.length, 1);
    const barWidth = Math.min(70, Math.max(1, bandWidth * 0.62));
    const labelStep = Math.max(1, Math.ceil(shipmentActivity.length / 12));
    let customRangeMax = today;
    if (customStart) {
        const rangeLimit = new Date(`${customStart}T00:00:00Z`);
        rangeLimit.setUTCDate(rangeLimit.getUTCDate() + 365);
        const endLimit = rangeLimit.toISOString().slice(0, 10);
        customRangeMax = endLimit < today ? endLimit : today;
    }

    const changeActivityPeriod = (value: string) => {
        const selected = activityPeriodOptions.find((option) => option.value === value);
        if (!selected) return;

        setActivityPeriod(selected.value);
        if (selected.value !== 'custom') {
            router.get('/dashboard', { activity_period: selected.value }, { preserveScroll: true, preserveState: true, replace: true });
        }
    };

    const applyCustomRange = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/dashboard',
            { activity_period: 'custom', activity_start: customStart, activity_end: customEnd },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

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

                <section className="border-sidebar-border/70 rounded-xl border p-5" aria-labelledby="shipment-activity-title">
                    <div className="mb-5 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 id="shipment-activity-title" className="font-semibold">Shipment activity</h2>
                            <p className="text-muted-foreground mt-1 text-sm">Parcel status updates · {activityRange.label}</p>
                        </div>
                        <div className="flex flex-wrap items-end justify-end gap-3">
                            {activityPeriod === 'custom' && (
                                <form className="flex flex-wrap items-end gap-2" onSubmit={applyCustomRange}>
                                    <label className="text-muted-foreground grid gap-1 text-xs">
                                        From
                                        <input
                                            type="date"
                                            value={customStart}
                                            max={today}
                                            onChange={(event) => setCustomStart(event.target.value)}
                                            required
                                            className="border-input bg-background text-foreground rounded-md border px-2 py-2 text-sm"
                                        />
                                    </label>
                                    <label className="text-muted-foreground grid gap-1 text-xs">
                                        To
                                        <input
                                            type="date"
                                            value={customEnd}
                                            min={customStart}
                                            max={customRangeMax}
                                            onChange={(event) => setCustomEnd(event.target.value)}
                                            required
                                            className="border-input bg-background text-foreground rounded-md border px-2 py-2 text-sm"
                                        />
                                    </label>
                                    <button
                                        type="submit"
                                        disabled={!customStart || !customEnd || customStart > customEnd}
                                        className="bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 rounded-md px-3 py-2 text-sm font-medium"
                                    >
                                        Apply
                                    </button>
                                </form>
                            )}
                            <div className="text-right">
                                <p className="text-muted-foreground text-xs">Total status updates</p>
                                <p className="font-semibold">{activityTotal.toLocaleString()}</p>
                            </div>
                            <select
                                aria-label="Shipment activity period"
                                value={activityPeriod}
                                onChange={(event) => changeActivityPeriod(event.target.value)}
                                className="border-input bg-background text-foreground rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                {activityPeriodOptions.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <svg
                        viewBox="0 0 1000 220"
                        role="img"
                        aria-label={`${activityTotal} shipment status updates ${activityPeriod === 'this-week' ? 'this week' : 'last week'}`}
                        className="h-auto w-full"
                    >
                        <defs>
                            <linearGradient id="shipment-activity-bar" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stopColor="#60a5fa" />
                                <stop offset="100%" stopColor="#2563eb" />
                            </linearGradient>
                        </defs>
                        {Array.from({ length: chartTickCount + 1 }, (_, step) => {
                            const value = (chartMax / chartTickCount) * step;
                            const y = chartBottom - (chartHeight / chartTickCount) * step;

                            return (
                                <g key={step}>
                                    <line x1="62" x2="980" y1={y} y2={y} stroke="var(--border)" strokeDasharray="3 5" />
                                    <text x="48" y={y + 4} textAnchor="end" fill="var(--muted-foreground)" fontSize="12">
                                        {Math.round(value)}
                                    </text>
                                </g>
                            );
                        })}
                        {shipmentActivity.map((day, index) => {
                            const x = plotLeft + index * bandWidth + (bandWidth - barWidth) / 2;
                            const barHeight = (day.count / chartMax) * chartHeight;
                            const date = new Date(`${day.date}T12:00:00`);
                            const showLabel = index === 0 || index === shipmentActivity.length - 1 || index % labelStep === 0;

                            return (
                                <g key={day.date}>
                                    <rect
                                        x={x}
                                        y={chartBottom - barHeight}
                                        width={barWidth}
                                        height={barHeight}
                                        rx="5"
                                        fill="url(#shipment-activity-bar)"
                                        className="transition-opacity hover:opacity-80"
                                    >
                                        <title>{`${day.count.toLocaleString()} status updates on ${date.toLocaleDateString()}`}</title>
                                    </rect>
                                    {showLabel && (
                                        <text x={x + barWidth / 2} y="204" textAnchor="middle" fill="var(--muted-foreground)" fontSize="12">
                                            {day.label}
                                        </text>
                                    )}
                                </g>
                            );
                        })}
                    </svg>
                    {activityTotal === 0 && (
                        <p className="text-muted-foreground -mt-2 text-center text-sm">No status updates recorded in this period.</p>
                    )}
                </section>

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
