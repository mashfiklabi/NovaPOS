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

export interface Customer {
    id: number;
    uuid: string;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country: string | null;
    tax_number: string | null;
    status: 'active' | 'inactive';
    created_at: string;
    deleted_at: string | null;
}

export interface POSProduct {
    id: number;
    name: string;
    sku: string;
    barcode: string | null;
    selling_price: string | number;
    current_stock: string | number;
    allow_decimal: boolean;
    tax_type: string | null;
    tax_rate: string | number;
    unit?: {
        id: number;
        name: string;
        short_name: string;
    } | null;
}

export interface SaleItem {
    id: number;
    sale_id: number;
    product_id: number;
    unit_id: number | null;
    quantity: number | string;
    unit_price: number | string;
    discount_amount: number | string;
    tax_amount: number | string;
    subtotal: number | string;
    total: number | string;
    product?: {
        id: number;
        name: string;
        code: string;
    };
}

export interface SalePayment {
    id: number;
    sale_id: number;
    uuid: string;
    user_id: number;
    payment_method: string;
    amount: number | string;
    reference_number: string | null;
    paid_at: string;
    notes: string | null;
    user?: {
        id: number;
        name: string;
    };
}

export interface Sale {
    id: number;
    uuid: string;
    invoice_number: string;
    customer_id: number | null;
    user_id: number;
    sale_date: string;
    subtotal: number | string;
    discount_amount: number | string;
    tax_amount: number | string;
    shipping_cost: number | string;
    grand_total: number | string;
    paid_amount: number | string;
    due_amount: number | string;
    payment_status: 'paid' | 'partial' | 'unpaid';
    status: 'draft' | 'completed' | 'cancelled';
    reference_number: string | null;
    notes: string | null;
    created_at: string;
    deleted_at: string | null;
    customer?: Customer | null;
    user?: User;
    items?: SaleItem[];
    payments?: SalePayment[];
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
        permissions?: string[];
    };
    settings: Settings;
    navigation: NavigationItem[];
};
