import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatPeso } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';

interface StatusLog {
    id: number;
    sequence: number;
    status: string;
    occurred_at: string;
    transit_hub: { name: string } | null;
}

interface Payment {
    id: number;
    amount: string;
    payment_status: string;
    payment_method: string | null;
    paid_at: string | null;
}

interface ParcelPhoto {
    id: number;
    url: string;
}

interface Parcel {
    id: number;
    tracking_number: string;
    parcel_weight_kg: string;
    delivery_type: string;
    shipping_cost: string;
    sender: { name: string; address: string };
    recipient: { name: string; address: string };
    current_hub: { name: string } | null;
    status_logs: StatusLog[];
    payments: Payment[];
    photos: ParcelPhoto[];
}

export default function ShowParcel({ parcel }: { parcel: Parcel }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Parcels', href: route('parcels.index') },
        { title: parcel.tracking_number, href: route('parcels.show', parcel.id) },
    ];

    const deletePhoto = (photo: ParcelPhoto) => {
        if (window.confirm('Delete this parcel photo? This cannot be undone.')) {
            router.delete(route('parcels.photos.destroy', [parcel.id, photo.id]));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Parcel ${parcel.tracking_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{parcel.tracking_number}</h1>
                        <p className="text-muted-foreground text-sm">Parcel details and tracking history.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('parcels.index')}>
                                <ArrowLeft /> Back
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={route('parcels.edit', parcel.id)}>
                                <Pencil /> Edit
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader><CardTitle className="text-base">Shipment</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                            <Detail label="Delivery speed" value={parcel.delivery_type} />
                            <Detail label="Weight" value={`${Number(parcel.parcel_weight_kg).toFixed(3)} kg`} />
                            <Detail label="Current hub" value={parcel.current_hub?.name ?? '—'} />
                            <Detail label="Shipping cost" value={formatPeso(parcel.shipping_cost)} />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader><CardTitle className="text-base">Sender and recipient</CardTitle></CardHeader>
                        <CardContent className="grid gap-5 text-sm sm:grid-cols-2">
                            <div>
                                <p className="text-muted-foreground mb-1">Sender</p>
                                <p className="font-medium">{parcel.sender.name}</p>
                                <p className="text-muted-foreground">{parcel.sender.address}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground mb-1">Recipient</p>
                                <p className="font-medium">{parcel.recipient.name}</p>
                                <p className="text-muted-foreground">{parcel.recipient.address}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {parcel.photos.length > 0 && (
                    <Card>
                        <CardHeader><CardTitle className="text-base">Parcel photos</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {parcel.photos.map((photo) => (
                                <figure key={photo.id} className="group relative overflow-hidden rounded-lg border">
                                    <img
                                        src={photo.url}
                                        alt={`Photo of parcel ${parcel.tracking_number}`}
                                        className="aspect-[4/3] w-full object-cover"
                                        loading="lazy"
                                    />
                                    <figcaption className="sr-only">Parcel photo</figcaption>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="icon"
                                        className="absolute right-2 top-2 opacity-100 sm:opacity-0 sm:transition-opacity sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
                                        aria-label="Delete parcel photo"
                                        onClick={() => deletePhoto(photo)}
                                    >
                                        <Trash2 />
                                    </Button>
                                </figure>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader><CardTitle className="text-base">Tracking history</CardTitle></CardHeader>
                    <CardContent>
                        {parcel.status_logs.length ? (
                            <div className="divide-y">
                                {parcel.status_logs.map((log) => (
                                    <div key={log.id} className="flex flex-wrap justify-between gap-2 py-3 first:pt-0 last:pb-0">
                                        <div>
                                            <p className="font-medium capitalize">{log.status.replaceAll('_', ' ')}</p>
                                            <p className="text-muted-foreground text-sm">{log.transit_hub?.name ?? 'No hub recorded'}</p>
                                        </div>
                                        <time className="text-muted-foreground text-sm">{new Date(log.occurred_at).toLocaleString()}</time>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">No tracking events yet.</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">Payments</CardTitle></CardHeader>
                    <CardContent>
                        {parcel.payments.length ? (
                            <div className="divide-y">
                                {parcel.payments.map((payment) => (
                                    <div key={payment.id} className="flex flex-wrap justify-between gap-2 py-3 first:pt-0 last:pb-0">
                                        <div>
                                            <p className="font-medium capitalize">{payment.payment_status.replaceAll('_', ' ')}</p>
                                            <p className="text-muted-foreground text-sm">
                                                {payment.payment_method?.replaceAll('_', ' ') ?? 'No payment method'}
                                                {payment.paid_at ? ` · ${new Date(payment.paid_at).toLocaleString()}` : ''}
                                            </p>
                                        </div>
                                        <p className="font-medium">{formatPeso(payment.amount)}</p>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">No payments recorded.</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-muted-foreground mb-1">{label}</p>
            <p className="font-medium capitalize">{value}</p>
        </div>
    );
}
