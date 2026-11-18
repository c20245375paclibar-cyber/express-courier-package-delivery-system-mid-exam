import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

export interface ResourceRow {
    id: number;
    values: string[];
}

interface Page<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export default function CourierResourceIndex({
    title,
    description,
    resource,
    indexUrl,
    createUrl,
    editUrl,
    deleteUrl,
    columns,
    rows,
    search: initialSearch,
}: {
    title: string;
    description: string;
    resource: string;
    indexUrl: string;
    createUrl: string;
    editUrl: (id: number) => string;
    deleteUrl: (id: number) => string;
    columns: string[];
    rows: Page<ResourceRow>;
    search: string;
}) {
    const [search, setSearch] = useState(initialSearch);
    const { props } = usePage<{ errors: Record<string, string> }>();
    const errors = Object.values(props.errors ?? {});

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(indexUrl, { search }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={[{ title: resource, href: indexUrl }]}>
            <Head title={title} />
            <main className="flex flex-col gap-6 p-4 md:p-8">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">{description}</p>
                    </div>
                    <Button asChild><Link href={createUrl}>Add {resource.toLowerCase().replace(/s$/, '')}</Link></Button>
                </div>
                {errors.length > 0 && <p role="alert" className="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">{errors.join(' ')}</p>}
                <form onSubmit={submitSearch} className="flex w-full gap-2 sm:max-w-md">
                    <Input aria-label={`Search ${resource}`} placeholder={`Search ${resource.toLowerCase()}`} value={search} onChange={(event) => setSearch(event.target.value)} />
                    <Button type="submit" variant="secondary">Search</Button>
                    {initialSearch && <Button type="button" variant="outline" onClick={() => {
                        setSearch('');
                        router.get(indexUrl, {}, { preserveState: true, replace: true });
                    }}>Clear</Button>}
                </form>
                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {columns.map((column) => <TableHead key={column}>{column}</TableHead>)}
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.data.length ? rows.data.map((row) => (
                                <TableRow key={row.id}>
                                    {row.values.map((value, index) => <TableCell key={`${row.id}-${index}`}>{value || '—'}</TableCell>)}
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button variant="outline" size="sm" asChild><Link href={editUrl(row.id)}>Edit</Link></Button>
                                            <Button variant="destructive" size="sm" onClick={() => {
                                                if (window.confirm(`Delete this ${resource.toLowerCase().replace(/s$/, '')}?`)) {
                                                    router.delete(deleteUrl(row.id));
                                                }
                                            }}>Delete</Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )) : (
                                <TableRow><TableCell colSpan={columns.length + 1} className="h-32 text-center text-muted-foreground">No {resource.toLowerCase()} found.</TableCell></TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    {rows.prev_page_url ? <Button variant="outline" size="sm" asChild><Link href={rows.prev_page_url}>Previous</Link></Button> : <span />}
                    <span>Page {rows.current_page} of {rows.last_page}</span>
                    {rows.next_page_url ? <Button variant="outline" size="sm" asChild><Link href={rows.next_page_url}>Next</Link></Button> : <span />}
                </div>
            </main>
        </AppLayout>
    );
}
