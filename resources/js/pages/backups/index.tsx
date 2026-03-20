import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    CalendarRange,
    DatabaseBackup,
    Download,
    Search,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type {
    BackupMonthOption,
    BackupSummary,
    BackupUser,
    BreadcrumbItem,
} from '@/types';

type Props = {
    filters: {
        year: number;
        month: number;
    };
    availableYears: number[];
    availableMonths: BackupMonthOption[];
    summary: BackupSummary;
    backupUsers: BackupUser[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Backups', href: '/backups' },
];

function formatCreatedAt(value?: string | null): string {
    if (!value) {
        return 'Unknown';
    }

    return new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
}

export default function BackupsIndex({
    filters,
    availableYears,
    availableMonths,
    summary,
    backupUsers,
}: Props) {
    const [search, setSearch] = useState('');
    const [year, setYear] = useState(String(filters.year));
    const [month, setMonth] = useState(String(filters.month));
    const [exportType, setExportType] = useState('json');

    const filteredUsers = useMemo(() => {
        const query = search.toLowerCase().trim();

        if (!query) {
            return backupUsers;
        }

        return backupUsers.filter((user) =>
            [
                user.name,
                user.email,
                user.employee_code,
                user.position,
                user.role_label,
            ]
                .filter(Boolean)
                .some((value) => value?.toLowerCase().includes(query)),
        );
    }, [backupUsers, search]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams({
            year,
            month,
            type: exportType,
        });

        return `/backups/export?${params.toString()}`;
    }, [exportType, month, year]);

    function applyFilters() {
        router.get(
            '/backups',
            {
                year,
                month,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Backups" />

            <div className="flex flex-1 flex-col gap-6 bg-[linear-gradient(180deg,_rgba(240,249,255,0.6)_0%,_rgba(248,250,252,0.15)_100%)] p-4 md:p-6">
                <section className="overflow-hidden rounded-[2rem] border border-cyan-100 bg-[linear-gradient(135deg,_#ecfeff_0%,_#f8fafc_52%,_#eff6ff_100%)] p-8">
                    <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                        <div className="space-y-4">
                            <div className="inline-flex w-fit items-center gap-2 rounded-full border border-cyan-200 bg-white/80 px-4 py-2 text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">
                                <DatabaseBackup className="h-4 w-4" />
                                Attendance backup center
                            </div>
                            <div>
                                <h1 className="text-3xl font-semibold text-slate-950">
                                    Backup user information and monthly attendance
                                </h1>
                                <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
                                    Choose the year and month, review each
                                    member&apos;s attendance archive, then
                                    download a JSON, Excel, or PDF backup with
                                    the user profile details and recorded Time
                                    In / Time Out data for that period.
                                </p>
                            </div>
                        </div>

                        <Card className="border-cyan-100 bg-white/90 shadow-sm">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CalendarRange className="h-5 w-5 text-cyan-700" />
                                    Backup period
                                </CardTitle>
                                <CardDescription>
                                    {summary.periodLabel}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <select
                                        value={month}
                                        onChange={(event) =>
                                            setMonth(event.target.value)
                                        }
                                        className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-10 rounded-md border bg-white px-3 text-sm outline-none focus-visible:ring-[3px]"
                                    >
                                        {availableMonths.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>

                                    <select
                                        value={year}
                                        onChange={(event) =>
                                            setYear(event.target.value)
                                        }
                                        className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-10 rounded-md border bg-white px-3 text-sm outline-none focus-visible:ring-[3px]"
                                    >
                                        {availableYears.map((option) => (
                                            <option key={option} value={option}>
                                                {option}
                                            </option>
                                        ))}
                                    </select>

                                    <select
                                        value={exportType}
                                        onChange={(event) =>
                                            setExportType(event.target.value)
                                        }
                                        className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-10 rounded-md border bg-white px-3 text-sm outline-none focus-visible:ring-[3px]"
                                    >
                                        <option value="json">JSON</option>
                                        <option value="excel">Excel</option>
                                        <option value="pdf">PDF</option>
                                    </select>
                                </div>

                                <div className="flex flex-wrap gap-3">
                                    <Button
                                        type="button"
                                        onClick={applyFilters}
                                        variant="outline"
                                    >
                                        Apply period
                                    </Button>
                                    <Button
                                        asChild
                                        className="bg-slate-950 text-white hover:bg-slate-800"
                                    >
                                        <a href={exportUrl}>
                                            <Download className="mr-2 h-4 w-4" />
                                            Download {exportType.toUpperCase()}{' '}
                                            backup
                                        </a>
                                    </Button>
                                </div>
                                {exportType === 'pdf' && (
                                    <p className="text-sm leading-6 text-slate-500">
                                        PDF backups include the signatory block:
                                        Approved and verified by: Elmar B.
                                        Noche
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-4">
                    <Card className="border-white/80 bg-white/90">
                        <CardHeader className="space-y-1">
                            <CardDescription>Total users</CardDescription>
                            <CardTitle className="text-3xl">
                                {summary.totalUsers}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card className="border-white/80 bg-white/90">
                        <CardHeader className="space-y-1">
                            <CardDescription>Users with attendance</CardDescription>
                            <CardTitle className="text-3xl">
                                {summary.usersWithAttendance}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card className="border-white/80 bg-white/90">
                        <CardHeader className="space-y-1">
                            <CardDescription>Attendance days</CardDescription>
                            <CardTitle className="text-3xl">
                                {summary.attendanceDayCount}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card className="border-white/80 bg-white/90">
                        <CardHeader className="space-y-1">
                            <CardDescription>Attendance logs</CardDescription>
                            <CardTitle className="text-3xl">
                                {summary.attendanceLogCount}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </section>

                <Card className="border-slate-200 bg-white/90">
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <UsersRound className="h-5 w-5 text-cyan-700" />
                                    Member backup preview
                                </CardTitle>
                                <CardDescription>
                                    Search the selected monthly archive by name,
                                    email, employee code, role, or position.
                                </CardDescription>
                            </div>

                            <div className="relative w-full max-w-sm">
                                <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search backup users"
                                    className="pl-9"
                                />
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                <section className="grid gap-5">
                    {filteredUsers.map((user) => (
                        <Card
                            key={user.id}
                            className="overflow-hidden border-slate-200 bg-white/95"
                        >
                            <CardHeader className="border-b border-slate-100 bg-slate-50/70">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <CardTitle className="text-xl text-slate-950">
                                            {user.name}
                                        </CardTitle>
                                        <CardDescription className="mt-1 text-sm">
                                            {user.email}
                                        </CardDescription>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant="outline">
                                            {user.role_label ?? user.role}
                                        </Badge>
                                        <Badge
                                            variant="outline"
                                            className="border-cyan-200 bg-cyan-50 text-cyan-800"
                                        >
                                            {user.attendance_day_count} day
                                            {user.attendance_day_count === 1
                                                ? ''
                                                : 's'}
                                        </Badge>
                                    </div>
                                </div>
                            </CardHeader>

                            <CardContent className="space-y-5 p-6">
                                <div className="grid gap-3 text-sm text-slate-600 md:grid-cols-4">
                                    <div className="rounded-2xl bg-slate-50 px-4 py-3">
                                        <p className="text-xs uppercase tracking-[0.18em] text-slate-400">
                                            Employee code
                                        </p>
                                        <p className="mt-1 font-medium text-slate-900">
                                            {user.employee_code ?? 'Not set'}
                                        </p>
                                    </div>
                                    <div className="rounded-2xl bg-slate-50 px-4 py-3">
                                        <p className="text-xs uppercase tracking-[0.18em] text-slate-400">
                                            Position
                                        </p>
                                        <p className="mt-1 font-medium text-slate-900">
                                            {user.position ?? 'Not set'}
                                        </p>
                                    </div>
                                    <div className="rounded-2xl bg-slate-50 px-4 py-3">
                                        <p className="text-xs uppercase tracking-[0.18em] text-slate-400">
                                            Logs in period
                                        </p>
                                        <p className="mt-1 font-medium text-slate-900">
                                            {user.attendance_log_count}
                                        </p>
                                    </div>
                                    <div className="rounded-2xl bg-slate-50 px-4 py-3">
                                        <p className="text-xs uppercase tracking-[0.18em] text-slate-400">
                                            Added
                                        </p>
                                        <p className="mt-1 font-medium text-slate-900">
                                            {formatCreatedAt(user.created_at)}
                                        </p>
                                    </div>
                                </div>

                                {user.attendance_days.length === 0 ? (
                                    <div className="rounded-[1.5rem] border border-dashed border-slate-200 p-6 text-sm text-slate-500">
                                        No attendance records for this member in{' '}
                                        {summary.periodLabel}.
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto rounded-[1.5rem] border border-slate-200">
                                        <table className="min-w-full divide-y divide-slate-200 bg-white text-sm">
                                            <thead className="bg-slate-50">
                                                <tr className="text-left text-slate-600">
                                                    <th className="px-4 py-3 font-semibold">
                                                        Date
                                                    </th>
                                                    <th className="px-4 py-3 font-semibold">
                                                        Time In
                                                    </th>
                                                    <th className="px-4 py-3 font-semibold">
                                                        Time Out
                                                    </th>
                                                    <th className="px-4 py-3 font-semibold">
                                                        Logs
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-200">
                                                {user.attendance_days.map((day) => (
                                                    <tr key={day.date}>
                                                        <td className="px-4 py-4 font-medium text-slate-900">
                                                            {day.display_date}
                                                        </td>
                                                        <td className="px-4 py-4 text-slate-700">
                                                            {day.time_in ??
                                                                'Not recorded'}
                                                        </td>
                                                        <td className="px-4 py-4 text-slate-700">
                                                            {day.time_out ??
                                                                'Not recorded'}
                                                        </td>
                                                        <td className="px-4 py-4">
                                                            <div className="flex flex-wrap gap-2">
                                                                {day.logs.map(
                                                                    (log) => (
                                                                        <span
                                                                            key={
                                                                                log.id
                                                                            }
                                                                            className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700"
                                                                        >
                                                                            {
                                                                                log.entry_type_label
                                                                            }{' '}
                                                                            -{' '}
                                                                            {
                                                                                log.recorded_time
                                                                            }
                                                                        </span>
                                                                    ),
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}

                    {filteredUsers.length === 0 && (
                        <Card className="border-dashed border-slate-200 bg-white/90">
                            <CardContent className="flex flex-col items-center gap-3 p-10 text-center text-sm text-slate-500">
                                <ShieldCheck className="h-6 w-6 text-cyan-700" />
                                No backup users matched your search for the
                                selected month and year.
                            </CardContent>
                        </Card>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
