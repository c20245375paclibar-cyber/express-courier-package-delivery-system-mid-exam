import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, LockKeyhole, PackageCheck, ShieldCheck, Truck } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import AppLogoIcon from '@/components/app-logo-icon';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <main className="courier-login-page">
            <Head title="Log in" />

            <div className="courier-login-shell">
                <aside className="courier-login-brand" aria-label="Express-Courier PDS">
                    <div className="courier-login-brand-mark">
                        <div className="courier-login-logo">
                            <AppLogoIcon className="size-7 text-[#dce8f7]" />
                        </div>
                        <span>Express-Courier PDS</span>
                    </div>

                    <div className="courier-login-brand-content">
                        <div className="courier-login-icon">
                            <Truck className="size-10" strokeWidth={1.5} />
                        </div>
                        <h2>Package delivery,<br />organized.</h2>
                        <p>Manage parcels, transit hubs, dispatches and shipment updates from one workspace.</p>
                    </div>

                    <div className="courier-login-features">
                        <span><PackageCheck className="size-4" /> Parcel tracking</span>
                        <span><ShieldCheck className="size-4" /> Secure access</span>
                    </div>
                </aside>

                <section className="courier-login-panel">
                    <div className="courier-login-heading">
                        <h1>Welcome Back</h1>
                        <p>Sign in to your courier workspace</p>
                    </div>

                    {status && <div className="courier-login-status">{status}</div>}

                    <form className="courier-login-form" onSubmit={submit}>
                        <div className="courier-login-field">
                            <Label htmlFor="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="name@company.com"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="courier-login-field">
                            <div className="courier-login-field-row">
                                <Label htmlFor="password">Password</Label>
                                {canResetPassword && (
                                    <TextLink href={route('password.request')} className="courier-login-forgot" tabIndex={5}>
                                        Forgot password?
                                    </TextLink>
                                )}
                            </div>
                            <Input
                                id="password"
                                type="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="Enter your password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="courier-login-options">
                            <div className="courier-login-remember">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                    checked={data.remember}
                                    onCheckedChange={(checked) => setData('remember', checked === true)}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>
                        </div>

                        <button type="submit" className="courier-login-submit" tabIndex={4} disabled={processing}>
                            {processing ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <LockKeyhole className="h-4 w-4" />}
                            {processing ? 'Signing in...' : 'Sign in'}
                        </button>

                        <div className="courier-login-register">
                            Don't have an account?{' '}
                            <TextLink href={route('register')} tabIndex={6}>
                                Sign up
                            </TextLink>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    );
}
