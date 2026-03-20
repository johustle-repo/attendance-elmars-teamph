import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, DatabaseBackup, LayoutGrid, QrCode, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem, User } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props as { auth: { user: User } };
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
            icon: LayoutGrid,
        },
        {
            title: 'QR Scanner',
            href: '/scan',
            icon: QrCode,
        },
        ...(auth.user.role === 'super_admin' || auth.user.role === 'admin'
            ? [
                  {
                      title: 'Users',
                      href: '/users',
                      icon: Users,
                  },
                  {
                      title: 'Attendance',
                      href: '/attendances',
                      icon: CalendarDays,
                  },
                  {
                      title: 'Backups',
                      href: '/backups',
                      icon: DatabaseBackup,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
