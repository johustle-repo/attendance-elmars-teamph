import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Database, QrCode, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    scanUrl: string;
};

const features = [
    {
        title: 'QR-based check-in',
        description: 'Scan member QR codes to record attendance in seconds.',
        icon: QrCode,
    },
    {
        title: 'Firebase-ready storage',
        description: 'Attendance records can sync to an online backup when Firebase is configured.',
        icon: Database,
    },
    {
        title: 'Role-based access',
        description: 'Authenticated accounts only see the tools assigned to their role.',
        icon: ShieldCheck,
    },
    {
        title: 'Excel export',
        description: 'Download attendance reports in an Excel-compatible format any time.',
        icon: CalendarDays,
    },
];

export default function Welcome({ scanUrl }: Props) {
    return (
        <>
            <Head title="Elmar's Team PH" />

            <div className="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.22),_transparent_30%),linear-gradient(135deg,_#f8fffe_0%,_#f3f7ff_45%,_#eefcf8_100%)] text-slate-950">
                <div className="mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 lg:px-10">
                    <header className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-[0.28em] text-cyan-700">
                                Elmar's Team
                            </p>
                            <h1 className="text-2xl font-semibold">
                                PH Attendance
                            </h1>
                        </div>

                        <div className="flex gap-3">
                            <Button asChild variant="outline">
                                <Link href="/login">Admin Login</Link>
                            </Button>
                            <Button asChild className="bg-slate-950 text-white hover:bg-slate-800">
                                <Link href={scanUrl}>Open Scanner</Link>
                            </Button>
                        </div>
                    </header>

                    <main className="grid flex-1 items-center gap-10 py-10 lg:grid-cols-[1.15fr_0.85fr]">
                        <section className="space-y-8">
                            <div className="space-y-5">
                                <div className="inline-flex items-center rounded-full border border-cyan-200 bg-white/80 px-4 py-1 text-sm font-medium text-cyan-900 shadow-sm backdrop-blur">
                                    Simple standalone Laravel attendance platform
                                </div>
                                <div className="space-y-4">
                                    <h2 className="max-w-3xl text-5xl leading-tight font-semibold tracking-tight">
                                        Track attendance for Elmar's Team PH
                                        with QR scanning, role-based controls,
                                        and Firebase-ready sync.
                                    </h2>
                                    <p className="max-w-2xl text-lg text-slate-600">
                                        Built for quick daily check-ins, role-aware
                                        dashboards, and export-ready attendance
                                        reporting without exposing internal setup
                                        details on the public landing page.
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-4">
                                <Button asChild size="lg" className="bg-cyan-600 text-white hover:bg-cyan-700">
                                    <Link href={scanUrl}>Start QR Scan</Link>
                                </Button>
                                <Button asChild size="lg" variant="outline">
                                    <Link href="/login">Manage Dashboard</Link>
                                </Button>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                {features.map((feature) => (
                                    <Card
                                        key={feature.title}
                                        className="border-white/70 bg-white/85 shadow-lg shadow-cyan-950/5 backdrop-blur"
                                    >
                                        <CardHeader className="space-y-3">
                                            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-500 to-emerald-500 text-white">
                                                <feature.icon className="h-6 w-6" />
                                            </div>
                                            <CardTitle>{feature.title}</CardTitle>
                                            <CardDescription>
                                                {feature.description}
                                            </CardDescription>
                                        </CardHeader>
                                    </Card>
                                ))}
                            </div>
                        </section>

                        <aside className="rounded-[2rem] border border-white/70 bg-slate-950 p-8 text-white shadow-2xl shadow-slate-950/15">
                            <div className="space-y-6">
                                <div>
                                    <p className="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-300">
                                        Secure access
                                    </p>
                                    <h3 className="mt-2 text-3xl font-semibold">
                                        Public-safe entry point
                                    </h3>
                                </div>

                                <div className="space-y-4">
                                    <Card className="border-white/10 bg-white/5 text-white shadow-none">
                                        <CardHeader>
                                            <CardTitle>Scanner station</CardTitle>
                                            <CardDescription className="text-slate-300">
                                                Open the QR scanner directly for
                                                fast attendance recording.
                                            </CardDescription>
                                        </CardHeader>
                                    </Card>

                                    <Card className="border-white/10 bg-white/5 text-white shadow-none">
                                        <CardHeader>
                                            <CardTitle>Protected dashboard</CardTitle>
                                            <CardDescription className="text-slate-300">
                                                Administrative tools stay behind
                                                authentication and role checks.
                                            </CardDescription>
                                        </CardHeader>
                                    </Card>
                                </div>

                                <div className="rounded-3xl border border-cyan-400/20 bg-cyan-400/10 p-5">
                                    <p className="text-sm font-medium text-cyan-100">
                                        Use the scanner for attendance, then log
                                        in with an assigned account to manage the
                                        protected workspace.
                                    </p>
                                </div>
                            </div>
                        </aside>
                    </main>
                </div>
            </div>
        </>
    );
}
