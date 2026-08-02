export interface User {
    id: number;
    uuid: string;
    name: string;
    email: string;
    status: string;
    roles: string[];
    permissions: string[];
    email_verified_at?: string;
}

export interface Settings {
    shop_name: string;
    currency: string;
    tax_rate: string;
    phone: string;
    address: string;
    invoice_prefix: string;
    timezone: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    settings: Settings;
};
