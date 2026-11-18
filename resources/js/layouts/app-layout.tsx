import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';

interface AppLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default ({ children, breadcrumbs, ...props }: AppLayoutProps) => {
    const { props: pageProps } = usePage<{ flash?: { success?: string } }>();

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs} {...props}>
            {pageProps.flash?.success && (
                <div role="status" className="mx-4 mt-4 rounded-md border border-green-600/30 bg-green-500/10 px-4 py-3 text-sm text-green-800 dark:text-green-200 md:mx-8">
                    {pageProps.flash.success}
                </div>
            )}
            {children}
        </AppLayoutTemplate>
    );
};
