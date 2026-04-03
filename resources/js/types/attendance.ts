export type DashboardStats = {
    totalUsers: number;
    totalAdmins: number;
    attendanceToday: number;
    presentToday: number;
    firebaseConfigured: boolean;
};

export type MemberSummary = {
    totalAttendances: number;
    todayAttendances: number;
    lastEntryType?: string | null;
    lastEntryTypeLabel: string;
    firebaseConfigured: boolean;
};

export type AttendanceItem = {
    id: number;
    user_name: string;
    user_email?: string;
    employee_code?: string;
    entry_type?: string;
    entry_type_label?: string;
    attendance_status?: 'late' | 'on_time' | 'no_time_in' | null;
    status_label?: string | null;
    status_hint?: string | null;
    late_minutes?: number | null;
    recorded_at: string;
    recorded_date: string;
    recorded_time: string;
    display_date?: string;
    display_time?: string;
    source?: string;
};

export type AttendanceSummaryItem = {
    key: string;
    user_id: number;
    date: string;
    display_date: string;
    employee_code?: string | null;
    user_name: string;
    user_email?: string | null;
    time_in_attendance_id?: number | null;
    time_in_date?: string | null;
    time_in_time?: string | null;
    time_in_display?: string | null;
    attendance_status: 'late' | 'on_time' | 'no_time_in';
    status_label: string;
    status_hint: string;
    late_minutes?: number | null;
    time_out_attendance_id?: number | null;
    time_out_date?: string | null;
    time_out_time?: string | null;
    time_out_display?: string | null;
    total_hours_label?: string | null;
};

export type ManagedUser = {
    id: number;
    name: string;
    sub_name?: string | null;
    email: string;
    role: string;
    role_label: string;
    employee_code?: string;
    position?: string;
    status: 'active' | 'inactive';
    status_label: string;
    qr_value?: string | null;
    attendance_count: number;
    created_at: string;
};

export type RoleOption = {
    value: string;
    label: string;
};
