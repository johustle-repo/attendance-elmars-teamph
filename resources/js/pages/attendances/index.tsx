import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CalendarCheck2, Download, Search, Trash2 } from 'lucide-react';
import { FlashMessage } from '@/components/flash-message';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { AttendanceSummaryItem, BreadcrumbItem, Flash } from '@/types';

type RecordableUser = {
    id: number;
    name: string;
    sub_name?: string | null;
    employee_code?: string | null;
};

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
    recordableUsers: RecordableUser[];
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

type ManualRecordEntryType = 'time_in' | 'time_out';
type AttendanceTab = 'monitor' | 'record';

type ManualRecordForm = {
    user_id: string;
    entry_type: ManualRecordEntryType;
    recorded_date: string;
    recorded_time: string;
};

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
    recordableUsers,
    attendances,
}: Props) {
    const { flash } = usePage().props as { flash: Flash };
    const [search, setSearch] = useState(filters.search);
    const [date, setDate] = useState(filters.date);
    const [activeTab, setActiveTab] = useState<AttendanceTab>('monitor');
    const [editingValues, setEditingValues] = useState<EditingValues>(
        toEditingValues(attendances),
    );
    const recordAttendanceForm = useForm<ManualRecordForm>({
        user_id: recordableUsers[0] ? String(recordableUsers[0].id) : '',
        entry_type: 'time_in',
        recorded_date: filters.date,
        recorded_time: '',
    });

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();

        if (search) {
            params.set('search', search);
        }

        if (date) {
            params.set('date', date);
        }

        const query = params.toString();

        return query ? `/attendances/export?${query}` : '/attendances/export';
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
        entryType: ManualRecordEntryType,
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

    const submitRecordAttendance = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        recordAttendanceForm.transform((data) => ({
            ...data,
            search,
            date,
        }));

        recordAttendanceForm.post('/attendances/manual-record', {
            preserveScroll: true,
            onSuccess: () => {
                recordAttendanceForm.setData('recorded_time', '');
            },
        });
    };

    const deleteAttendance = (
        attendanceId: number | null | undefined,
        entryType: ManualRecordEntryType,
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

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6 dark:bg-[linear-gradient(180deg,_rgba(8,47,73,0.22)_0%,_rgba(2,6,23,0)_24%)]">
                <FlashMessage flash={flash} />

                <Card className="border-cyan-100 dark:border-cyan-500/20 dark:bg-slate-950/80">
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <CalendarCheck2 className="h-5 w-5 text-cyan-700" />
                                    Attendance table
                                </CardTitle>
                                <CardDescription>
                                    One row per date per user with separate Time
                                    In and Time Out values plus a late check
                                    based on office hours ({officeHours}).
                                </CardDescription>
                            </div>

                            <Button
                                asChild
                                className="bg-slate-950 text-white hover:bg-slate-800 dark:bg-cyan-500 dark:text-slate-950 dark:hover:bg-cyan-400"
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
                                <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
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
                            <div className="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-900">
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Summary rows
                                </p>
                                <p className="text-3xl font-semibold text-slate-950 dark:text-slate-50">
                                    {summary.recordCount}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-900">
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Unique users
                                </p>
                                <p className="text-3xl font-semibold text-slate-950 dark:text-slate-50">
                                    {summary.uniqueUsers}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-900">
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Team size
                                </p>
                                <p className="text-3xl font-semibold text-slate-950 dark:text-slate-50">
                                    {summary.teamSize}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {canEditAttendanceTime ? (
                    <div className="inline-flex w-full max-w-fit rounded-2xl bg-slate-100 p-1 dark:bg-slate-900">
                        <Button
                            type="button"
                            variant={activeTab === 'monitor' ? 'default' : 'ghost'}
                            onClick={() => setActiveTab('monitor')}
                            className={
                                activeTab === 'monitor'
                                    ? 'bg-slate-950 text-white hover:bg-slate-800 dark:bg-cyan-500 dark:text-slate-950 dark:hover:bg-cyan-400'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-100'
                            }
                        >
                            Attendance Monitor
                        </Button>
                        <Button
                            type="button"
                            variant={activeTab === 'record' ? 'default' : 'ghost'}
                            onClick={() => setActiveTab('record')}
                            className={
                                activeTab === 'record'
                                    ? 'bg-slate-950 text-white hover:bg-slate-800 dark:bg-cyan-500 dark:text-slate-950 dark:hover:bg-cyan-400'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-100'
                            }
                        >
                            Record Attendance
                        </Button>
                    </div>
                ) : null}

                {(!canEditAttendanceTime || activeTab === 'monitor') ? (
                <Card className="dark:border-slate-800 dark:bg-slate-950/80">
                    <CardHeader>
                        <CardTitle>Daily attendance monitor</CardTitle>
                        <CardDescription>
                            {canEditAttendanceTime
                                ? 'Super admin accounts can adjust existing Time In and Time Out records, add a missing Time Out, and switch to the Record Attendance tab for new manual entries.'
                                : 'Admin accounts can review daily attendance in a clearer summarized view.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {attendances.length === 0 ? (
                            <div className="rounded-[1.5rem] border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                No attendance records found for the selected
                                filters.
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-[1.5rem] border border-slate-200 dark:border-slate-800">
                                <table className="min-w-full divide-y divide-slate-200 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                                    <thead className="bg-slate-50 dark:bg-slate-900">
                                        <tr className="text-left text-slate-600 dark:text-slate-300">
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
                                            <th className="px-4 py-3 font-semibold">
                                                Total Hours
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                        {attendances.map((attendance) => {
                                            const values =
                                                editingValues[attendance.key];

                                            return (
                                                <tr
                                                    key={attendance.key}
                                                    className="align-top"
                                                >
                                                    <td className="px-4 py-4 font-medium text-slate-900 dark:text-slate-100">
                                                        {
                                                            attendance.display_date
                                                        }
                                                    </td>
                                                    <td className="px-4 py-4 text-slate-700 dark:text-slate-300">
                                                        {attendance.employee_code ??
                                                            `USER-${attendance.user_id}`}
                                                    </td>
                                                    <td className="px-4 py-4 text-slate-900 dark:text-slate-100">
                                                        {attendance.user_name}
                                                    </td>
                                                    <td className="px-4 py-4 text-slate-700 dark:text-slate-300">
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
                                                                    className="w-full"
                                                                />
                                                                <div className="flex flex-wrap gap-2">
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
                                                                        className="w-full"
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
                                                                        className="border-rose-200 text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:border-rose-500/40 dark:text-rose-200 dark:hover:bg-rose-500/10 dark:hover:text-rose-100"
                                                                    >
                                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                                        Delete
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <span className="font-medium text-slate-900 dark:text-slate-100">
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
                                                                        ? 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200'
                                                                        : attendance.attendance_status ===
                                                                            'on_time'
                                                                          ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200'
                                                                          : 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
                                                                }
                                                            >
                                                                {
                                                                    attendance.status_label
                                                                }
                                                            </Badge>
                                                            <p className="text-xs leading-5 text-slate-500 dark:text-slate-400">
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
                                                                    className="w-full"
                                                                />
                                                                <div className="flex flex-wrap gap-2">
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
                                                                        className="w-full"
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
                                                                                className="border-rose-200 text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:border-rose-500/40 dark:text-rose-200 dark:hover:bg-rose-500/10 dark:hover:text-rose-100"
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
                                                                            className="bg-emerald-600 text-white hover:bg-emerald-700 disabled:bg-emerald-300 dark:disabled:bg-emerald-800/40"
                                                                        >
                                                                            Add
                                                                            Time
                                                                            Out
                                                                        </Button>
                                                                    )}
                                                                </div>
                                                                {!attendance.time_out_attendance_id ? (
                                                                    <p className="text-xs leading-5 text-slate-500 dark:text-slate-400">
                                                                        Add a
                                                                        missing
                                                                        Time Out
                                                                        for this
                                                                        day.
                                                                    </p>
                                                                ) : null}
                                                            </div>
                                                        ) : (
                                                            <span className="font-medium text-slate-900 dark:text-slate-100">
                                                                {attendance.time_out_display ??
                                                                    'Not recorded'}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-4 font-medium text-slate-900 dark:text-slate-100">
                                                        {attendance.total_hours_label ?? 'N/A'}
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

                ) : null}

                {canEditAttendanceTime && activeTab === 'record' ? (
                    <Card className="dark:border-slate-800 dark:bg-slate-950/80">
                        <CardHeader>
                            <CardTitle>Record attendance</CardTitle>
                            <CardDescription>
                                Manually add a Time In or Time Out record for
                                an active agent. Time Out entries still require
                                an existing Time In on the same date.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {recordableUsers.length === 0 ? (
                                <div className="rounded-[1.5rem] border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                    No active agents are available to record
                                    attendance right now.
                                </div>
                            ) : (
                                <form
                                    onSubmit={submitRecordAttendance}
                                    className="grid gap-5 lg:grid-cols-2"
                                >
                                    <div className="space-y-2 lg:col-span-2">
                                        <Label htmlFor="record-user">
                                            Agent
                                        </Label>
                                        <Select
                                            value={recordAttendanceForm.data.user_id}
                                            onValueChange={(value) =>
                                                recordAttendanceForm.setData(
                                                    'user_id',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id="record-user"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select an agent" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {recordableUsers.map((user) => (
                                                    <SelectItem
                                                        key={user.id}
                                                        value={String(user.id)}
                                                    >
                                                        {`${user.name}${user.employee_code ? ` (${user.employee_code})` : ''}`}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {recordAttendanceForm.errors.user_id ? (
                                            <p className="text-sm text-rose-600 dark:text-rose-300">
                                                {
                                                    recordAttendanceForm.errors
                                                        .user_id
                                                }
                                            </p>
                                        ) : null}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="record-entry-type">
                                            Entry type
                                        </Label>
                                        <Select
                                            value={recordAttendanceForm.data.entry_type}
                                            onValueChange={(value) =>
                                                recordAttendanceForm.setData(
                                                    'entry_type',
                                                    value as ManualRecordEntryType,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id="record-entry-type"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="time_in">
                                                    Time In
                                                </SelectItem>
                                                <SelectItem value="time_out">
                                                    Time Out
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {recordAttendanceForm.errors.entry_type ? (
                                            <p className="text-sm text-rose-600 dark:text-rose-300">
                                                {
                                                    recordAttendanceForm.errors
                                                        .entry_type
                                                }
                                            </p>
                                        ) : null}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="record-date">Date</Label>
                                        <Input
                                            id="record-date"
                                            type="date"
                                            value={
                                                recordAttendanceForm.data
                                                    .recorded_date
                                            }
                                            onChange={(event) =>
                                                recordAttendanceForm.setData(
                                                    'recorded_date',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        {recordAttendanceForm.errors.recorded_date ? (
                                            <p className="text-sm text-rose-600 dark:text-rose-300">
                                                {
                                                    recordAttendanceForm.errors
                                                        .recorded_date
                                                }
                                            </p>
                                        ) : null}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="record-time">Time</Label>
                                        <Input
                                            id="record-time"
                                            type="time"
                                            value={
                                                recordAttendanceForm.data
                                                    .recorded_time
                                            }
                                            onChange={(event) =>
                                                recordAttendanceForm.setData(
                                                    'recorded_time',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        {recordAttendanceForm.errors.recorded_time ? (
                                            <p className="text-sm text-rose-600 dark:text-rose-300">
                                                {
                                                    recordAttendanceForm.errors
                                                        .recorded_time
                                                }
                                            </p>
                                        ) : null}
                                    </div>

                                    <div className="space-y-3 rounded-2xl bg-slate-50 p-4 lg:col-span-2 dark:bg-slate-900">
                                        <p className="text-sm font-medium text-slate-900 dark:text-slate-100">
                                            Quick reminders
                                        </p>
                                        <p className="text-sm text-slate-600 dark:text-slate-300">
                                            Only active agents are listed here.
                                            A Time In must happen before a Time
                                            Out on the same date, and each date
                                            only allows one Time In and one Time
                                            Out per agent.
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap gap-3 lg:col-span-2">
                                        <Button
                                            type="submit"
                                            disabled={
                                                recordAttendanceForm.processing ||
                                                !recordAttendanceForm.data
                                                    .user_id ||
                                                !recordAttendanceForm.data
                                                    .recorded_date ||
                                                !recordAttendanceForm.data
                                                    .recorded_time
                                            }
                                            className="bg-cyan-600 text-white hover:bg-cyan-700"
                                        >
                                            {recordAttendanceForm.processing
                                                ? 'Recording...'
                                                : 'Record attendance'}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                recordAttendanceForm.reset()
                                            }
                                        >
                                            Reset form
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </AppLayout>
    );
}
