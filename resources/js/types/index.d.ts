export interface User {
    id: number;
    uuid: string;
    name: string;
    email: string;
    status: string;
    phone: string | null;
    avatar: string | null;
    roles: string[];
    permissions: string[];
    email_verified_at?: string;
}

export interface Settings {
    shop_name: string;
    currency: string;
    tax_rate: string;
    phone: string;
    email: string;
    address: string;
    invoice_prefix: string;
    timezone: string;
    logo: string | null;
    favicon: string | null;
}

export interface NavigationItem {
    title: string;
    route: string;
    icon: string;
    permission: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    settings: Settings;
    navigation: NavigationItem[];
};
