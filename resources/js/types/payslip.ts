export type PayslipDay = {
    date: string;
    display_date: string;
    time_in?: string | null;
    time_out?: string | null;
    worked_minutes?: number | null;
    worked_hours?: number | null;
    worked_hours_label: string;
};

export type PayslipItem = {
    user_id: number;
    name: string;
    sub_name?: string | null;
    email: string;
    employee_code?: string | null;
    position?: string | null;
    hourly_rate: number;
    days_worked: number;
    total_minutes: number;
    total_hours: number;
    total_pay: number;
    attendance_days: PayslipDay[];
};

export type PayslipSummary = {
    totalEmployees: number;
    totalHours: number;
    totalPay: number;
};

export type MonthOption = {
    value: number;
    label: string;
};
