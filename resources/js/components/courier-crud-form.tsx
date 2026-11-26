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
    type?: 'text' | 'number' | 'datetime-local' | 'select' | 'file';
    required?: boolean;
    placeholder?: string;
    step?: string;
    accept?: string;
    multiple?: boolean;
    maxFiles?: number;
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
    values: Record<string, string | File[]>;
    fields: CrudField[];
}) {
    const form = useForm<Record<string, string | File[]>>(values);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (method === 'put' && fields.some((field) => field.type === 'file')) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(action);
            return;
        }
        form[method](action);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: resource, href: cancelHref },
                { title, href: action },
            ]}
        >
            <Head title={title} />
            <main className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 md:p-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">Fields are validated and normalized before saving.</p>
                </div>
                <form onSubmit={submit} className="bg-card grid gap-5 rounded-xl border p-5 shadow-sm">
                    {fields.map((field) => {
                        const value = form.data[field.name];

                        return (
                            <div key={field.name} className="grid gap-2">
                                <Label htmlFor={field.name}>{field.label}</Label>
                                {field.type === 'file' ? (
                                    <input
                                        id={field.name}
                                        name={field.name}
                                        type="file"
                                        accept={field.accept}
                                        multiple={field.multiple}
                                        onChange={(event) => {
                                            const files = Array.from(event.currentTarget.files ?? []);
                                            if (field.maxFiles && files.length > field.maxFiles) {
                                                form.setError(field.name, `Choose no more than ${field.maxFiles} photos at a time.`);
                                                event.currentTarget.value = '';
                                                return;
                                            }
                                            form.clearErrors(field.name);
                                            form.setData(field.name, files);
                                        }}
                                        className="border-input file:bg-muted hover:file:bg-accent bg-background rounded-md border text-sm file:mr-3 file:border-0 file:px-3 file:py-2"
                                    />
                                ) : field.type === 'select' ? (
                                    <select
                                        id={field.name}
                                        required={field.required}
                                        className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                                        value={typeof value === 'string' ? value : ''}
                                        onChange={(event) => form.setData(field.name, event.target.value)}
                                    >
                                        <option value="" disabled={field.required}>
                                            {field.required ? `Select ${field.label.toLowerCase()}` : 'None'}
                                        </option>
                                        {field.options?.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <Input
                                        id={field.name}
                                        type={field.type ?? 'text'}
                                        required={field.required}
                                        step={field.step}
                                        placeholder={field.placeholder}
                                        value={typeof value === 'string' ? value : ''}
                                        onChange={(event) => form.setData(field.name, event.target.value)}
                                    />
                                )}
                                {field.type === 'file' && (
                                    <p className="text-muted-foreground text-xs">
                                        {field.multiple ? `Up to ${field.maxFiles ?? 5} images. ` : ''}
                                        JPG, PNG, WebP or AVIF; maximum 2 MB each. Saved as compressed WebP.
                                    </p>
                                )}
                                <InputError message={form.errors[field.name] ?? form.errors[`${field.name}.0`]} />
                            </div>
                        );
                    })}
                    <InputError message={form.errors.customer} />
                    <div className="flex justify-end gap-3">
                        <Button variant="outline" type="button" asChild>
                            <Link href={cancelHref}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save'}
                        </Button>
                    </div>
                </form>
            </main>
        </AppLayout>
    );
}
