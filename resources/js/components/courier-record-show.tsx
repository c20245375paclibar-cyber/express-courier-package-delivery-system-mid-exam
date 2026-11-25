import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil } from 'lucide-react';

interface DetailField {
    label: string;
    value: string;
}

interface DetailSection {
    title: string;
    fields: DetailField[];
}

export default function CourierRecordShow({
    title,
    subtitle,
    backUrl,
    editUrl,
    sections,
}: {
    title: string;
    subtitle: string;
    backUrl: string;
    editUrl: string;
    sections: DetailSection[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: subtitle, href: backUrl },
        { title, href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />
            <main className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                        <p className="text-muted-foreground text-sm">{subtitle} details.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={backUrl}>
                                <ArrowLeft /> Back
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={editUrl}>
                                <Pencil /> Edit
                            </Link>
                        </Button>
                    </div>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    {sections.map((section) => (
                        <Card key={section.title}>
                            <CardHeader>
                                <CardTitle className="text-base">{section.title}</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                                {section.fields.map((field) => (
                                    <div key={field.label}>
                                        <p className="text-muted-foreground mb-1">{field.label}</p>
                                        <p className="font-medium whitespace-pre-line">{field.value || '—'}</p>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </main>
        </AppLayout>
    );
}
