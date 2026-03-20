import { useMemo, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { BadgePlus, Search, UsersRound } from 'lucide-react';
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
import type {
    BreadcrumbItem,
    Flash,
    ManagedUser,
    RoleOption,
} from '@/types';

type Props = {
    users: ManagedUser[];
    allowedRoles: RoleOption[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/users' },
];

export default function UsersIndex({ users, allowedRoles }: Props) {
    const { flash } = usePage().props as { flash: Flash };
    const [search, setSearch] = useState('');
    const canCreatePrivilegedAccounts = allowedRoles.some((role) =>
        ['super_admin', 'admin'].includes(role.value),
    );
    const form = useForm({
        name: '',
        email: '',
        role: allowedRoles[0]?.value ?? 'member',
        employee_code: '',
        position: '',
        password: '',
    });

    const filteredUsers = useMemo(() => {
        const query = search.toLowerCase().trim();

        if (!query) {
            return users;
        }

        return users.filter((user) =>
            [user.name, user.email, user.employee_code, user.position]
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
                    'email',
                    'employee_code',
                    'position',
                    'password',
                );
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />

            <div className="flex flex-1 flex-col gap-6 bg-[linear-gradient(180deg,_rgba(236,254,255,0.5)_0%,_rgba(248,250,252,0)_24%)] p-4 md:p-6">
                <FlashMessage flash={flash} />

                <section className="overflow-hidden rounded-[2rem] border border-cyan-100 bg-[linear-gradient(135deg,_#ecfeff_0%,_#f8fafc_48%,_#eff6ff_100%)] p-8">
                    <div className="grid gap-6 xl:grid-cols-[0.86fr_1.14fr]">
                        <Card className="border-cyan-100 bg-white/90 shadow-sm backdrop-blur">
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <div className="rounded-2xl bg-cyan-50 p-3 text-cyan-700">
                                        <BadgePlus className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <CardTitle>Add user</CardTitle>
                                        <CardDescription>
                                            {canCreatePrivilegedAccounts
                                                ? 'Create a new member, admin, or super admin account with a QR identity.'
                                                : 'Create a new member account with a QR identity.'}
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-2">
                                    <Input
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData('name', event.target.value)
                                        }
                                        placeholder="Full name"
                                    />
                                    <InputError message={form.errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Input
                                        value={form.data.email}
                                        onChange={(event) =>
                                            form.setData('email', event.target.value)
                                        }
                                        placeholder="Email address"
                                    />
                                    <InputError message={form.errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <select
                                        value={form.data.role}
                                        onChange={(event) =>
                                            form.setData('role', event.target.value)
                                        }
                                        className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-10 rounded-md border bg-transparent px-3 text-sm outline-none focus-visible:ring-[3px]"
                                    >
                                        {allowedRoles.map((role) => (
                                            <option key={role.value} value={role.value}>
                                                {role.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={form.errors.role} />
                                </div>

                                <div className="grid gap-2 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Input
                                            value={form.data.employee_code}
                                            onChange={(event) =>
                                                form.setData(
                                                    'employee_code',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Employee code"
                                        />
                                        <InputError
                                            message={form.errors.employee_code}
                                        />
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
                                    <InputError message={form.errors.password} />
                                </div>

                                <Button
                                    type="button"
                                    onClick={submit}
                                    disabled={form.processing}
                                    className="w-full bg-slate-950 text-white hover:bg-slate-800"
                                >
                                    Save user
                                </Button>
                            </CardContent>
                        </Card>

                        <div className="space-y-5">
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <p className="text-sm font-semibold uppercase tracking-[0.24em] text-cyan-700">
                                        User directory
                                    </p>
                                    <h1 className="mt-2 text-3xl font-semibold text-slate-950">
                                        Named QR cards, cleaner user management
                                    </h1>
                                </div>

                                <div className="relative w-full max-w-sm">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Search name, email, code"
                                        className="border-white/70 bg-white/90 pl-9"
                                    />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <Card className="border-white/80 bg-white/90">
                                    <CardHeader className="space-y-1">
                                        <CardDescription>Total users</CardDescription>
                                        <CardTitle className="text-3xl">
                                            {users.length}
                                        </CardTitle>
                                    </CardHeader>
                                </Card>
                                <Card className="border-white/80 bg-white/90">
                                    <CardHeader className="space-y-1">
                                        <CardDescription>Admins</CardDescription>
                                        <CardTitle className="text-3xl">
                                            {
                                                users.filter((user) =>
                                                    ['admin', 'super_admin'].includes(
                                                        user.role,
                                                    ),
                                                ).length
                                            }
                                        </CardTitle>
                                    </CardHeader>
                                </Card>
                                <Card className="border-white/80 bg-white/90">
                                    <CardHeader className="space-y-1">
                                        <CardDescription>Members</CardDescription>
                                        <CardTitle className="text-3xl">
                                            {
                                                users.filter(
                                                    (user) => user.role === 'member',
                                                ).length
                                            }
                                        </CardTitle>
                                    </CardHeader>
                                </Card>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="grid gap-5 xl:grid-cols-2">
                    {filteredUsers.map((user) => (
                        <div
                            key={user.id}
                            className="rounded-[2rem] border border-slate-200/80 bg-white/92 p-6 shadow-sm backdrop-blur"
                        >
                            <div className="mb-5 flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="text-xl font-semibold text-slate-950">
                                        {user.name}
                                    </p>
                                    <p className="text-sm text-slate-500">
                                        {user.email}
                                    </p>
                                </div>
                                <Badge variant="outline">{user.role_label}</Badge>
                            </div>

                            <div className="mb-5 grid gap-3 text-sm text-slate-600 md:grid-cols-2">
                                <div className="rounded-2xl bg-slate-50 px-4 py-3">
                                    <p className="text-xs uppercase tracking-[0.18em] text-slate-400">
                                        Employee code
                                    </p>
                                    <p className="mt-1 font-medium text-slate-900">
                                        {user.employee_code}
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
                                        Attendance logs
                                    </p>
                                    <p className="mt-1 font-medium text-slate-900">
                                        {user.attendance_count}
                                    </p>
                                </div>
                                <div className="rounded-2xl bg-slate-50 px-4 py-3">
                                    <p className="text-xs uppercase tracking-[0.18em] text-slate-400">
                                        Added
                                    </p>
                                    <p className="mt-1 font-medium text-slate-900">
                                        {user.created_at}
                                    </p>
                                </div>
                            </div>

                            <QrIdentityCard
                                name={user.name}
                                subtitle={user.employee_code ?? user.position}
                                value={user.qr_value}
                                compact={true}
                            />
                        </div>
                    ))}

                    {filteredUsers.length === 0 && (
                        <div className="col-span-full rounded-[2rem] border border-dashed border-slate-200 bg-white/90 p-10 text-center text-sm text-slate-500">
                            No users match your search.
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
