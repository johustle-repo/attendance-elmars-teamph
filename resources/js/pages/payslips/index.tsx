import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Download, FileText, Receipt } from 'lucide-react';
import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type {
    BreadcrumbItem,
    Flash,
    MonthOption,
    PayslipItem,
    PayslipSummary,
} from '@/types';
import { usePage } from '@inertiajs/react';

type Props = {
    filters: { year: number; month: number };
    availableYears: number[];
    availableMonths: MonthOption[];
    periodLabel: string;
    payslips: PayslipItem[];
    summary: PayslipSummary;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payslips', href: '/payslips' },
];

export default function PayslipsIndex({
    filters,
    availableYears,
    availableMonths,
    periodLabel,
    payslips,
    summary,
}: Props) {
    const { flash } = usePage().props as { flash: Flash };
    const [year, setYear] = useState(String(filters.year));
    const [month, setMonth] = useState(String(filters.month));

    const applyFilters = () => {
        router.get('/payslips', { year, month }, { preserveState: true, replace: true });
    };

    const exportExcelUrl = useMemo(() => {
        const p = new URLSearchParams({ year, month });
        return `/payslips/export?${p.toString()}`;
    }, [year, month]);

    const pdfUrl = (userId: number) => {
        const p = new URLSearchParams({ year, month });
        return `/payslips/${userId}/pdf?${p.toString()}`;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payslips" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6 dark:bg-[linear-gradient(180deg,_rgba(8,47,73,0.22)_0%,_rgba(2,6,23,0)_24%)]">
                <FlashMessage flash={flash} />

                {/* Header card */}
                <Card className="border-cyan-100 dark:border-cyan-500/20 dark:bg-slate-950/80">
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Receipt className="h-5 w-5 text-cyan-700" />
                                    Payslips — {periodLabel}
                                </CardTitle>
                                <CardDescription>
                                    Monthly pay summary — hours are clipped to shift
                                    windows. Day shift 8 AM–12 PM &amp; 1 PM–5 PM
                                    (lunch excluded); night shift 6 PM–9 PM (Fiona
                                    &amp; Maria only). Rate: $2.00/hr default,
                                    $3.00/hr (Elmar).
                                </CardDescription>
                            </div>

                            <Button
                                asChild
                                className="bg-slate-950 text-white hover:bg-slate-800 dark:bg-cyan-500 dark:text-slate-950 dark:hover:bg-cyan-400"
                            >
                                <a href={exportExcelUrl}>
                                    <Download className="mr-2 h-4 w-4" />
                                    Export Excel
                                </a>
                            </Button>
                        </div>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        {/* Filters */}
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="flex flex-col gap-1">
                                <span className="text-xs text-slate-500">Month</span>
                                <Select value={month} onValueChange={setMonth}>
                                    <SelectTrigger className="w-36">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableMonths.map((m) => (
                                            <SelectItem key={m.value} value={String(m.value)}>
                                                {m.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex flex-col gap-1">
                                <span className="text-xs text-slate-500">Year</span>
                                <Select value={year} onValueChange={setYear}>
                                    <SelectTrigger className="w-28">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableYears.map((y) => (
                                            <SelectItem key={y} value={String(y)}>
                                                {y}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button type="button" onClick={applyFilters} variant="outline">
                                Apply
                            </Button>
                        </div>

                        {/* Summary stats */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div className="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-900">
                                <p className="text-sm text-slate-500 dark:text-slate-400">Employees</p>
                                <p className="text-3xl font-semibold text-slate-950 dark:text-slate-50">
                                    {summary.totalEmployees}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-900">
                                <p className="text-sm text-slate-500 dark:text-slate-400">Total Hours</p>
                                <p className="text-3xl font-semibold text-slate-950 dark:text-slate-50">
                                    {summary.totalHours.toFixed(2)}h
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-900">
                                <p className="text-sm text-slate-500 dark:text-slate-400">Total Gross Pay</p>
                                <p className="text-3xl font-semibold text-emerald-700 dark:text-emerald-400">
                                    ${summary.totalPay.toFixed(2)}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Payslip table */}
                <Card className="dark:border-slate-800 dark:bg-slate-950/80">
                    <CardHeader>
                        <CardTitle>Employee Payslips</CardTitle>
                        <CardDescription>
                            Click the PDF button on any row to download that
                            employee's payslip.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {payslips.length === 0 ? (
                            <div className="rounded-[1.5rem] border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                No employees found for the selected period.
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-[1.5rem] border border-slate-200 dark:border-slate-800">
                                <table className="min-w-full divide-y divide-slate-200 bg-white text-sm dark:divide-slate-800 dark:bg-slate-950">
                                    <thead className="bg-slate-50 dark:bg-slate-900">
                                        <tr className="text-left text-slate-600 dark:text-slate-300">
                                            <th className="px-4 py-3 font-semibold">ID</th>
                                            <th className="px-4 py-3 font-semibold">Name</th>
                                            <th className="px-4 py-3 font-semibold">Position</th>
                                            <th className="px-4 py-3 font-semibold">Rate/hr</th>
                                            <th className="px-4 py-3 font-semibold">Days</th>
                                            <th className="px-4 py-3 font-semibold">Total Hours</th>
                                            <th className="px-4 py-3 font-semibold">Gross Pay</th>
                                            <th className="px-4 py-3 font-semibold">PDF</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                        {payslips.map((p) => (
                                            <tr key={p.user_id} className="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                                                <td className="px-4 py-3 text-slate-600 dark:text-slate-400">
                                                    {p.employee_code ?? `USER-${p.user_id}`}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="font-medium text-slate-900 dark:text-slate-100">
                                                        {p.name}
                                                    </p>
                                                    {p.sub_name && (
                                                        <p className="text-xs text-cyan-600 dark:text-cyan-400">
                                                            {p.sub_name}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-slate-600 dark:text-slate-400">
                                                    {p.position ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                                                    ${p.hourly_rate.toFixed(2)}
                                                </td>
                                                <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                                                    {p.days_worked}
                                                </td>
                                                <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                                                    {p.total_hours.toFixed(2)}h
                                                </td>
                                                <td className="px-4 py-3 font-semibold text-emerald-700 dark:text-emerald-400">
                                                    ${p.total_pay.toFixed(2)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                        className="border-slate-200 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300"
                                                    >
                                                        <a href={pdfUrl(p.user_id)}>
                                                            <FileText className="mr-1 h-3.5 w-3.5" />
                                                            PDF
                                                        </a>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
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
