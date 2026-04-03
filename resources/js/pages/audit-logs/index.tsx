import { Head, router, usePage } from '@inertiajs/react';
import { ClipboardList, Search } from 'lucide-react';
import { useState } from 'react';
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
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Flash } from '@/types';

type AuditLogEntry = {
    id: number;
    action: string;
    action_label: string;
    resource_type: string;
    resource_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    performed_at: string;
    performed_at_display: string;
    performer_name: string;
    performer_code: string | null;
};

type PaginatedLogs = {
    data: AuditLogEntry[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type ActionOption = { value: string; label: string };

type Props = {
    filters: { action: string; date: string };
    actions: ActionOption[];
    logs: PaginatedLogs;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Audit Logs', href: '/audit-logs' },
];

const actionColors: Record<string, string> = {
    'attendance.scan':            'bg-green-100 text-green-800',
    'attendance.update':          'bg-yellow-100 text-yellow-800',
    'attendance.delete':          'bg-red-100 text-red-800',
    'attendance.manual_time_out': 'bg-blue-100 text-blue-800',
    'backup.export':              'bg-purple-100 text-purple-800',
};

function ValuesBlock({ label, values }: { label: string; values: Record<string, unknown> | null }) {
    if (!values || Object.keys(values).length === 0) return null;
    return (
        <div className="mt-1">
            <span className="text-xs font-medium text-muted-foreground">{label}: </span>
            <span className="font-mono text-xs text-foreground">
                {Object.entries(values)
                    .map(([k, v]) => `${k}: ${String(v)}`)
                    .join(' · ')}
            </span>
        </div>
    );
}

export default function AuditLogsIndex({ filters, actions, logs }: Props) {
    const { flash } = usePage().props as { flash: Flash };

    const [search, setSearch] = useState({
        action: filters.action,
        date: filters.date,
    });

    function applyFilters() {
        router.get(
            '/audit-logs',
            { action: search.action || undefined, date: search.date || undefined },
            { preserveScroll: true },
        );
    }

    function clearFilters() {
        setSearch({ action: '', date: '' });
        router.get('/audit-logs', {}, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Logs" />

            <div className="flex flex-col gap-6 p-4">
                <FlashMessage flash={flash} />

                {/* Header */}
                <div className="flex items-center gap-3">
                    <ClipboardList className="h-6 w-6 text-muted-foreground" />
                    <div>
                        <h1 className="text-xl font-semibold">Audit Logs</h1>
                        <p className="text-sm text-muted-foreground">
                            All recorded system actions — visible to Super Admin only.
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Filter</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-3">
                            <select
                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                value={search.action}
                                onChange={(e) => setSearch((s) => ({ ...s, action: e.target.value }))}
                            >
                                <option value="">All actions</option>
                                {actions.map((a) => (
                                    <option key={a.value} value={a.value}>
                                        {a.label}
                                    </option>
                                ))}
                            </select>

                            <Input
                                type="date"
                                className="h-9 w-44"
                                value={search.date}
                                onChange={(e) => setSearch((s) => ({ ...s, date: e.target.value }))}
                            />

                            <Button size="sm" onClick={applyFilters}>
                                <Search className="mr-2 h-4 w-4" />
                                Filter
                            </Button>

                            {(filters.action || filters.date) && (
                                <Button size="sm" variant="ghost" onClick={clearFilters}>
                                    Clear
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Stats */}
                <div className="text-sm text-muted-foreground">
                    {logs.total} {logs.total === 1 ? 'entry' : 'entries'}
                    {logs.last_page > 1 && (
                        <> — page {logs.current_page} of {logs.last_page}</>
                    )}
                </div>

                {/* Log Table */}
                <Card>
                    <CardContent className="p-0">
                        {logs.data.length === 0 ? (
                            <div className="py-16 text-center text-sm text-muted-foreground">
                                No audit log entries found.
                            </div>
                        ) : (
                            <div className="divide-y">
                                {logs.data.map((log) => (
                                    <div key={log.id} className="flex flex-col gap-1 px-4 py-3 hover:bg-muted/40">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span
                                                className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${actionColors[log.action] ?? 'bg-gray-100 text-gray-700'}`}
                                            >
                                                {log.action_label}
                                            </span>
                                            <span className="text-sm font-medium">{log.performer_name}</span>
                                            {log.performer_code && (
                                                <span className="text-xs text-muted-foreground">({log.performer_code})</span>
                                            )}
                                            <span className="ml-auto text-xs text-muted-foreground">
                                                {log.performed_at_display}
                                            </span>
                                        </div>

                                        <div className="text-xs text-muted-foreground">
                                            {log.resource_type}
                                            {log.resource_id != null && ` #${log.resource_id}`}
                                            {log.ip_address && <> · {log.ip_address}</>}
                                        </div>

                                        <ValuesBlock label="Before" values={log.old_values} />
                                        <ValuesBlock label="After"  values={log.new_values} />
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Pagination */}
                {logs.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={!logs.prev_page_url}
                            onClick={() => logs.prev_page_url && router.get(logs.prev_page_url)}
                        >
                            Previous
                        </Button>
                        <span className="flex items-center px-3 text-sm text-muted-foreground">
                            {logs.current_page} / {logs.last_page}
                        </span>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={!logs.next_page_url}
                            onClick={() => logs.next_page_url && router.get(logs.next_page_url)}
                        >
                            Next
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
