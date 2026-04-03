import { Link, usePage } from '@inertiajs/react';
import { LockKeyhole, QrCode, ShieldCheck } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const highlights = [
    {
        title: 'Fast QR scanning',
        description:
            'Camera, image upload, and manual fallback in one scanner.',
        icon: QrCode,
    },
    {
        title: 'Protected access',
        description: 'Role-based tools for members, admins, and super admins.',
        icon: ShieldCheck,
    },
    {
        title: 'Secure sign in',
        description:
            'Designed for daily attendance operations and admin control.',
        icon: LockKeyhole,
    },
];

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="min-h-svh bg-[linear-gradient(145deg,_#082f49_0%,_#0f766e_34%,_#f8fafc_34%,_#eff6ff_100%)] p-3 sm:p-6 md:p-10 dark:bg-[linear-gradient(145deg,_#020617_0%,_#082f49_38%,_#0f172a_100%)]">
            <div className="mx-auto grid min-h-[calc(100svh-3rem)] max-w-7xl overflow-hidden rounded-[2rem] border border-white/60 bg-white/88 shadow-2xl shadow-cyan-950/10 backdrop-blur lg:grid-cols-[1.05fr_0.95fr] dark:border-white/10 dark:bg-slate-950/82 dark:shadow-black/30">
                <div className="relative hidden overflow-hidden bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.26),_transparent_32%),linear-gradient(145deg,_#082f49_0%,_#155e75_52%,_#0f766e_100%)] p-10 text-white lg:flex lg:flex-col">
                    <div className="absolute inset-0 bg-[linear-gradient(180deg,_transparent,_rgba(15,23,42,0.18))]" />
                    <div className="relative z-10 flex h-full flex-col">
                        <Link
                            href={home()}
                            className="inline-flex items-center gap-3 text-lg font-semibold"
                        >
                            <div className="rounded-2xl bg-white/10 p-3">
                                <AppLogoIcon className="size-7 fill-current text-white" />
                            </div>
                            {name}
                        </Link>

                        <div className="mt-14 max-w-xl space-y-5">
                            <p className="text-sm font-semibold tracking-[0.28em] text-cyan-200 uppercase">
                                Elmar's Team PH
                            </p>
                            <h2 className="text-5xl font-semibold tracking-tight">
                                QR-powered attendance for your team in one
                                place.
                            </h2>
                            <p className="text-lg text-cyan-50/85">
                                Sign in to manage users, export attendance, and
                                keep named QR identity cards ready for every
                                team member.
                            </p>
                        </div>

                        <div className="mt-auto grid gap-4">
                            {highlights.map((highlight) => (
                                <div
                                    key={highlight.title}
                                    className="rounded-[1.5rem] border border-white/10 bg-white/10 p-5 backdrop-blur"
                                >
                                    <div className="flex items-start gap-4">
                                        <div className="rounded-2xl bg-white/10 p-3">
                                            <highlight.icon className="h-5 w-5 text-cyan-100" />
                                        </div>
                                        <div>
                                            <p className="font-semibold">
                                                {highlight.title}
                                            </p>
                                            <p className="mt-1 text-sm text-cyan-50/80">
                                                {highlight.description}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="flex items-center justify-center p-6 sm:p-10">
                    <div className="w-full max-w-md">
                        <div className="mb-8 flex items-center justify-between lg:hidden">
                            <Link
                                href={home()}
                                className="inline-flex items-center gap-3 font-semibold text-slate-950 dark:text-white"
                            >
                                <div className="rounded-2xl bg-cyan-50 p-3 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-200">
                                    <AppLogoIcon className="size-6 fill-current" />
                                </div>
                                {name}
                            </Link>
                        </div>

                        <div className="mb-8 space-y-3">
                            <p className="text-sm font-semibold tracking-[0.26em] text-cyan-700 uppercase dark:text-cyan-300">
                                Secure login
                            </p>
                            <h1 className="text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-50">
                                {title}
                            </h1>
                            <p className="text-sm leading-6 text-slate-500 dark:text-slate-300">
                                {description}
                            </p>
                        </div>

                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
