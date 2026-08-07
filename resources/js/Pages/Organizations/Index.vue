<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';
import OrganizationFilter from '../../Components/OrganizationFilter.vue';
import Pagination from '../../Components/Pagination.vue';

defineProps({
    organizations: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const statusStyles = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    suspended: 'bg-orange-50 text-orange-800 ring-orange-600/20',
    archived: 'bg-slate-100 text-slate-600 ring-slate-500/20',
};
</script>

<template>
    <Head title="Organizácie" />

    <PageHeader title="Organizácie" subtitle="Jeden zdroj pravdy pre všetky pripojené projekty.">
        <template #action>
            <Link href="/organizations/create" class="btn-primary">
                <Icon name="plus" :size="17" />
                Nová organizácia
            </Link>
        </template>
    </PageHeader>

    <OrganizationFilter :filters="filters" :products="products" :statuses="statuses" />

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3 font-medium">Názov</th>
                    <th class="px-5 py-3 font-medium">IČO</th>
                    <th class="px-5 py-3 font-medium">IČ DPH</th>
                    <th class="px-5 py-3 font-medium">Mesto</th>
                    <th class="px-5 py-3 font-medium">Stav</th>
                    <th class="px-5 py-3 text-right font-medium">Projektov</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="org in organizations.data" :key="org.id" class="transition hover:bg-brand-50/40">
                    <td class="px-5 py-3">
                        <Link :href="`/organizations/${org.id}`" class="font-medium text-slate-900 hover:text-brand-700 hover:underline">
                            {{ org.name }}
                        </Link>
                        <span v-if="org.verified" class="ml-2 text-xs text-emerald-600">overené</span>
                    </td>
                    <td class="px-5 py-3 text-slate-600">{{ org.ico ?? '—' }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ org.ic_dph ?? '—' }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ org.city ?? '—' }}</td>
                    <td class="px-5 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusStyles[org.status]">
                            {{ org.status }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right font-medium text-slate-700">{{ org.products_count }}</td>
                </tr>
                <tr v-if="organizations.data.length === 0">
                    <!-- Prázdny výpis pri zapnutom filtri vyzerá rovnako ako prázdna
                         evidencia — bez rozlíšenia človek hľadá chybu v dátach. -->
                    <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                        {{ Object.keys(filters).length ? 'Filtru nezodpovedá žiadna organizácia.' : 'Zatiaľ tu nie je žiadna organizácia.' }}
                    </td>
                </tr>
            </tbody>
        </table>

        <Pagination :meta="organizations" label="organizácií" />
    </div>
</template>
