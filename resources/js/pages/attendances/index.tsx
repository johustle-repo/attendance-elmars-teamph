import { useEffect, useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { CalendarCheck2, Download, Search, Trash2 } from 'lucide-react';
import { FlashMessage } from '@/components/flash-message';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type {
    AttendanceSummaryItem,
    BreadcrumbItem,
    Flash,
} from '@/types';

type Props = {
    filters: {
        search: string;
        date: string;
    };
    officeHours: string;
    summary: {
        recordCount: number;
        uniqueUsers: number;
        teamSize: number;
    };
    canEditAttendanceTime: boolean;
    attendances: AttendanceSummaryItem[];
};

type EditingValues = Record<
    string,
    {
        time_in_date: string;
        time_in_time: string;
        time_out_date: string;
        time_out_time: string;
    }
>;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Attendance', href: '/attendances' },
];

function toEditingValues(attendances: AttendanceSummaryItem[]): EditingValues {
    return Object.fromEntries(
        attendances.map((attendance) => [
            attendance.key,
            {
                time_in_date: attendance.time_in_date ?? attendance.date,
                time_in_time: attendance.time_in_time ?? '',
                time_out_date: attendance.time_out_date ?? attendance.date,
                time_out_time: attendance.time_out_time ?? '',
            },
        ]),
    );
}

export default function AttendancesIndex({
    filters,
    officeHours,
    summary,
    canEditAttendanceTime,
    attendances,
}: Props) {
    const { flash } = usePage().props as { flash: Flash };
    const [search, setSearch] = useState(filters.search);
    const [date, setDate] = useState(filters.date);
    const [editingValues, setEditingValues] = useState<EditingValues>(
        toEditingValues(attendances),
    );

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();

        if (search) {
            params.set('search', search);
        }

        if (date) {
            params.set('date', date);
        }

        const query = params.toString();

        return query
            ? `/attendances/export?${query}`
            : '/attendances/export';
    }, [date, search]);

    useEffect(() => {
        setEditingValues(toEditingValues(attendances));
    }, [attendances]);

    const applyFilters = () => {
        router.get(
            '/attendances',
            {
                search,
                date,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const saveAttendanceTime = (
        attendance: AttendanceSummaryItem,
        entryType: 'time_in' | 'time_out',
    ) => {
        const attendanceId =
            entryType === 'time_in'
                ? attendance.time_in_attendance_id
                : attendance.time_out_attendance_id;

        if (!attendanceId) {
            return;
        }

        const values = editingValues[attendance.key];

        router.patch(
            `/attendances/${attendanceId}`,
            {
                recorded_date:
                    entryType === 'time_in'
                        ? values.time_in_date
                        : values.time_out_date,
                recorded_time:
                    entryType === 'time_in'
                        ? values.time_in_time
                        : values.time_out_time,
                search,
                date,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const addManualTimeOut = (attendance: AttendanceSummaryItem) => {
        const values = editingValues[attendance.key];

        router.post(
            '/attendances/manual-time-out',
            {
                user_id: attendance.user_id,
                recorded_date: values.time_out_date,
                recorded_time: values.time_out_time,
                search,
                date,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const deleteAttendance = (
        attendanceId: number | null | undefined,
        entryType: 'time_in' | 'time_out',
    ) => {
        if (!attendanceId) {
            return;
        }

        const confirmed = window.confirm(
            `Delete this ${entryType === 'time_in' ? 'Time In' : 'Time Out'} record?`,
        );

        if (!confirmed) {
            return;
        }

        router.delete(`/attendances/${attendanceId}`, {
            data: {
                search,
                date,
            },
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Attendance" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <FlashMessage flash={flash} />

                <Card className="border-cyan-100">
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <CalendarCheck2 className="h-5 w-5 text-cyan-700" />
                                    Attendance table
                                </CardTitle>
                                <CardDescription>
                                    One row per date per user with separate
                                    Time In and Time Out values plus a late
                                    check based on office hours ({officeHours}).
                                </CardDescription>
                            </div>

                            <Button
                                asChild
                                className="bg-slate-950 text-white hover:bg-slate-800"
                            >
                                <a href={exportUrl}>
                                    <Download className="mr-2 h-4 w-4" />
                                    Export Excel
                                </a>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="grid gap-4 lg:grid-cols-[1fr_220px_auto]">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search ID, name, or email"
                                    className="pl-9"
                                />
                            </div>
                            <Input
                                type="date"
                                value={date}
                                onChange={(event) =>
                                    setDate(event.target.value)
                                }
                            />
                            <Button
                                type="button"
                                onClick={applyFilters}
                                variant="outline"
                            >
                                Apply filters
                            </Button>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="rounded-2xl bg-slate-50 px-4 py-3">
                                <p className="text-sm text-slate-500">
                                    Summary rows
                                </p>
                                <p className="text-3xl font-semibold text-slate-950">
                                    {summary.recordCount}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-50 px-4 py-3">
                                <p className="text-sm text-slate-500">
                                    Unique users
                                </p>
                                <p className="text-3xl font-semibold text-slate-950">
                                    {summary.uniqueUsers}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-50 px-4 py-3">
                                <p className="text-sm text-slate-500">
                                    Team size
                                </p>
                                <p className="text-3xl font-semibold text-slate-950">
                                    {summary.teamSize}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Daily attendance monitor</CardTitle>
                        <CardDescription>
                            {canEditAttendanceTime
                                ? 'Super admin accounts can adjust existing Time In and Time Out records and add a missing Time Out directly from the table.'
                                : 'Admin accounts can review daily attendance in a clearer summarized view.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {attendances.length === 0 ? (
                            <div className="rounded-[1.5rem] border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">
                                No attendance records found for the selected
                                filters.
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
                                                ID
                                            </th>
                                            <th className="px-4 py-3 font-semibold">
                                                Name
                                            </th>
                                            <th className="px-4 py-3 font-semibold">
                                                Email
                                            </th>
                                            <th className="px-4 py-3 font-semibold">
                                                Time In
                                            </th>
                                            <th className="px-4 py-3 font-semibold">
                                                Status
                                            </th>
                                            <th className="px-4 py-3 font-semibold">
                                                Time Out
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200">
                                        {attendances.map((attendance) => {
                                            const values =
                                                editingValues[attendance.key];

                                            return (
                                                <tr
                                                    key={attendance.key}
                                                    className="align-top"
                                                >
                                                    <td className="px-4 py-4 font-medium text-slate-900">
                                                        {attendance.display_date}
                                                    </td>
                                                    <td className="px-4 py-4 text-slate-700">
                                                        {attendance.employee_code ??
                                                            `USER-${attendance.user_id}`}
                                                    </td>
                                                    <td className="px-4 py-4 text-slate-900">
                                                        {attendance.user_name}
                                                    </td>
                                                    <td className="px-4 py-4 text-slate-700">
                                                        {attendance.user_email ??
                                                            'No email'}
                                                    </td>
                                                    <td className="px-4 py-4">
                                                        {canEditAttendanceTime &&
                                                        attendance.time_in_attendance_id ? (
                                                            <div className="space-y-2">
                                                                <Input
                                                                    type="date"
                                                                    value={
                                                                        values?.time_in_date ??
                                                                        ''
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setEditingValues(
                                                                            (
                                                                                current,
                                                                            ) => ({
                                                                                ...current,
                                                                                [attendance.key]:
                                                                                    {
                                                                                        ...current[
                                                                                            attendance
                                                                                                .key
                                                                                        ],
                                                                                        time_in_date:
                                                                                            event
                                                                                                .target
                                                                                                .value,
                                                                                    },
                                                                            }),
                                                                        )
                                                                    }
                                                                    className="min-w-[150px]"
                                                                />
                                                                <div className="flex gap-2">
                                                                    <Input
                                                                        type="time"
                                                                        value={
                                                                            values?.time_in_time ??
                                                                            ''
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            setEditingValues(
                                                                                (
                                                                                    current,
                                                                                ) => ({
                                                                                    ...current,
                                                                                    [attendance.key]:
                                                                                        {
                                                                                            ...current[
                                                                                                attendance
                                                                                                    .key
                                                                                            ],
                                                                                            time_in_time:
                                                                                                event
                                                                                                    .target
                                                                                                    .value,
                                                                                        },
                                                                                }),
                                                                            )
                                                                        }
                                                                        className="min-w-[120px]"
                                                                    />
                                                                    <Button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            saveAttendanceTime(
                                                                                attendance,
                                                                                'time_in',
                                                                            )
                                                                        }
                                                                        className="bg-cyan-600 text-white hover:bg-cyan-700"
                                                                    >
                                                                        Save
                                                                    </Button>
                                                                    <Button
                                                                        type="button"
                                                                        variant="outline"
                                                                        onClick={() =>
                                                                            deleteAttendance(
                                                                                attendance.time_in_attendance_id,
                                                                                'time_in',
                                                                            )
                                                                        }
                                                                        className="border-rose-200 text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                                                                    >
                                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                                        Delete
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <span className="font-medium text-slate-900">
                                                                {attendance.time_in_display ??
                                                                    'Not recorded'}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-4">
                                                        <div className="space-y-2">
                                                            <Badge
                                                                variant="outline"
                                                                className={
                                                                    attendance.attendance_status ===
                                                                    'late'
                                                                        ? 'border-amber-200 bg-amber-50 text-amber-800'
                                                                        : attendance.attendance_status ===
                                                                            'on_time'
                                                                          ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                                          : 'border-slate-200 bg-slate-50 text-slate-600'
                                                                }
                                                            >
                                                                {
                                                                    attendance.status_label
                                                                }
                                                            </Badge>
                                                            <p className="max-w-[180px] text-xs leading-5 text-slate-500">
                                                                {
                                                                    attendance.status_hint
                                                                }
                                                            </p>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-4">
                                                        {canEditAttendanceTime ? (
                                                            <div className="space-y-2">
                                                                <Input
                                                                    type="date"
                                                                    value={
                                                                        values?.time_out_date ??
                                                                        ''
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setEditingValues(
                                                                            (
                                                                                current,
                                                                            ) => ({
                                                                                ...current,
                                                                                [attendance.key]:
                                                                                    {
                                                                                        ...current[
                                                                                            attendance
                                                                                                .key
                                                                                        ],
                                                                                        time_out_date:
                                                                                            event
                                                                                                .target
                                                                                                .value,
                                                                                    },
                                                                            }),
                                                                        )
                                                                    }
                                                                    className="min-w-[150px]"
                                                                />
                                                                <div className="flex gap-2">
                                                                    <Input
                                                                        type="time"
                                                                        value={
                                                                            values?.time_out_time ??
                                                                            ''
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            setEditingValues(
                                                                                (
                                                                                    current,
                                                                                ) => ({
                                                                                    ...current,
                                                                                    [attendance.key]:
                                                                                        {
                                                                                            ...current[
                                                                                                attendance
                                                                                                    .key
                                                                                            ],
                                                                                            time_out_time:
                                                                                                event
                                                                                                    .target
                                                                                                    .value,
                                                                                        },
                                                                                }),
                                                                            )
                                                                        }
                                                                        className="min-w-[120px]"
                                                                    />
                                                                    {attendance.time_out_attendance_id ? (
                                                                        <>
                                                                            <Button
                                                                                type="button"
                                                                                onClick={() =>
                                                                                    saveAttendanceTime(
                                                                                        attendance,
                                                                                        'time_out',
                                                                                    )
                                                                                }
                                                                                className="bg-cyan-600 text-white hover:bg-cyan-700"
                                                                            >
                                                                                Save
                                                                            </Button>
                                                                            <Button
                                                                                type="button"
                                                                                variant="outline"
                                                                                onClick={() =>
                                                                                    deleteAttendance(
                                                                                        attendance.time_out_attendance_id,
                                                                                        'time_out',
                                                                                    )
                                                                                }
                                                                                className="border-rose-200 text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                                                                            >
                                                                                <Trash2 className="mr-2 h-4 w-4" />
                                                                                Delete
                                                                            </Button>
                                                                        </>
                                                                    ) : (
                                                                        <Button
                                                                            type="button"
                                                                            onClick={() =>
                                                                                addManualTimeOut(
                                                                                    attendance,
                                                                                )
                                                                            }
                                                                            disabled={
                                                                                !values?.time_out_date ||
                                                                                !values?.time_out_time
                                                                            }
                                                                            className="bg-emerald-600 text-white hover:bg-emerald-700 disabled:bg-emerald-300"
                                                                        >
                                                                            Add Time Out
                                                                        </Button>
                                                                    )}
                                                                </div>
                                                                {!attendance.time_out_attendance_id ? (
                                                                    <p className="text-xs leading-5 text-slate-500">
                                                                        Add a missing
                                                                        Time Out for
                                                                        this day.
                                                                    </p>
                                                                ) : null}
                                                            </div>
                                                        ) : (
                                                            <span className="font-medium text-slate-900">
                                                                {attendance.time_out_display ??
                                                                    'Not recorded'}
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
