import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';
import { FormEventHandler, MouseEvent } from 'react';

interface StatusLogForm {
    [key: string]: string;
    parcel_id: string;
    status: string;
    current_hub_name: string;
    occurred_at: string;
    occurred_date: string;
    occurred_time: string;
}

interface ParcelOption {
    id: number;
    tracking_number: string;
}

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

export default function CreateShipmentStatusLog({ parcels }: { parcels: ParcelOption[] }) {
    const { data, setData, post, processing, errors, transform } = useForm<StatusLogForm>({
        parcel_id: '',
        status: 'in_transit',
        current_hub_name: '',
        occurred_at: '',
        occurred_date: '',
        occurred_time: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        transform((formData) => ({
            ...formData,
            occurred_at: formData.occurred_date
                ? `${formData.occurred_date}T${formData.occurred_time || '00:00'}`
                : '',
        }));
        post(route('shipment-status-logs.store'));
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Status history', href: '/shipment-status-logs' }, { title: 'Add status', href: '/shipment-status-logs/create' }]}>
            <Head title="Add shipment status" />
            <main className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 md:p-8">
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" asChild><Link href={route('shipment-status-logs.index')} aria-label="Back to status history"><ArrowLeft /></Link></Button>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Add shipment status</h1>
                        <p className="text-sm text-muted-foreground">New events are assigned the next sequence number automatically.</p>
                    </div>
                </div>
                <form onSubmit={submit} className="grid gap-5 rounded-xl border bg-card p-5 shadow-sm">
                    <div className="grid gap-2">
                        <Label htmlFor="parcel_id">Parcel</Label>
                        <select id="parcel_id" required className="h-10 rounded-md border border-input bg-background px-3 text-sm" value={data.parcel_id} onChange={(event) => setData('parcel_id', event.target.value)}>
                            <option value="">Select a tracking number</option>
                            {parcels.map((parcel) => <option key={parcel.id} value={parcel.id}>{parcel.tracking_number}</option>)}
                        </select>
                        <InputError message={errors.parcel_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <select id="status" className="h-10 rounded-md border border-input bg-background px-3 text-sm" value={data.status} onChange={(event) => setData('status', event.target.value)}>
                            <option value="received">Received</option>
                            <option value="picked_up">Picked up</option>
                            <option value="in_transit">In transit</option>
                            <option value="out_for_delivery">Out for delivery</option>
                            <option value="delivered">Delivered</option>
                            <option value="exception">Exception</option>
                        </select>
                        <InputError message={errors.status} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="current_hub_name">Current transit hub</Label>
                        <Input id="current_hub_name" value={data.current_hub_name} onChange={(event) => setData('current_hub_name', event.target.value)} placeholder="Optional" />
                        <InputError message={errors.current_hub_name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="occurred_at">Date and time (optional)</Label>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => setData('occurred_date', dateAfter(0))}>Today</Button>
                            <Button type="button" variant="outline" size="sm" onClick={() => setData('occurred_date', dateAfter(1))}>Tomorrow</Button>
                            <Button type="button" variant="outline" size="sm" onClick={() => setData('occurred_date', dateAfter(7))}>Next week</Button>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1">
                                <Label htmlFor="occurred_date" className="text-xs text-muted-foreground">Date</Label>
                                <Input
                                    id="occurred_date"
                                    type="date"
                                    min={localDateValue()}
                                    value={data.occurred_date}
                                    onChange={(event) => setData('occurred_date', event.target.value)}
                                    onClick={openNativePicker}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="occurred_time" className="text-xs text-muted-foreground">Time</Label>
                                <Input
                                    id="occurred_time"
                                    type="time"
                                    step="60"
                                    value={data.occurred_time}
                                    onChange={(event) => setData('occurred_time', event.target.value)}
                                />
                            </div>
                        </div>
                        <InputError message={errors.occurred_at} />
                    </div>
                    <div className="flex justify-end gap-3">
                        <Button variant="outline" type="button" asChild><Link href={route('shipment-status-logs.index')}>Cancel</Link></Button>
                        <Button type="submit" disabled={processing || parcels.length === 0}>
                            {processing && <LoaderCircle className="animate-spin" />}
                            Add status
                        </Button>
                    </div>
                    {parcels.length === 0 && <p className="text-sm text-muted-foreground">Create a parcel before adding status events.</p>}
                </form>
            </main>
        </AppLayout>
    );
}
