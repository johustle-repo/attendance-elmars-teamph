import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    Database,
    Download,
    LogIn,
    LogOut,
    QrCode,
    ShieldCheck,
    Sparkles,
    Users,
} from 'lucide-react';
import { FlashMessage } from '@/components/flash-message';
import { QrIdentityCard } from '@/components/qr-identity-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type {
    AttendanceItem,
    BreadcrumbItem,
    DashboardStats,
    Flash,
    MemberSummary,
    User,
} from '@/types';

type Props = {
    canManageUsers: boolean;
    stats: DashboardStats | null;
    memberSummary: MemberSummary | null;
    recentAttendances: AttendanceItem[];
    myQrValue?: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard({
    canManageUsers,
    stats,
    memberSummary,
    recentAttendances,
    myQrValue,
}: Props) {
    const { auth, flash } = usePage().props as {
        auth: { user: User };
        flash: Flash;
    };

    const roleLabel =
        auth.user.role === 'super_admin'
            ? 'Super Admin'
            : auth.user.role === 'admin'
              ? 'Admin'
              : 'Member';

    const statCards = canManageUsers
        ? [
              {
                  label: 'Total users',
                  value: stats?.totalUsers ?? 0,
                  icon: Users,
                  accent: 'from-cyan-500 to-sky-500',
              },
              {
                  label: 'Attendance today',
                  value: stats?.attendanceToday ?? 0,
                  icon: CalendarDays,
                  accent: 'from-emerald-500 to-teal-500',
              },
              {
                  label: 'Present today',
                  value: stats?.presentToday ?? 0,
                  icon: ShieldCheck,
                  accent: 'from-amber-500 to-orange-500',
              },
              {
                  label: 'Admin accounts',
                  value: stats?.totalAdmins ?? 0,
                  icon: Database,
                  accent: 'from-violet-500 to-indigo-500',
              },
          ]
        : [
              {
                  label: 'My total scans',
                  value: memberSummary?.totalAttendances ?? 0,
                  icon: Users,
                  accent: 'from-cyan-500 to-sky-500',
              },
              {
                  label: 'My scans today',
                  value: memberSummary?.todayAttendances ?? 0,
                  icon: CalendarDays,
                  accent: 'from-emerald-500 to-teal-500',
              },
              {
                  label: 'Last action',
                  value: memberSummary?.lastEntryTypeLabel ?? 'No scan yet',
                  icon:
                      memberSummary?.lastEntryType === 'time_out'
                          ? LogOut
                          : LogIn,
                  accent: 'from-amber-500 to-orange-500',
              },
              {
                  label: 'Firebase sync',
                  value: memberSummary?.firebaseConfigured
                      ? 'Connected'
                      : 'Pending',
                  icon: Database,
                  accent: 'from-violet-500 to-indigo-500',
              },
          ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 bg-[linear-gradient(180deg,_rgba(236,254,255,0.45)_0%,_rgba(248,250,252,0)_26%)] p-4 md:p-6">
                <FlashMessage flash={flash} />

                <section className="overflow-hidden rounded-[2rem] border border-cyan-100 bg-[linear-gradient(135deg,_#082f49_0%,_#0f766e_48%,_#14b8a6_100%)] p-8 text-white shadow-xl shadow-cyan-950/10">
                    <div className="grid gap-8 xl:grid-cols-[1.08fr_0.92fr]">
                        <div className="space-y-6">
                            <div className="flex flex-wrap items-center gap-3">
                                <Badge className="bg-white/15 text-white">
                                    {roleLabel}
                                </Badge>
                                <Badge className="bg-black/15 text-cyan-50">
                                    <Sparkles className="mr-1 h-3 w-3" />
                                    Attendance workspace
                                </Badge>
                            </div>

                            <div className="space-y-4">
                                <h1 className="max-w-3xl text-4xl font-semibold tracking-tight md:text-5xl">
                                    Welcome back, {auth.user.name.split(' ')[0]}.
                                </h1>
                                <p className="max-w-2xl text-base text-cyan-50/90 md:text-lg">
                                    {canManageUsers
                                        ? 'Manage users, monitor live attendance, export reports, and keep QR identities ready for fast check-ins.'
                                        : 'Your QR identity card is ready below so you can scan in quickly and keep your attendance record up to date.'}
                                </p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="rounded-[1.5rem] border border-white/15 bg-white/10 p-5 backdrop-blur">
                                    <p className="text-sm uppercase tracking-[0.2em] text-cyan-100/80">
                                        Firebase sync
                                    </p>
                                    <div className="mt-3 flex items-center justify-between">
                                        <div>
                                            <p className="text-lg font-semibold">
                                                {(canManageUsers
                                                    ? stats?.firebaseConfigured
                                                    : memberSummary?.firebaseConfigured)
                                                    ? 'Connected'
                                                    : 'Pending setup'}
                                            </p>
                                            <p className="text-sm text-cyan-50/75">
                                                Online backup for users and attendance
                                            </p>
                                        </div>
                                        <Database className="h-8 w-8 text-cyan-100" />
                                    </div>
                                </div>

                                <div className="rounded-[1.5rem] border border-white/15 bg-black/10 p-5 backdrop-blur">
                                    <p className="text-sm uppercase tracking-[0.2em] text-cyan-100/80">
                                        Quick action
                                    </p>
                                    <div className="mt-3 flex items-center justify-between gap-4">
                                        <div>
                                            <p className="text-lg font-semibold">
                                                Ready to scan
                                            </p>
                                            <p className="text-sm text-cyan-50/75">
                                                Open the camera station or download your QR card
                                            </p>
                                        </div>
                                        <QrCode className="h-8 w-8 text-cyan-100" />
                                    </div>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-3">
                                <Button
                                    asChild
                                    className="bg-white text-slate-950 hover:bg-cyan-50"
                                >
                                    <Link href="/scan">
                                        <QrCode className="mr-2 h-4 w-4" />
                                        Open scanner
                                    </Link>
                                </Button>

                                {canManageUsers ? (
                                    <>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="border-white/30 bg-white/10 text-white hover:bg-white/20"
                                        >
                                            <Link href="/users">
                                                <Users className="mr-2 h-4 w-4" />
                                                User management
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="border-white/30 bg-white/10 text-white hover:bg-white/20"
                                        >
                                            <Link href="/attendances">
                                                <Download className="mr-2 h-4 w-4" />
                                                Attendance reports
                                            </Link>
                                        </Button>
                                    </>
                                ) : (
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="border-white/30 bg-white/10 text-white hover:bg-white/20"
                                    >
                                        <Link href="/scan">
                                            Go to check-in station
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>

                        <QrIdentityCard
                            name={auth.user.name}
                            subtitle={
                                auth.user.employee_code ||
                                auth.user.email ||
                                roleLabel
                            }
                            value={myQrValue}
                            className="border-white/10 bg-white/90 shadow-none"
                        />
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {statCards.map((card) => (
                        <Card
                            key={card.label}
                            className="border-slate-200/80 bg-white/85 shadow-sm backdrop-blur"
                        >
                            <CardHeader className="flex flex-row items-start justify-between space-y-0">
                                <div>
                                    <CardDescription>{card.label}</CardDescription>
                                    <CardTitle className="mt-2 text-3xl">
                                        {card.value}
                                    </CardTitle>
                                </div>
                                <div
                                    className={`rounded-2xl bg-gradient-to-br ${card.accent} p-3 text-white`}
                                >
                                    <card.icon className="h-5 w-5" />
                                </div>
                            </CardHeader>
                        </Card>
                    ))}
                </section>

                <section className="grid gap-6 xl:grid-cols-[1.06fr_0.94fr]">
                    <Card className="bg-white/90 backdrop-blur">
                        <CardHeader>
                            <CardTitle>Recent attendance</CardTitle>
                            <CardDescription>
                                Latest recorded attendance activity across the
                                workspace.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {recentAttendances.length === 0 ? (
                                <p className="rounded-2xl border border-dashed border-slate-200 p-6 text-sm text-slate-500">
                                    No attendance records yet. Open the scanner
                                    to start recording check-ins.
                                </p>
                            ) : (
                                recentAttendances.map((attendance) => (
                                    <div
                                        key={attendance.id}
                                        className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3"
                                    >
                                        <div>
                                            <p className="font-medium text-slate-900">
                                                {attendance.user_name}
                                            </p>
                                            <p className="text-sm text-slate-500">
                                                {attendance.employee_code ??
                                                    'No employee code'}
                                            </p>
                                            <p className="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">
                                                {attendance.entry_type_label ??
                                                    'Attendance'}
                                            </p>
                                            {attendance.status_label && (
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        attendance.attendance_status ===
                                                        'late'
                                                            ? 'mt-2 border-amber-200 bg-amber-50 text-amber-800'
                                                            : 'mt-2 border-emerald-200 bg-emerald-50 text-emerald-800'
                                                    }
                                                >
                                                    {attendance.status_label}
                                                </Badge>
                                            )}
                                            {attendance.status_hint && (
                                                <p className="mt-2 text-xs text-slate-500">
                                                    {attendance.status_hint}
                                                </p>
                                            )}
                                        </div>
                                        <div className="text-right text-sm text-slate-500">
                                            <p>{attendance.recorded_date}</p>
                                            <p>{attendance.recorded_time}</p>
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card className="bg-white/90 backdrop-blur">
                        <CardHeader>
                            <CardTitle>Workspace guidance</CardTitle>
                            <CardDescription>
                                A cleaner daily flow for attendance operations.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="rounded-2xl bg-slate-50 px-4 py-4">
                                <p className="font-medium text-slate-900">
                                    1. Start with the QR scanner
                                </p>
                                <p className="mt-1 text-sm text-slate-500">
                                    The scanner now supports live camera access,
                                    QR image uploads, and manual fallback entry.
                                </p>
                            </div>

                            <div className="rounded-2xl bg-slate-50 px-4 py-4">
                                <p className="font-medium text-slate-900">
                                    2. Share downloadable QR cards
                                </p>
                                <p className="mt-1 text-sm text-slate-500">
                                    Each user now has a visible QR preview with
                                    a downloadable named QR card.
                                </p>
                            </div>

                            <div className="rounded-2xl bg-slate-50 px-4 py-4">
                                <p className="font-medium text-slate-900">
                                    3. Keep records export-ready
                                </p>
                                <p className="mt-1 text-sm text-slate-500">
                                    Admins can review attendance logs and export
                                    them in Excel format whenever needed.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </section>
            </div>
        </AppLayout>
    );
}
