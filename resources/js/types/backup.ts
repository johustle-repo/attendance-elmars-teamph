export type BackupMonthOption = {
    value: number;
    label: string;
};

export type BackupAttendanceLog = {
    id: number;
    entry_type: string;
    entry_type_label: string;
    recorded_at: string;
    recorded_time: string;
};

export type BackupAttendanceDay = {
    date: string;
    display_date: string;
    time_in?: string | null;
    time_out?: string | null;
    total_work_minutes?: number | null;
    total_work_hours?: string | null;
    logs: BackupAttendanceLog[];
};

export type BackupUser = {
    id: number;
    name: string;
    sub_name?: string | null;
    email: string;
    role: string;
    role_label?: string | null;
    employee_code?: string | null;
    position?: string | null;
    status?: string | null;
    status_label?: string | null;
    qr_value?: string | null;
    created_at?: string | null;
    attendance_day_count: number;
    attendance_log_count: number;
    total_work_minutes: number;
    total_work_hours: string;
    attendance_days: BackupAttendanceDay[];
};

export type BackupSummary = {
    periodLabel: string;
    totalUsers: number;
    usersWithAttendance: number;
    attendanceDayCount: number;
    attendanceLogCount: number;
    totalWorkHours: string;
};
