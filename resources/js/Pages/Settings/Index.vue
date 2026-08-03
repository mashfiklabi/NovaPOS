<script setup lang="ts">
import { useForm, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/AppCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppInput from '@/Components/AppInput.vue';
import AppButton from '@/Components/AppButton.vue';
import AppTextarea from '@/Components/AppTextarea.vue';

interface SettingItem {
    id: number;
    key: string;
    value: string | null;
    group: string;
    type: string;
}

const props = defineProps<{
    settings: {
        shop_name?: string;
        phone?: string;
        email?: string;
        address?: string;
        currency?: string;
        timezone?: string;
        invoice_prefix?: string;
        tax_rate?: string | number;
        logo?: string | null;
        favicon?: string | null;
    };
    grouped_settings: Record<string, SettingItem[]>;
}>();

// Initialize Inertia form with file upload support
const form = useForm({
    shop_name: props.settings.shop_name ?? '',
    phone: props.settings.phone ?? '',
    email: props.settings.email ?? '',
    address: props.settings.address ?? '',
    currency: props.settings.currency ?? 'USD',
    timezone: props.settings.timezone ?? 'UTC',
    invoice_prefix: props.settings.invoice_prefix ?? 'INV-',
    tax_rate: String(props.settings.tax_rate ?? '0'),
    logo: null as File | null,
    favicon: null as File | null,
});

const handleFile = (field: 'logo' | 'favicon', event: Event) => {
    const files = (event.target as HTMLInputElement).files;
    if (files && files.length > 0) {
        form[field] = files[0];
    }
};

const submit = () => {
    // We use post to /settings to allow file uploads (multipart/form-data)
    form.post('/settings', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout>
        <Head title="System Configuration" />

        <PageHeader title="Settings" :breadcrumbs="[{ name: 'Settings' }]" />

        <div class="max-w-4xl space-y-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Group 1: General Settings -->
                <AppCard title="General Information" subtitle="Configure shop contact details, physical address, and names.">
                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6 mt-4">
                        <div class="sm:col-span-4">
                            <AppInput
                                label="Shop Name"
                                v-model="form.shop_name"
                                :error="form.errors.shop_name"
                                required
                            />
                        </div>

                        <div class="sm:col-span-3">
                            <AppInput
                                label="Phone Number"
                                v-model="form.phone"
                                :error="form.errors.phone"
                            />
                        </div>

                        <div class="sm:col-span-3">
                            <AppInput
                                label="Contact Email"
                                type="email"
                                v-model="form.email"
                                :error="form.errors.email"
                                required
                            />
                        </div>

                        <div class="sm:col-span-6">
                            <AppTextarea
                                label="Physical Address"
                                v-model="form.address"
                                :error="form.errors.address"
                            />
                        </div>
                    </div>
                </AppCard>

                <!-- Group 2: POS & Pricing Settings -->
                <AppCard title="Localization & POS Checkout Properties" subtitle="Adjust invoice styles, standard taxes, and currency attributes.">
                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6 mt-4">
                        <div class="sm:col-span-3">
                            <AppInput
                                label="Currency Symbol/Code"
                                v-model="form.currency"
                                :error="form.errors.currency"
                                required
                            />
                        </div>

                        <div class="sm:col-span-3">
                            <AppInput
                                label="Local Timezone"
                                v-model="form.timezone"
                                :error="form.errors.timezone"
                                required
                            />
                        </div>

                        <div class="sm:col-span-3">
                            <AppInput
                                label="Invoice Prefix"
                                v-model="form.invoice_prefix"
                                :error="form.errors.invoice_prefix"
                                required
                            />
                        </div>

                        <div class="sm:col-span-3">
                            <AppInput
                                label="Default Tax Rate (%)"
                                type="text"
                                v-model="form.tax_rate"
                                :error="form.errors.tax_rate"
                                required
                            />
                        </div>
                    </div>
                </AppCard>

                <!-- Group 3: Branding & Appearance -->
                <AppCard title="Appearance & Branding" subtitle="Upload customized logos and favicons for receipts and invoices.">
                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6 mt-4">
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Store Logo</label>
                            <input
                                type="file"
                                accept="image/*"
                                @change="handleFile('logo', $event)"
                                class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                            <p v-if="settings.logo" class="mt-1 text-[10px] text-gray-400">Current: {{ settings.logo }}</p>
                            <p v-if="form.errors.logo" class="mt-1 text-xs text-red-600">{{ form.errors.logo }}</p>
                        </div>

                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Favicon Icon</label>
                            <input
                                type="file"
                                accept="image/*"
                                @change="handleFile('favicon', $event)"
                                class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                            <p v-if="settings.favicon" class="mt-1 text-[10px] text-gray-400">Current: {{ settings.favicon }}</p>
                            <p v-if="form.errors.favicon" class="mt-1 text-xs text-red-600">{{ form.errors.favicon }}</p>
                        </div>
                    </div>
                </AppCard>

                <!-- Save Footer -->
                <div class="flex justify-end pt-2">
                    <AppButton type="submit" variant="primary" :loading="form.processing">
                        Save System Settings
                    </AppButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
