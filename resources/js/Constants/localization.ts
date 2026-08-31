export interface CurrencyOption {
    code: string;
    symbol: string;
    label: string;
}

export interface SelectOption {
    value: string;
    label: string;
}

export const CURRENCY_OPTIONS: CurrencyOption[] = [
    { code: 'BDT', symbol: '৳', label: 'BDT — ৳ — Bangladeshi Taka' },
    { code: 'USD', symbol: '$', label: 'USD — $ — US Dollar' },
    { code: 'EUR', symbol: '€', label: 'EUR — € — Euro' },
    { code: 'GBP', symbol: '£', label: 'GBP — £ — British Pound' },
    { code: 'INR', symbol: '₹', label: 'INR — ₹ — Indian Rupee' },
    { code: 'JPY', symbol: '¥', label: 'JPY — ¥ — Japanese Yen' },
    { code: 'CNY', symbol: '¥', label: 'CNY — ¥ — Chinese Yuan' },
    { code: 'CAD', symbol: 'CA$', label: 'CAD — CA$ — Canadian Dollar' },
    { code: 'AUD', symbol: 'A$', label: 'AUD — A$ — Australian Dollar' },
    { code: 'CHF', symbol: 'CHF', label: 'CHF — CHF — Swiss Franc' },
    { code: 'SAR', symbol: '﷼', label: 'SAR — ﷼ — Saudi Riyal' },
    { code: 'AED', symbol: 'د.إ', label: 'AED — د.إ — UAE Dirham' },
    { code: 'SGD', symbol: '$', label: 'SGD — $ — Singapore Dollar' },
    { code: 'MYR', symbol: 'RM', label: 'MYR — RM — Malaysian Ringgit' },
    { code: 'PKR', symbol: '₨', label: 'PKR — ₨ — Pakistani Rupee' },
];

export const TIMEZONE_OPTIONS: SelectOption[] = [
    { value: 'Asia/Dhaka', label: 'Asia/Dhaka — Bangladesh' },
    { value: 'Asia/Kolkata', label: 'Asia/Kolkata — India' },
    { value: 'Asia/Karachi', label: 'Asia/Karachi — Pakistan' },
    { value: 'Asia/Dubai', label: 'Asia/Dubai — UAE' },
    { value: 'Asia/Singapore', label: 'Asia/Singapore — Singapore' },
    { value: 'Asia/Tokyo', label: 'Asia/Tokyo — Japan' },
    { value: 'Asia/Shanghai', label: 'Asia/Shanghai — China' },
    { value: 'Europe/London', label: 'Europe/London — United Kingdom' },
    { value: 'Europe/Paris', label: 'Europe/Paris — France' },
    { value: 'Europe/Berlin', label: 'Europe/Berlin — Germany' },
    { value: 'Europe/Rome', label: 'Europe/Rome — Italy' },
    { value: 'Europe/Madrid', label: 'Europe/Madrid — Spain' },
    { value: 'America/New_York', label: 'America/New_York — US Eastern' },
    { value: 'America/Chicago', label: 'America/Chicago — US Central' },
    { value: 'America/Denver', label: 'America/Denver — US Mountain' },
    { value: 'America/Los_Angeles', label: 'America/Los_Angeles — US Pacific' },
    { value: 'Australia/Sydney', label: 'Australia/Sydney — Australia' },
    { value: 'UTC', label: 'UTC — Coordinated Universal Time' },
];
