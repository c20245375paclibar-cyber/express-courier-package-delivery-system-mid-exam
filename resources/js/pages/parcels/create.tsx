import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';
import { FormEventHandler, MouseEvent } from 'react';

interface ParcelForm {
    [key: string]: string;
    tracking_number: string;
    sender_name: string;
    sender_address: string;
    recipient_name: string;
    recipient_address: string;
    parcel_weight_kg: string;
    delivery_type: string;
    current_hub_name: string;
    initial_status: string;
    status_occurred_at: string;
    status_date: string;
    status_time: string;
}

const breadcrumbs = [
    { title: 'Parcels', href: '/parcels' },
    { title: 'Create parcel', href: '/parcels/create' },
];

function localDateValue(date = new Date()) {
    const offsetDate = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
    return offsetDate.toISOString().slice(0, 10);
}

function dateAfter(days: number) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    const offsetDate = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
    return offsetDate.toISOString().slice(0, 10);
}

function openNativePicker(event: MouseEvent<HTMLInputElement>) {
    try {
        event.currentTarget.showPicker();
    } catch {
        // The browser may not support showPicker; the native input remains usable.
    }
}

export default function CreateParcel({ generated_tracking_number }: { generated_tracking_number: string }) {
    const { data, setData, post, processing, errors, transform } = useForm<ParcelForm>({
        tracking_number: generated_tracking_number,
        sender_name: '',
        sender_address: '',
        recipient_name: '',
        recipient_address: '',
        parcel_weight_kg: '',
        delivery_type: 'standard',
        current_hub_name: '',
        initial_status: 'received',
        status_occurred_at: '',
        status_date: '',
        status_time: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        transform((formData) => ({
            ...formData,
            status_occurred_at: formData.status_date
                ? `${formData.status_date}T${formData.status_time || '00:00'}`
                : '',
        }));
        post(route('parcels.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create parcel" />
            <main className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={route('parcels.index')} aria-label="Back to parcels">
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Create a parcel</h1>
                        <p className="text-sm text-muted-foreground">Add the shipment details and its first tracking event.</p>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6 rounded-xl border bg-card p-5 shadow-sm md:p-7">
                    <section className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="tracking_number">Tracking number</Label>
                            <Input
                                id="tracking_number"
                                required
                                value={data.tracking_number}
                                onChange={(event) => setData('tracking_number', event.target.value)}
                                placeholder="e.g. CXP-2026-001"
                            />
                            <InputError message={errors.tracking_number} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="sender_name">Sender name</Label>
                            <Input id="sender_name" required value={data.sender_name} onChange={(event) => setData('sender_name', event.target.value)} />
                            <InputError message={errors.sender_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="recipient_name">Recipient name</Label>
                            <Input id="recipient_name" required value={data.recipient_name} onChange={(event) => setData('recipient_name', event.target.value)} />
                            <InputError message={errors.recipient_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="sender_address">Sender address</Label>
                            <Input id="sender_address" required value={data.sender_address} onChange={(event) => setData('sender_address', event.target.value)} placeholder="Street, city, region" />
                            <InputError message={errors.sender_address} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="recipient_address">Recipient address</Label>
                            <Input id="recipient_address" required value={data.recipient_address} onChange={(event) => setData('recipient_address', event.target.value)} placeholder="Street, city, region" />
                            <InputError message={errors.recipient_address} />
                        </div>
                    </section>

                    <section className="grid gap-4 border-t pt-6 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="parcel_weight_kg">Weight (kg)</Label>
                            <Input id="parcel_weight_kg" type="number" min="0.001" step="0.001" required value={data.parcel_weight_kg} onChange={(event) => setData('parcel_weight_kg', event.target.value)} placeholder="1.250" />
                            <InputError message={errors.parcel_weight_kg} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="delivery_type">Delivery speed</Label>
                            <select id="delivery_type" className="h-10 rounded-md border border-input bg-background px-3 text-sm" value={data.delivery_type} onChange={(event) => setData('delivery_type', event.target.value)}>
                                <option value="standard">Standard (base rate)</option>
                                <option value="express">Express (1.5× rate)</option>
                                <option value="overnight">Overnight (2.5× rate)</option>
                            </select>
                            <InputError message={errors.delivery_type} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="current_hub_name">Current transit hub</Label>
                            <Input id="current_hub_name" value={data.current_hub_name} onChange={(event) => setData('current_hub_name', event.target.value)} placeholder="Optional" />
                            <InputError message={errors.current_hub_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="initial_status">Initial shipment status</Label>
                            <select id="initial_status" className="h-10 rounded-md border border-input bg-background px-3 text-sm" value={data.initial_status} onChange={(event) => setData('initial_status', event.target.value)}>
                                <option value="received">Received</option>
                                <option value="picked_up">Picked up</option>
                                <option value="in_transit">In transit</option>
                                <option value="out_for_delivery">Out for delivery</option>
                                <option value="delivered">Delivered</option>
                                <option value="exception">Exception</option>
                            </select>
                            <InputError message={errors.initial_status} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="status_occurred_at">Status date and time (optional)</Label>
                            <div className="flex flex-wrap gap-2">
                                <Button type="button" variant="outline" size="sm" onClick={() => setData('status_date', dateAfter(0))}>
                                    Today
                                </Button>
                                <Button type="button" variant="outline" size="sm" onClick={() => setData('status_date', dateAfter(1))}>
                                    Tomorrow
                                </Button>
                                <Button type="button" variant="outline" size="sm" onClick={() => setData('status_date', dateAfter(7))}>
                                    Next week
                                </Button>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="grid gap-1">
                                    <Label htmlFor="status_date" className="text-xs text-muted-foreground">Date</Label>
                                    <Input
                                        id="status_date"
                                        type="date"
                                        min={localDateValue()}
                                        step="1"
                                        value={data.status_date}
                                        onChange={(event) => setData('status_date', event.target.value)}
                                        onClick={openNativePicker}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="status_time" className="text-xs text-muted-foreground">Time</Label>
                                    <Input
                                        id="status_time"
                                        type="time"
                                        step="60"
                                        value={data.status_time}
                                        onChange={(event) => setData('status_time', event.target.value)}
                                    />
                                </div>
                            </div>
                            <InputError message={errors.status_occurred_at} />
                        </div>
                    </section>

                    <p className="text-sm text-muted-foreground">Shipping is calculated automatically: weight × $5/kg × delivery speed. A pending payment and the first status log are created with the parcel.</p>
                    <div className="flex justify-end gap-3">
                        <Button variant="outline" type="button" asChild>
                            <Link href={route('parcels.index')}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="animate-spin" />}
                            Create parcel
                        </Button>
                    </div>
                </form>
            </main>
        </AppLayout>
    );
}
