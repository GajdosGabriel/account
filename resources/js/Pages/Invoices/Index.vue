<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import DropdownMenu from '../../Components/DropdownMenu.vue';
import Pagination from '../../Components/Pagination.vue';
import Icon from '../../Components/Icon.vue';
import { invoiceMenu, money, shortDate } from '../../Composables/useInvoiceActions';

const props = defineProps({
    invoices: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    organizations: { type: Array, default: () => [] },
    trashed_count: { type: Number, default: 0 },
    can: { type: Object, default: () => ({}) },
});

const form = ref({ ...props.filters });
let timer = null;

// Hľadanie sa odosiela s oneskorením, filtre hneď.
watch(
    () => form.value.search,
    () => {
        clearTimeout(timer);
        timer = setTimeout(submit, 350);
    },
);

const submit = () => {
    const query = Object.fromEntries(Object.entries(form.value).filter(([, v]) => v !== '' && v != null));
    router.get('/invoices', query, { preserveState: true, preserveScroll: true, replace: true });
};

const reset = () => {
    form.value = { search: '', status: null, type: null, organization: null, from: null, to: null };
    submit();
};

const hasFilters = computed(() => Object.values(form.value).some((v) => v !== '' && v != null));

const cards = computed(() => [
    {
        label: 'Koncepty',
        value: props.stats.drafts ?? 0,
        hint: 'čakajú na vystavenie',
        icon: 'pencil',
        tone: 'bg-slate-100 text-slate-600',
        filter: 'draft',
    },
    {
        label: 'Neuhradené',
        value: money(props.stats.unpaid_cents),
        hint: `${props.stats.unpaid_count ?? 0} dokladov`,
        icon: 'clock',
        tone: 'bg-sky-50 text-sky-600',
        filter: null,
    },
    {
        label: 'Po splatnosti',
        value: money(props.stats.overdue_cents),
        hint: `${props.stats.overdue_count ?? 0} dokladov`,
        icon: 'warning',
        tone: 'bg-rose-50 text-rose-600',
        filter: 'overdue',
        alert: (props.stats.overdue_count ?? 0) > 0,
    },
    {
        label: 'Uhradené tento mesiac',
        value: money(props.stats.paid_month_cents),
        hint: 'prijaté platby',
        icon: 'check',
        tone: 'bg-emerald-50 text-emerald-600',
        filter: 'paid',
    },
]);

const applyCard = (card) => {
    form.value.status = form.value.status === card.filter ? null : card.filter;
    submit();
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (form.value.from) params.set('from', form.value.from);
    if (form.value.to) params.set('to', form.value.to);
    return (format) => `/invoices/export?format=${format}&${params.toString()}`;
});
</script>

<template>
    <Head title="Faktúry" />

    <PageHeader title="Faktúry" subtitle="Doklady, pohľadávky a exporty pre účtovníka">
        <template #action>
            <DropdownMenu
                v-if="can.export"
                label="Export"
                align="right"
                :items="[
                    { label: 'CSV pre Excel', icon: 'download', onSelect: () => (window.location.href = exportUrl('csv')) },
                    { label: 'XML pre účtovníctvo', icon: 'download', onSelect: () => (window.location.href = exportUrl('xml')) },
                ]"
            />
            <Link v-if="can.create" href="/invoices/create" class="btn-primary">
                <Icon name="plus" :size="16" />
                Nový doklad
            </Link>
        </template>
    </PageHeader>

    <!-- Prehľad pohľadávok -->
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <button
            v-for="card in cards"
            :key="card.label"
            type="button"
            class="card flex items-center gap-3.5 p-4 text-left transition hover:border-slate-300 hover:shadow-md"
            :class="[
                form.status === card.filter && card.filter ? 'ring-2 ring-brand-500/30' : '',
                card.alert ? 'border-rose-200' : '',
            ]"
            @click="card.filter && applyCard(card)"
        >
            <span class="chip h-10 w-10 shrink-0" :class="card.tone">
                <Icon :name="card.icon" :size="19" />
            </span>
            <div class="min-w-0">
                <p class="truncate text-lg font-semibold tracking-tight text-slate-900">{{ card.value }}</p>
                <p class="truncate text-xs text-slate-500">{{ card.label }} · {{ card.hint }}</p>
            </div>
        </button>
    </div>

    <!-- Filtre -->
    <div class="card mb-5 p-4">
        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <label class="sr-only">Hľadať</label>
                <input v-model="form.search" type="text" placeholder="Číslo, VS, firma alebo IČO…" />
            </div>
            <select v-model="form.status" @change="submit">
                <option :value="null">Všetky stavy</option>
                <option value="overdue">Po splatnosti</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                <option v-if="trashed_count" value="trashed">Kôš ({{ trashed_count }})</option>
            </select>
            <select v-model="form.type" @change="submit">
                <option :value="null">Všetky typy</option>
                <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
            <input v-model="form.from" type="date" title="Vystavené od" @change="submit" />
            <input v-model="form.to" type="date" title="Vystavené do" @change="submit" />
        </div>

        <div v-if="hasFilters" class="mt-3 flex items-center gap-3 border-t border-slate-100 pt-3">
            <button type="button" class="text-sm text-slate-500 hover:text-slate-900" @click="reset">
                Zrušiť filtre
            </button>
            <span class="text-sm text-slate-400">Nájdených: {{ invoices.total }}</span>
        </div>
    </div>

    <!-- Zoznam -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/70 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3 font-semibold">Doklad</th>
                        <th class="px-4 py-3 font-semibold">Odberateľ</th>
                        <th class="px-4 py-3 font-semibold">Vystavené</th>
                        <th class="px-4 py-3 font-semibold">Splatnosť</th>
                        <th class="px-4 py-3 text-right font-semibold">Suma</th>
                        <th class="px-4 py-3 font-semibold">Stav</th>
                        <th class="w-12 px-2 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="invoice in invoices.data"
                        :key="invoice.id"
                        class="transition hover:bg-slate-50/70"
                    >
                        <td class="px-4 py-3">
                            <Link :href="`/invoices/${invoice.id}`" class="font-semibold text-slate-900 hover:text-brand-700">
                                {{ invoice.number ?? 'Koncept' }}
                            </Link>
                            <div class="text-xs text-slate-400">
                                {{ invoice.type_label }}
                                <span v-if="invoice.variable_symbol"> · VS {{ invoice.variable_symbol }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <Link
                                :href="`/organizations/${invoice.organization.id}`"
                                class="text-slate-700 hover:text-brand-700"
                            >
                                {{ invoice.organization.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ shortDate(invoice.issued_at) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span :class="invoice.is_overdue ? 'font-medium text-rose-600' : 'text-slate-600'">
                                {{ shortDate(invoice.due_at) }}
                            </span>
                            <div v-if="invoice.is_overdue" class="text-xs text-rose-500">
                                {{ invoice.days_overdue }} dní po splatnosti
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <div class="font-semibold text-slate-900">{{ invoice.total }}</div>
                            <div v-if="invoice.paid_cents > 0 && invoice.outstanding_cents > 0" class="text-xs text-amber-600">
                                zostáva {{ invoice.outstanding }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge
                                :status="invoice.status"
                                :tone="invoice.is_overdue && invoice.status !== 'overdue' ? 'rose' : invoice.status_tone"
                                :label="invoice.status_label"
                            />
                        </td>
                        <td class="px-2 py-3 text-right">
                            <DropdownMenu :abilities="invoice.can" :items="invoiceMenu(invoice)" />
                        </td>
                    </tr>

                    <tr v-if="!invoices.data.length">
                        <td colspan="7" class="px-4 py-14 text-center">
                            <Icon name="invoice" :size="32" class="mx-auto mb-3 text-slate-300" />
                            <p class="font-medium text-slate-600">Žiadne doklady</p>
                            <p class="mt-1 text-sm text-slate-400">
                                {{ hasFilters ? 'Skús uvoľniť filtre.' : 'Vystav prvú faktúru alebo spusti automatickú fakturáciu.' }}
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="invoices" label="dokladov" />
    </div>
</template>
