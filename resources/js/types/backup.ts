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
    logs: BackupAttendanceLog[];
};

export type BackupUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label?: string | null;
    employee_code?: string | null;
    position?: string | null;
    qr_value?: string | null;
    created_at?: string | null;
    attendance_day_count: number;
    attendance_log_count: number;
    attendance_days: BackupAttendanceDay[];
};

export type BackupSummary = {
    periodLabel: string;
    totalUsers: number;
    usersWithAttendance: number;
    attendanceDayCount: number;
    attendanceLogCount: number;
};
