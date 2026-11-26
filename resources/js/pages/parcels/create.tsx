import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';
import { FormEventHandler, MouseEvent } from 'react';

interface ParcelForm {
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
    photos: File[];
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

export default function CreateParcel({
    generated_tracking_number,
    deliveryTypes,
    shipmentStatuses,
}: {
    generated_tracking_number: string;
    deliveryTypes: string[];
    shipmentStatuses: string[];
}) {
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
        photos: [],
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        transform((formData) => ({
            tracking_number: formData.tracking_number.trim(),
            sender_name: formData.sender_name.trim(),
            sender_address: formData.sender_address.trim(),
            recipient_name: formData.recipient_name.trim(),
            recipient_address: formData.recipient_address.trim(),
            parcel_weight_kg: formData.parcel_weight_kg.trim(),
            delivery_type: formData.delivery_type,
            current_hub_name: formData.current_hub_name.trim(),
            initial_status: formData.initial_status,
            status_occurred_at: formData.status_date ? `${formData.status_date}T${formData.status_time || '00:00'}` : '',
            photos: formData.photos,
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
                        <p className="text-muted-foreground text-sm">Add the shipment details and its first tracking event.</p>
                    </div>
                </div>

                <ol
                    aria-label="Parcel creation steps"
                    className="bg-muted/30 flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <li className="order-first flex flex-1 items-center gap-3">
                        <span className="bg-primary text-primary-foreground grid size-9 shrink-0 place-items-center rounded-full text-sm font-semibold">
                            01
                        </span>
                        <span>
                            <span className="block text-sm font-medium">Parcel &amp; people</span>
                            <span className="text-muted-foreground block text-xs">Tracking and contact details</span>
                        </span>
                    </li>
                    <li className="order-2 flex flex-1 items-center gap-3">
                        <span className="bg-background grid size-9 shrink-0 place-items-center rounded-full border text-sm font-semibold">02</span>
                        <span>
                            <span className="block text-sm font-medium">Delivery</span>
                            <span className="text-muted-foreground block text-xs">Weight, speed and hub</span>
                        </span>
                    </li>
                    <li className="order-last flex flex-1 items-center gap-3">
                        <span className="bg-background grid size-9 shrink-0 place-items-center rounded-full border text-sm font-semibold">03</span>
                        <span>
                            <span className="block text-sm font-medium">First update</span>
                            <span className="text-muted-foreground block text-xs">Status and received time</span>
                        </span>
                    </li>
                </ol>

                <form onSubmit={submit} className="bg-card space-y-6 rounded-xl border p-5 shadow-sm md:p-7">
                    <section className="space-y-4">
                        <div className="flex items-center gap-3">
                            <span className="bg-primary/10 text-primary grid size-8 place-items-center rounded-lg text-xs font-semibold">01</span>
                            <div>
                                <h2 className="text-sm font-semibold">Parcel and contact details</h2>
                                <p className="text-muted-foreground text-xs">Who is sending this parcel, and where is it going?</p>
                            </div>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
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
                                <Input
                                    id="sender_name"
                                    required
                                    value={data.sender_name}
                                    onChange={(event) => setData('sender_name', event.target.value)}
                                />
                                <InputError message={errors.sender_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="recipient_name">Recipient name</Label>
                                <Input
                                    id="recipient_name"
                                    required
                                    value={data.recipient_name}
                                    onChange={(event) => setData('recipient_name', event.target.value)}
                                />
                                <InputError message={errors.recipient_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="sender_address">Sender address</Label>
                                <Input
                                    id="sender_address"
                                    required
                                    value={data.sender_address}
                                    onChange={(event) => setData('sender_address', event.target.value)}
                                    placeholder="Street, city, region"
                                />
                                <InputError message={errors.sender_address} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="recipient_address">Recipient address</Label>
                                <Input
                                    id="recipient_address"
                                    required
                                    value={data.recipient_address}
                                    onChange={(event) => setData('recipient_address', event.target.value)}
                                    placeholder="Street, city, region"
                                />
                                <InputError message={errors.recipient_address} />
                            </div>
                        </div>
                        <div className="grid gap-2 border-t pt-4">
                            <Label htmlFor="photos">Parcel photos (optional)</Label>
                            <Input
                                id="photos"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/avif"
                                multiple
                                onChange={(event) => setData('photos', Array.from(event.target.files ?? []))}
                                disabled={processing}
                            />
                            <p className="text-muted-foreground text-xs">
                                Choose up to 5 images. Each can be up to 2 MB. JPG, PNG, WebP and AVIF are converted to compressed WebP.
                            </p>
                            {data.photos.length > 0 && (
                                <ul className="text-muted-foreground list-inside list-disc text-xs">
                                    {data.photos.map((photo) => (
                                        <li key={`${photo.name}-${photo.lastModified}`}>{photo.name}</li>
                                    ))}
                                </ul>
                            )}
                            <InputError message={errors.photos ?? errors['photos.0']} />
                        </div>
                    </section>

                    <section className="space-y-4 border-t pt-6">
                        <div className="flex items-center gap-3">
                            <span className="bg-muted grid size-8 place-items-center rounded-lg text-xs font-semibold">02</span>
                            <div>
                                <h2 className="text-sm font-semibold">Delivery details</h2>
                                <p className="text-muted-foreground text-xs">Choose the service speed and enter the parcel weight.</p>
                            </div>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="parcel_weight_kg">Weight (kg)</Label>
                                <Input
                                    id="parcel_weight_kg"
                                    type="number"
                                    min="0.001"
                                    step="0.001"
                                    required
                                    value={data.parcel_weight_kg}
                                    onChange={(event) => setData('parcel_weight_kg', event.target.value)}
                                    placeholder="1.250"
                                />
                                <InputError message={errors.parcel_weight_kg} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="delivery_type">Delivery speed</Label>
                                <select
                                    id="delivery_type"
                                    className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                                    value={data.delivery_type}
                                    onChange={(event) => setData('delivery_type', event.target.value)}
                                >
                                    {deliveryTypes.map((type) => (
                                        <option key={type} value={type}>
                                            {type === 'standard'
                                                ? 'Standard (base rate)'
                                                : `${type[0].toUpperCase()}${type.slice(1)} (${type === 'express' ? '1.5' : '2.5'}× rate)`}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.delivery_type} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="current_hub_name">Current transit hub</Label>
                                <Input
                                    id="current_hub_name"
                                    value={data.current_hub_name}
                                    onChange={(event) => setData('current_hub_name', event.target.value)}
                                    placeholder="Optional"
                                />
                                <InputError message={errors.current_hub_name} />
                            </div>
                        </div>
                    </section>

                    <section className="space-y-4 border-t pt-6">
                        <div className="flex items-center gap-3">
                            <span className="bg-muted grid size-8 place-items-center rounded-lg text-xs font-semibold">03</span>
                            <div>
                                <h2 className="text-sm font-semibold">First tracking update</h2>
                                <p className="text-muted-foreground text-xs">Set the parcel’s starting status and when it was received.</p>
                            </div>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="initial_status">Initial shipment status</Label>
                                <select
                                    id="initial_status"
                                    className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                                    value={data.initial_status}
                                    onChange={(event) => setData('initial_status', event.target.value)}
                                >
                                    {shipmentStatuses.map((status) => (
                                        <option key={status} value={status}>
                                            {status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())}
                                        </option>
                                    ))}
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
                                        <Label htmlFor="status_date" className="text-muted-foreground text-xs">
                                            Date
                                        </Label>
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
                                        <Label htmlFor="status_time" className="text-muted-foreground text-xs">
                                            Time
                                        </Label>
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
                        </div>
                    </section>

                    <p className="text-muted-foreground text-sm">
                        Shipping is calculated automatically: weight × ₱5/kg × delivery speed. A pending payment and the first status log are created
                        with the parcel.
                    </p>
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
