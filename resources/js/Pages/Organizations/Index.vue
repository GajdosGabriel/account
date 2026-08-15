<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';
import OrganizationFilter from '../../Components/OrganizationFilter.vue';
import Pagination from '../../Components/Pagination.vue';
import RowActions from '../../Components/RowActions.vue';
import { t } from '../../Composables/useLang';

defineProps({
    organizations: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    /** Zoznam je prepnutý na kôš – rozhoduje o tom filter stavu. */
    trashed: { type: Boolean, default: false },
    trashed_count: { type: Number, default: 0 },
});

const statusStyles = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    suspended: 'bg-orange-50 text-orange-800 ring-orange-600/20',
    archived: 'bg-slate-100 text-slate-600 ring-slate-500/20',
};
</script>

<template>
    <Head :title="t('organizations.title')" />

    <PageHeader :title="t('organizations.title')" :subtitle="t('organizations.subtitle')">
        <template #action>
            <Link href="/organizations/create" class="btn-primary">
                <Icon name="plus" :size="17" />
                {{ t('organizations.create') }}
            </Link>
        </template>
    </PageHeader>

    <OrganizationFilter
        :filters="filters"
        :products="products"
        :statuses="statuses"
        :trashed-count="trashed_count"
    />

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3 font-medium">{{ t('organizations.table.name') }}</th>
                    <th class="px-5 py-3 font-medium">{{ t('organizations.table.ico') }}</th>
                    <th class="px-5 py-3 font-medium">{{ t('organizations.table.vat_number') }}</th>
                    <th class="px-5 py-3 font-medium">{{ t('organizations.table.city') }}</th>
                    <th class="px-5 py-3 font-medium">{{ t('organizations.table.status') }}</th>
                    <th class="px-5 py-3 text-right font-medium">{{ t('organizations.table.products') }}</th>
                    <th class="w-12 px-2 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr
                    v-for="org in organizations.data"
                    :key="org.id"
                    class="transition hover:bg-brand-50/40"
                    :class="org.deleted_at ? 'opacity-60' : ''"
                >
                    <td class="px-5 py-3">
                        <Link :href="`/organizations/${org.id}`" class="font-medium text-slate-900 hover:text-brand-700 hover:underline">
                            {{ org.name }}
                        </Link>
                        <span v-if="org.verified" class="ml-2 text-xs text-emerald-600">
                            {{ t('organizations.table.verified') }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-slate-600">{{ org.ico ?? '—' }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ org.ic_dph ?? '—' }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ org.city ?? '—' }}</td>
                    <td class="px-5 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusStyles[org.status]">
                            {{ t(`enums.organization_status.${org.status}`) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right font-medium text-slate-700">{{ org.products_count }}</td>
                    <td class="px-2 py-3 text-right">
                        <RowActions
                            :abilities="org.can"
                            :trashed="!!org.deleted_at"
                            :base="`/organizations/${org.id}`"
                            :name="org.name"
                            :edit-href="`/organizations/${org.id}/edit`"
                        />
                    </td>
                </tr>
                <tr v-if="organizations.data.length === 0">
                    <!-- Prázdny výpis pri zapnutom filtri vyzerá rovnako ako prázdna
                         evidencia — bez rozlíšenia človek hľadá chybu v dátach. -->
                    <td colspan="7" class="px-5 py-12 text-center text-slate-500">
                        <template v-if="trashed">{{ t('organizations.empty.trashed') }}</template>
                        <template v-else>
                            {{ Object.keys(filters).length ? t('organizations.empty.filtered') : t('organizations.empty.none') }}
                        </template>
                    </td>
                </tr>
            </tbody>
        </table>

        <Pagination :meta="organizations" :label="t('organizations.records')" />
    </div>
</template>
