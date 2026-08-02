<script setup lang="ts">
import { useForm, Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/Card.vue';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { PageProps } from '@/types';

const props = defineProps<{
    settings: {
        shop_name?: string;
        phone?: string;
        address?: string;
        currency?: string;
        invoice_prefix?: string;
        tax_rate?: string | number;
        timezone?: string;
    };
}>();

// Get typed page props
const page = usePage<PageProps>();

// Initialize inertia form
const form = useForm({
    shop_name: props.settings.shop_name ?? '',
    phone: props.settings.phone ?? '',
    address: props.settings.address ?? '',
    currency: props.settings.currency ?? 'USD',
    invoice_prefix: props.settings.invoice_prefix ?? 'INV-',
    tax_rate: String(props.settings.tax_rate ?? '0'),
    timezone: props.settings.timezone ?? 'UTC',
});

const submit = () => {
    form.post('/settings', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout>
        <Head title="System Settings" />

        <PageHeader title="Settings" :breadcrumbs="[{ name: 'Settings' }]" />

        <div class="max-w-4xl">
            <form @submit.prevent="submit">
                <Card title="Shop & Localization Configuration" subtitle="Configure the foundational metadata and tax properties for NovaPOS.">

                    <!-- Alert success notification -->
                    <div v-if="form.recentlySuccessful" class="mb-6 p-4 rounded-lg bg-green-50 text-green-700 text-sm font-medium dark:bg-green-950/30 dark:text-green-400">
                        Settings saved successfully! Future receipts, PDF invoices, and currencies have been updated.
                    </div>

                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6 mt-4">
                        <div class="sm:col-span-4">
                            <InputLabel for="shop_name" value="Shop Name" />
                            <TextInput
                                id="shop_name"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.shop_name"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.shop_name" />
                        </div>

                        <div class="sm:col-span-3">
                            <InputLabel for="phone" value="Phone Number" />
                            <TextInput
                                id="phone"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.phone"
                            />
                            <InputError class="mt-1" :message="form.errors.phone" />
                        </div>

                        <div class="sm:col-span-3">
                            <InputLabel for="currency" value="Currency Code (e.g. USD, EUR, GBP)" />
                            <TextInput
                                id="currency"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.currency"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.currency" />
                        </div>

                        <div class="sm:col-span-6">
                            <InputLabel for="address" value="Physical Shop Address" />
                            <textarea
                                id="address"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-800 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-900 dark:text-gray-100"
                                v-model="form.address"
                            />
                            <InputError class="mt-1" :message="form.errors.address" />
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel for="invoice_prefix" value="Invoice Prefix" />
                            <TextInput
                                id="invoice_prefix"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.invoice_prefix"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.invoice_prefix" />
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel for="tax_rate" value="Tax Rate (%)" />
                            <TextInput
                                id="tax_rate"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.tax_rate"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.tax_rate" />
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel for="timezone" value="Timezone" />
                            <TextInput
                                id="timezone"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.timezone"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.timezone" />
                        </div>
                    </div>

                    <template #footer>
                        <div class="flex justify-end">
                            <PrimaryButton :disabled="form.processing">
                                Save Settings
                            </PrimaryButton>
                        </div>
                    </template>
                </Card>
            </form>
        </div>
    </AppLayout>
</template>
