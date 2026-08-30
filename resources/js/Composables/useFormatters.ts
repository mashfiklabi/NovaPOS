import { usePage } from '@inertiajs/vue3';
import { PageProps } from '@/types';

const CURRENCY_SYMBOLS: Record<string, string> = {
    USD: '$',
    BDT: '৳',
    EUR: '€',
    GBP: '£',
    INR: '₹',
    CAD: 'CA$',
    AUD: 'A$',
    JPY: '¥',
};

export function formatCurrency(amount: number | string | null | undefined): string {
    const num = Number(amount || 0);
    const formatted = num.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    try {
        const page = usePage<PageProps>();
        const settingCurrency = page.props.settings?.currency || 'USD';
        const symbol = CURRENCY_SYMBOLS[settingCurrency.toUpperCase()] || settingCurrency;
        return `${symbol}${formatted}`;
    } catch {
        return `$${formatted}`;
    }
}

export function formatDate(dateString: string | null | undefined): string {
    if (!dateString) return 'N/A';

    const trimmed = String(dateString).trim();

    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
        const [year, month, day] = trimmed.split('-');
        return `${day}-${month}-${year}`;
    }

    const date = new Date(trimmed);
    if (isNaN(date.getTime())) return trimmed;

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();

    let hours = date.getHours();
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const strHours = String(hours).padStart(2, '0');

    if (date.getHours() === 0 && date.getMinutes() === 0 && date.getSeconds() === 0) {
        return `${day}-${month}-${year}`;
    }

    return `${day}-${month}-${year} ${strHours}:${minutes} ${ampm}`;
}
