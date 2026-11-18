import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Archive, ArchiveRestore, Trash2 } from 'lucide-react';
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
    restoreUrl,
    archivable = true,
    columns,
    rows,
    search: initialSearch,
    archived = false,
}: {
    title: string;
    description: string;
    resource: string;
    indexUrl: string;
    createUrl: string;
    editUrl: (id: number) => string;
    deleteUrl: (id: number) => string;
    restoreUrl?: (id: number) => string;
    archivable?: boolean;
    columns: string[];
    rows: Page<ResourceRow>;
    search: string;
    archived: boolean;
}) {
    const [search, setSearch] = useState(initialSearch);
    const { props } = usePage<{ errors: Record<string, string> }>();
    const errors = Object.values(props.errors ?? {});

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(indexUrl, { search, archived: archived ? 1 : undefined }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={[{ title: resource, href: indexUrl }]}>
            <Head title={title} />
            <main className="flex flex-col gap-6 p-4 md:p-8">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">{description}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {archivable && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.get(
                                        indexUrl,
                                        { search: search || undefined, archived: archived ? undefined : 1 },
                                        { preserveState: true, replace: true },
                                    )
                                }
                            >
                                {archived ? 'View active' : 'View archive'}
                            </Button>
                        )}
                        {!archived && (
                            <Button asChild>
                                <Link href={createUrl}>Add {resource.toLowerCase().replace(/s$/, '')}</Link>
                            </Button>
                        )}
                    </div>
                </div>
                {errors.length > 0 && (
                    <p role="alert" className="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-4 py-3 text-sm">
                        {errors.join(' ')}
                    </p>
                )}
                <form onSubmit={submitSearch} className="flex w-full gap-2 sm:max-w-md">
                    <Input
                        aria-label={`Search ${resource}`}
                        placeholder={`Search ${resource.toLowerCase()}`}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                    {initialSearch && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setSearch('');
                                router.get(indexUrl, { archived: archived ? 1 : undefined }, { preserveState: true, replace: true });
                            }}
                        >
                            Clear
                        </Button>
                    )}
                </form>
                <div className="bg-card overflow-hidden rounded-xl border shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {columns.map((column) => (
                                    <TableHead key={column}>{column}</TableHead>
                                ))}
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.data.length ? (
                                rows.data.map((row) => (
                                    <TableRow key={row.id}>
                                        {row.values.map((value, index) => (
                                            <TableCell key={`${row.id}-${index}`}>{value || '—'}</TableCell>
                                        ))}
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                {archivable && archived && restoreUrl ? (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => router.post(restoreUrl(row.id), {}, { preserveScroll: true })}
                                                    >
                                                        <ArchiveRestore /> Restore
                                                    </Button>
                                                ) : (
                                                    <>
                                                        <Button variant="outline" size="sm" asChild>
                                                            <Link href={editUrl(row.id)}>Edit</Link>
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => {
                                                                const message = archivable
                                                                    ? `Archive this ${resource.toLowerCase().replace(/s$/, '')}? You can restore it later.`
                                                                    : `Delete this ${resource.toLowerCase().replace(/s$/, '')}? This action cannot be undone.`;
                                                                if (window.confirm(message)) {
                                                                    router.delete(deleteUrl(row.id));
                                                                }
                                                            }}
                                                        >
                                                            {archivable ? <Archive /> : <Trash2 />}
                                                            {archivable ? 'Archive' : 'Delete'}
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={columns.length + 1} className="text-muted-foreground h-32 text-center">
                                        No {resource.toLowerCase()} found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
                <div className="text-muted-foreground flex items-center justify-between text-sm">
                    {rows.prev_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={rows.prev_page_url}>Previous</Link>
                        </Button>
                    ) : (
                        <span />
                    )}
                    <span>
                        Page {rows.current_page} of {rows.last_page}
                    </span>
                    {rows.next_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={rows.next_page_url}>Next</Link>
                        </Button>
                    ) : (
                        <span />
                    )}
                </div>
            </main>
        </AppLayout>
    );
}
