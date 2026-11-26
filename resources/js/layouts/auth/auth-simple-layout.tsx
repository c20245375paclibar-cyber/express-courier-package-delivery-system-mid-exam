import AppLogoIcon from '@/components/app-logo-icon';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <main className="courier-auth-page">
            <div className="courier-auth-card">
                <div className="courier-auth-content">
                    <div className="courier-auth-header">
                        <Link href={route('home')} className="flex flex-col items-center gap-2 font-medium">
                            <div className="courier-auth-logo">
                                <AppLogoIcon className="size-7 text-[#dce8f7]" />
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="courier-auth-heading">
                            <h1>{title}</h1>
                            <p>{description}</p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </main>
    );
}
