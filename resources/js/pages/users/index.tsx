import { useEffect, useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { BadgePlus, Search } from 'lucide-react';
import { FlashMessage } from '@/components/flash-message';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Flash, ManagedUser, RoleOption } from '@/types';

type Props = {
    users: ManagedUser[];
    allowedRoles: RoleOption[];
    statusOptions: RoleOption[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/users' },
];

export default function UsersIndex({
    users,
    allowedRoles,
    statusOptions,
}: Props) {
    const { flash } = usePage().props as { flash: Flash };
    const [search, setSearch] = useState('');
    const [statusSelections, setStatusSelections] = useState<
        Record<number, string>
    >(() => Object.fromEntries(users.map((user) => [user.id, user.status])));
    const [updatingUserId, setUpdatingUserId] = useState<number | null>(null);
    const canCreateAdminAccounts = allowedRoles.some(
        (role) => role.value === 'admin',
    );
    const form = useForm({
        name: '',
        sub_name: '',
        email: '',
        role: allowedRoles[0]?.value ?? 'member',
        position: '',
        status: statusOptions[0]?.value ?? 'active',
        password: '',
    });

    useEffect(() => {
        setStatusSelections(
            Object.fromEntries(users.map((user) => [user.id, user.status])),
        );
    }, [users]);

    const filteredUsers = useMemo(() => {
        const query = search.toLowerCase().trim();

        if (!query) {
            return users;
        }

        return users.filter((user) =>
            [
                user.name,
                user.sub_name,
                user.email,
                user.employee_code,
                user.position,
                user.status_label,
            ]
                .filter(Boolean)
                .some((value) => value?.toLowerCase().includes(query)),
        );
    }, [search, users]);

    function submit() {
        form.post('/users', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset(
                    'name',
                    'sub_name',
                    'email',
                    'position',
                    'status',
                    'password',
                );
                form.setData('role', allowedRoles[0]?.value ?? 'member');
                form.setData('status', statusOptions[0]?.value ?? 'active');
            },
        });
    }

    function updateUserStatus(user: ManagedUser) {
        setUpdatingUserId(user.id);

        router.patch(
            `/users/${user.id}/status`,
            {
                status: statusSelections[user.id] ?? user.status,
            },
            {
                preserveScroll: true,
                onFinish: () => setUpdatingUserId(null),
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />

            <div className="flex flex-1 flex-col gap-6 bg-[linear-gradient(180deg,_rgba(236,254,255,0.5)_0%,_rgba(248,250,252,0)_24%)] p-4 md:p-6 dark:bg-[linear-gradient(180deg,_rgba(8,47,73,0.28)_0%,_rgba(2,6,23,0)_24%)]">
                <FlashMessage flash={flash} />

                <section className="overflow-hidden rounded-[2rem] border border-cyan-100 bg-[linear-gradient(135deg,_#ecfeff_0%,_#f8fafc_48%,_#eff6ff_100%)] p-5 sm:p-8 dark:border-cyan-500/20 dark:bg-[linear-gradient(135deg,_rgba(8,47,73,0.34)_0%,_rgba(15,23,42,0.96)_48%,_rgba(2,6,23,0.98)_100%)]">
                    <div className="grid gap-6 lg:grid-cols-[0.86fr_1.14fr]">
                        <Card className="border-cyan-100 bg-white/90 shadow-sm backdrop-blur dark:border-cyan-500/20 dark:bg-slate-950/80">
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <div className="rounded-2xl bg-cyan-50 p-3 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-200">
                                        <BadgePlus className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <CardTitle>Add user</CardTitle>
                                        <CardDescription>
                                            {canCreateAdminAccounts
                                                ? 'Create a new member or admin account with a QR identity, alias sub name, and agent status.'
                                                : 'Create a new member account with a QR identity, alias sub name, and agent status.'}
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-2">
                                    <Input
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Full name"
                                    />
                                    <InputError message={form.errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Input
                                        value={form.data.sub_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'sub_name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Sub name / alias"
                                    />
                                    <InputError
                                        message={form.errors.sub_name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Input
                                        type="email"
                                        value={form.data.email}
                                        onChange={(event) =>
                                            form.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Email address"
                                    />
                                    <InputError message={form.errors.email} />
                                </div>

                                <div className="grid gap-2 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <select
                                            value={form.data.role}
                                            onChange={(event) =>
                                                form.setData(
                                                    'role',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-10 rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-slate-950 dark:text-slate-100 dark:[&_option]:bg-slate-950"
                                        >
                                            {allowedRoles.map((role) => (
                                                <option
                                                    key={role.value}
                                                    value={role.value}
                                                >
                                                    {role.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={form.errors.role}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <select
                                            value={form.data.status}
                                            onChange={(event) =>
                                                form.setData(
                                                    'status',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-10 rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-slate-950 dark:text-slate-100 dark:[&_option]:bg-slate-950"
                                        >
                                            {statusOptions.map((status) => (
                                                <option
                                                    key={status.value}
                                                    value={status.value}
                                                >
                                                    {status.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={form.errors.status}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Input
                                        value={form.data.position}
                                        onChange={(event) =>
                                            form.setData(
                                                'position',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Position"
                                    />
                                    <InputError
                                        message={form.errors.position}
                                    />
                                </div>

                                <div className="rounded-2xl border border-dashed border-cyan-200 bg-cyan-50/60 px-4 py-3 text-sm text-cyan-900 dark:border-cyan-500/20 dark:bg-cyan-500/10 dark:text-cyan-100">
                                    Employee code will be generated
                                    automatically when this user is saved.
                                </div>

                                <div className="grid gap-2">
                                    <Input
                                        type="password"
                                        value={form.data.password}
                                        onChange={(event) =>
                                            form.setData(
                                                'password',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Temporary password"
                                    />
                                    <InputError
                                        message={form.errors.password}
                                    />
                                </div>

                                <Button
                                    type="button"
                                    onClick={submit}
                                    disabled={form.processing}
                                    className="w-full bg-slate-950 text-white hover:bg-slate-800 dark:bg-cyan-500 dark:text-slate-950 dark:hover:bg-cyan-400"
                                >
                                    Save user
                                </Button>
                            </CardContent>
                        </Card>

                        <div className="space-y-5">
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <p className="text-sm font-semibold tracking-[0.24em] text-cyan-700 uppercase dark:text-cyan-300">
                                        User directory
                                    </p>
                                    <h1 className="mt-2 text-3xl font-semibold text-slate-950 dark:text-slate-50">
                                        Named QR cards and agent status controls
                                    </h1>
                                </div>

                                <div className="relative w-full max-w-sm">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Search name, alias, email, code, or status"
                                        className="border-white/70 bg-white/90 pl-9 dark:border-slate-800 dark:bg-slate-950/80"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <Card className="border-white/80 bg-white/90 dark:border-slate-800 dark:bg-slate-950/80">
                                    <CardHeader className="space-y-1">
                                        <CardDescription>
                                            Total users
                                        </CardDescription>
                                        <CardTitle className="text-3xl">
                                            {users.length}
                                        </CardTitle>
                                    </CardHeader>
                                </Card>
                                <Card className="border-white/80 bg-white/90 dark:border-slate-800 dark:bg-slate-950/80">
                                    <CardHeader className="space-y-1">
                                        <CardDescription>
                                            Admins
                                        </CardDescription>
                                        <CardTitle className="text-3xl">
                                            {
                                                users.filter((user) =>
                                                    [
                                                        'admin',
                                                        'super_admin',
                                                    ].includes(user.role),
                                                ).length
                                            }
                                        </CardTitle>
                                    </CardHeader>
                                </Card>
                                <Card className="border-white/80 bg-white/90 dark:border-slate-800 dark:bg-slate-950/80">
                                    <CardHeader className="space-y-1">
                                        <CardDescription>
                                            Active
                                        </CardDescription>
                                        <CardTitle className="text-3xl">
                                            {
                                                users.filter(
                                                    (user) =>
                                                        user.status ===
                                                        'active',
                                                ).length
                                            }
                                        </CardTitle>
                                    </CardHeader>
                                </Card>
                                <Card className="border-white/80 bg-white/90 dark:border-slate-800 dark:bg-slate-950/80">
                                    <CardHeader className="space-y-1">
                                        <CardDescription>
                                            Inactive
                                        </CardDescription>
                                        <CardTitle className="text-3xl">
                                            {
                                                users.filter(
                                                    (user) =>
                                                        user.status ===
                                                        'inactive',
                                                ).length
                                            }
                                        </CardTitle>
                                    </CardHeader>
                                </Card>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="grid gap-5 lg:grid-cols-2">
                    {filteredUsers.map((user) => (
                        <div
                            key={user.id}
                            className="rounded-[2rem] border border-slate-200/80 bg-white/92 p-6 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-950/82"
                        >
                            <div className="mb-5 flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="text-xl font-semibold text-slate-950 dark:text-slate-50">
                                        {user.name}
                                    </p>
                                    {user.sub_name && (
                                        <p className="mt-1 text-sm font-medium text-cyan-700 dark:text-cyan-300">
                                            {user.sub_name}
                                        </p>
                                    )}
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        {user.email}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="outline">
                                        {user.role_label}
                                    </Badge>
                                    <Badge
                                        variant="outline"
                                        className={
                                            user.status === 'active'
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200'
                                                : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200'
                                        }
                                    >
                                        {user.status_label}
                                    </Badge>
                                </div>
                            </div>

                            <div className="mb-5 grid gap-3 text-sm text-slate-600 md:grid-cols-2 dark:text-slate-400">
                                <div className="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-900">
                                    <p className="text-xs tracking-[0.18em] text-slate-400 uppercase">
                                        Employee code
                                    </p>
                                    <p className="mt-1 font-medium text-slate-900 dark:text-slate-100">
                                        {user.employee_code}
                                    </p>
                                </div>
                                <div className="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-900">
                                    <p className="text-xs tracking-[0.18em] text-slate-400 uppercase">
                                        Position
                                    </p>
                                    <p className="mt-1 font-medium text-slate-900 dark:text-slate-100">
                                        {user.position ?? 'Not set'}
                                    </p>
                                </div>
                                <div className="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-900">
                                    <p className="text-xs tracking-[0.18em] text-slate-400 uppercase">
                                        Status
                                    </p>
                                    <p className="mt-1 font-medium text-slate-900 dark:text-slate-100">
                                        {user.status_label}
                                    </p>
                                </div>
                                <div className="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-900">
                                    <p className="text-xs tracking-[0.18em] text-slate-400 uppercase">
                                        Attendance logs
                                    </p>
                                    <p className="mt-1 font-medium text-slate-900 dark:text-slate-100">
                                        {user.attendance_count}
                                    </p>
                                </div>
                                <div className="rounded-2xl bg-slate-50 px-4 py-3 md:col-span-2 dark:bg-slate-900">
                                    <p className="text-xs tracking-[0.18em] text-slate-400 uppercase">
                                        Added
                                    </p>
                                    <p className="mt-1 font-medium text-slate-900 dark:text-slate-100">
                                        {user.created_at}
                                    </p>
                                </div>
                            </div>

                            <div className="mb-5 rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                                <div className="flex flex-wrap items-end gap-3">
                                    <div className="grid w-full gap-2 sm:w-auto sm:min-w-[180px]">
                                        <p className="text-xs font-semibold tracking-[0.18em] text-slate-400 uppercase">
                                            Agent status
                                        </p>
                                        <select
                                            value={
                                                statusSelections[user.id] ??
                                                user.status
                                            }
                                            onChange={(event) =>
                                                setStatusSelections(
                                                    (current) => ({
                                                        ...current,
                                                        [user.id]:
                                                            event.target.value,
                                                    }),
                                                )
                                            }
                                            className="h-10 rounded-md border border-input bg-white px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-slate-950 dark:text-slate-100 dark:[&_option]:bg-slate-950"
                                        >
                                            {statusOptions.map((status) => (
                                                <option
                                                    key={status.value}
                                                    value={status.value}
                                                >
                                                    {status.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <Button
                                        type="button"
                                        onClick={() => updateUserStatus(user)}
                                        disabled={
                                            updatingUserId === user.id ||
                                            (statusSelections[user.id] ??
                                                user.status) === user.status
                                        }
                                        variant="outline"
                                    >
                                        Save status
                                    </Button>
                                </div>
                            </div>

                            <QrIdentityCard
                                name={user.name}
                                subtitle={
                                    user.sub_name
                                        ? `${user.sub_name} - ${user.employee_code ?? user.position ?? ''}`.replace(
                                              / - $/,
                                              '',
                                          )
                                        : (user.employee_code ?? user.position)
                                }
                                value={user.qr_value}
                                compact={true}
                            />
                        </div>
                    ))}

                    {filteredUsers.length === 0 && (
                        <div className="col-span-full rounded-[2rem] border border-dashed border-slate-200 bg-white/90 p-10 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-400">
                            No users match your search.
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
