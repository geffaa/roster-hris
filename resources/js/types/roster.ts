export interface RosterUser {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    role: string | null;
    employee_id: number | null;
    two_factor: boolean;
}

export interface CompanyRef {
    id: number;
    name: string;
    country_code: string;
    base_currency?: string;
}

export interface RosterShared {
    name: string;
    auth: { user: RosterUser | null; can: Record<string, boolean> };
    company: CompanyRef | null;
    companies: CompanyRef[];
    flash: { success: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}
