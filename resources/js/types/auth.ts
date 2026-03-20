export type User = {
    id: number;
    name: string;
    email: string;
    role: 'super_admin' | 'admin' | 'member';
    employee_code?: string | null;
    position?: string | null;
    qr_value?: string | null;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Flash = {
    success?: string | null;
    error?: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
