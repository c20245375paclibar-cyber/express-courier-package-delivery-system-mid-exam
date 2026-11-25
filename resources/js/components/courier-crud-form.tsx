import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export interface CrudField {
    name: string;
    label: string;
    type?: 'text' | 'number' | 'datetime-local' | 'select';
    required?: boolean;
    placeholder?: string;
    step?: string;
    options?: Array<{ value: string; label: string }>;
}

export default function CourierCrudForm({
    title,
    resource,
    action,
    method,
    cancelHref,
    values,
    fields,
}: {
    title: string;
    resource: string;
    action: string;
    method: 'post' | 'put';
    cancelHref: string;
    values: Record<string, string>;
    fields: CrudField[];
}) {
    const form = useForm<Record<string, string>>(values);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form[method](action);
    };

    return (
        <AppLayout breadcrumbs={[{ title: resource, href: cancelHref }, { title, href: action }]}>
            <Head title={title} />
            <main className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 md:p-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">Fields are validated and normalized before saving.</p>
                </div>
                <form onSubmit={submit} className="grid gap-5 rounded-xl border bg-card p-5 shadow-sm">
                    {fields.map((field) => (
                        <div key={field.name} className="grid gap-2">
                            <Label htmlFor={field.name}>{field.label}</Label>
                            {field.type === 'select' ? (
                                <select
                                    id={field.name}
                                    required={field.required}
                                    className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                    value={form.data[field.name] ?? ''}
                                    onChange={(event) => form.setData(field.name, event.target.value)}
                                >
                                    <option value="" disabled={field.required}>
                                        {field.required ? `Select ${field.label.toLowerCase()}` : 'None'}
                                    </option>
                                    {field.options?.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                </select>
                            ) : (
                                <Input
                                    id={field.name}
                                    type={field.type ?? 'text'}
                                    required={field.required}
                                    step={field.step}
                                    placeholder={field.placeholder}
                                    value={form.data[field.name] ?? ''}
                                    onChange={(event) => form.setData(field.name, event.target.value)}
                                />
                            )}
                            <InputError message={form.errors[field.name]} />
                        </div>
                    ))}
                    <InputError message={form.errors.customer} />
                    <div className="flex justify-end gap-3">
                        <Button variant="outline" type="button" asChild><Link href={cancelHref}>Cancel</Link></Button>
                        <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save'}</Button>
                    </div>
                </form>
            </main>
        </AppLayout>
    );
}
