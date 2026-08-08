<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import CardSection from '../Components/CardSection.vue';
import StatusBadge from '../Components/StatusBadge.vue';
import Icon from '../Components/Icon.vue';

const props = defineProps({
    stats: { type: Object, required: true },
    products: { type: Array, default: () => [] },
    attention: { type: Array, default: () => [] },
    near_limit: { type: Array, default: () => [] },
    invoicing: { type: Object, default: () => ({}) },
    forecast: { type: Object, default: () => ({}) },
    months: { type: Array, default: () => [] },
});

/**
 * Stĺpce vývoja sa škálujú na najvyššiu hodnotu v okne, nie na absolútnu
 * sumu – inak by pri jednom silnom mesiaci boli ostatné neviditeľné.
 */
const peak = computed(
    () => Math.max(1, ...props.months.flatMap((m) => [m.invoiced_cents, m.paid_cents])),
);

const height = (cents) => `${Math.max((Math.max(cents, 0) / peak.value) * 100, 1.5)}%`;

const money = (cents) => `${new Intl.NumberFormat('sk-SK', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
}).format((cents ?? 0) / 100)} €`;

const hasHistory = computed(() => props.months.some((m) => m.invoiced_cents || m.paid_cents));
</script>

<template>
    <Head title="Prehľad" />

    <div class="space-y-7">
        <!-- Súhrn -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-600 via-brand-600 to-violet-700 p-6 text-white shadow-lg shadow-brand-600/20 sm:p-8">
            <div class="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>

            <div class="relative">
                <p class="text-xs font-medium uppercase tracking-wider text-white/60">Mesačný opakovaný príjem</p>
                <p class="mt-1 text-4xl font-semibold tracking-tight">{{ stats.mrr }}</p>
            </div>

            <div class="relative mt-7 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-white/10 px-4 py-3 backdrop-blur">
                    <p class="text-2xl font-semibold">{{ stats.organizations }}</p>
                    <p class="text-xs text-white/60">organizácií</p>
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 backdrop-blur">
                    <p class="text-2xl font-semibold">{{ stats.active }}</p>
                    <p class="text-xs text-white/60">aktívnych predplatných</p>
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 backdrop-blur">
                    <p class="text-2xl font-semibold">{{ stats.trialing }}</p>
                    <p class="text-xs text-white/60">v skúšobnom období</p>
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 backdrop-blur">
                    <p class="text-2xl font-semibold">{{ stats.past_due + stats.suspended }}</p>
                    <p class="text-xs text-white/60">s problémom platby</p>
                </div>
            </div>
        </div>

        <!-- Fakturácia -->
        <div>
            <div class="mb-4 flex items-baseline justify-between gap-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Fakturácia</h2>
                <Link href="/invoices" class="text-sm text-brand-700 hover:underline">Všetky doklady →</Link>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="card p-4">
                    <p class="text-xs text-slate-500">Vyfakturované tento mesiac</p>
                    <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                        {{ invoicing.invoiced_month?.formatted }}
                    </p>
                    <p class="mt-0.5 text-xs text-slate-400">{{ invoicing.invoiced_month?.count }} dokladov</p>
                </div>

                <div class="card p-4">
                    <p class="text-xs text-slate-500">Uhradené tento mesiac</p>
                    <p class="mt-1 text-2xl font-semibold tracking-tight text-emerald-600">
                        {{ invoicing.paid_month?.formatted }}
                    </p>
                    <p class="mt-0.5 text-xs text-slate-400">{{ invoicing.paid_month?.count }} dokladov</p>
                </div>

                <Link href="/invoices" class="card p-4 transition hover:border-slate-300 hover:shadow-md">
                    <p class="text-xs text-slate-500">Neuhradené spolu</p>
                    <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                        {{ invoicing.outstanding?.formatted }}
                    </p>
                    <p class="mt-0.5 text-xs text-slate-400">{{ invoicing.outstanding?.count }} dokladov</p>
                </Link>

                <Link
                    href="/invoices?status=overdue"
                    class="card p-4 transition hover:shadow-md"
                    :class="invoicing.overdue?.count ? 'border-rose-200 hover:border-rose-300' : 'hover:border-slate-300'"
                >
                    <p class="text-xs text-slate-500">Po splatnosti</p>
                    <p
                        class="mt-1 text-2xl font-semibold tracking-tight"
                        :class="invoicing.overdue?.count ? 'text-rose-600' : 'text-slate-900'"
                    >
                        {{ invoicing.overdue?.formatted }}
                    </p>
                    <p class="mt-0.5 text-xs text-slate-400">{{ invoicing.overdue?.count }} dokladov</p>
                </Link>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-5">
            <!-- Prognóza -->
            <CardSection
                class="lg:col-span-2"
                icon="clock"
                tone="emerald"
                title="Prognóza príjmu"
                :description="`Čo má pritiecť do ${forecast.days} dní, teda do ${forecast.until}.`"
            >
                <p class="text-3xl font-semibold tracking-tight text-slate-900">{{ forecast.total }}</p>
                <p class="mt-1 text-xs text-slate-500">
                    Nič sa neodhaduje – sú to splatné pohľadávky a obnovy, ktoré už v evidencii sú.
                </p>

                <dl class="mt-5 space-y-3 border-t border-slate-100 pt-4 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-slate-600">
                            Splatné faktúry
                            <span class="text-xs text-slate-400">· {{ forecast.due?.count }}</span>
                        </dt>
                        <dd class="font-semibold text-slate-900">{{ forecast.due?.formatted }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-slate-600">
                            Obnovy predplatných
                            <span class="text-xs text-slate-400">· {{ forecast.renewals?.count }}</span>
                        </dt>
                        <dd class="font-semibold text-slate-900">{{ forecast.renewals?.formatted }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 border-t border-slate-100 pt-3">
                        <dt class="text-slate-600">
                            Ohrozené po splatnosti
                            <span class="text-xs text-slate-400">· {{ forecast.at_risk?.count }}</span>
                        </dt>
                        <dd class="font-semibold" :class="forecast.at_risk?.count ? 'text-rose-600' : 'text-slate-400'">
                            {{ forecast.at_risk?.formatted }}
                        </dd>
                    </div>
                </dl>

                <template #footer>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-xs text-slate-500">
                        <span v-if="invoicing.avg_days_to_pay !== null">
                            Priemerná doba úhrady:
                            <strong class="text-slate-700">{{ invoicing.avg_days_to_pay }} dní</strong>
                        </span>
                        <span v-if="invoicing.drafts">
                            Konceptov čaká na vystavenie:
                            <strong class="text-slate-700">{{ invoicing.drafts }}</strong>
                        </span>
                    </div>
                </template>
            </CardSection>

            <!-- Vývoj -->
            <CardSection
                class="lg:col-span-3"
                icon="invoice"
                title="Vývoj fakturácie"
                description="Posledných šesť mesiacov: vystavené a z toho uhradené."
            >
                <div v-if="hasHistory">
                    <div class="flex h-44 items-end gap-3">
                        <div v-for="month in months" :key="month.key" class="flex h-full flex-1 flex-col justify-end">
                            <div class="flex h-full items-end justify-center gap-1">
                                <div
                                    class="w-1/2 rounded-t-md bg-brand-500/80 transition-all"
                                    :style="{ height: height(month.invoiced_cents) }"
                                    :title="`Vystavené: ${money(month.invoiced_cents)}`"
                                ></div>
                                <div
                                    class="w-1/2 rounded-t-md bg-emerald-500/80 transition-all"
                                    :style="{ height: height(month.paid_cents) }"
                                    :title="`Uhradené: ${money(month.paid_cents)}`"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 flex gap-3 border-t border-slate-100 pt-2">
                        <div v-for="month in months" :key="month.key" class="flex-1 text-center">
                            <p class="text-xs font-medium text-slate-600">{{ month.label }}</p>
                            <p class="text-xs text-slate-400">{{ money(month.invoiced_cents) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center gap-4 text-xs text-slate-500">
                        <span class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-sm bg-brand-500/80"></span> vystavené
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-sm bg-emerald-500/80"></span> uhradené
                        </span>
                    </div>
                </div>

                <p v-else class="py-12 text-center text-sm text-slate-500">
                    Zatiaľ nie je čo kresliť – vystav prvý doklad.
                </p>
            </CardSection>
        </div>

        <!-- Projekty -->
        <div>
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Pripojené projekty</h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="product in products"
                    :key="product.key"
                    :href="`/products/${product.key}`"
                    class="card group p-5 transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate font-semibold text-slate-900">{{ product.name }}</h3>
                            <p class="mt-0.5 font-mono text-xs text-slate-400">{{ product.key }}</p>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                            :class="product.is_active
                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                                : 'bg-slate-100 text-slate-500 ring-slate-500/20'"
                        >
                            {{ product.is_active ? 'aktívny' : 'vypnutý' }}
                        </span>
                    </div>

                    <p class="mt-4 text-sm text-slate-500">
                        <span class="text-lg font-semibold text-slate-900">{{ product.organizations_count }}</span>
                        organizácií
                    </p>
                </Link>

                <Link
                    v-if="products.length === 0"
                    href="/products"
                    class="card flex items-center justify-center border-dashed p-8 text-sm text-slate-500 hover:text-slate-900"
                >
                    <Icon name="plus" :size="18" class="mr-2" />
                    Pridať prvý projekt
                </Link>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Problémy s platbou -->
            <CardSection icon="warning" tone="amber" title="Vyžaduje pozornosť" description="Predplatné po splatnosti alebo pozastavené.">
                <ul class="divide-y divide-slate-100">
                    <li v-for="(row, i) in attention" :key="i" class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <Link :href="`/organizations/${row.organization_id}`" class="truncate font-medium text-slate-900 hover:underline">
                                {{ row.organization }}
                            </Link>
                            <p class="text-xs text-slate-500">
                                {{ row.product }}<span v-if="row.deadline"> · do {{ row.deadline }}</span>
                            </p>
                        </div>
                        <StatusBadge :status="row.status" :label="row.status_label" />
                    </li>
                    <li v-if="attention.length === 0" class="py-8 text-center text-sm text-slate-500">
                        Všetko je uhradené.
                    </li>
                </ul>
            </CardSection>

            <!-- Blízko limitu -->
            <CardSection icon="shield" tone="emerald" title="Blízko limitu" description="Kandidáti na vyšší plán.">
                <ul class="divide-y divide-slate-100">
                    <li v-for="(row, i) in near_limit" :key="i" class="py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <Link :href="`/organizations/${row.organization_id}`" class="truncate font-medium text-slate-900 hover:underline">
                                    {{ row.organization }}
                                </Link>
                                <p class="text-xs text-slate-500">{{ row.product }} · {{ row.feature }}</p>
                            </div>
                            <span
                                class="shrink-0 text-sm font-semibold"
                                :class="row.ratio >= 1 ? 'text-rose-600' : 'text-amber-600'"
                            >
                                {{ row.used }} / {{ row.limit }}
                            </span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="row.ratio >= 1 ? 'bg-rose-500' : 'bg-amber-400'"
                                :style="{ width: Math.min(row.ratio * 100, 100) + '%' }"
                            ></div>
                        </div>
                    </li>
                    <li v-if="near_limit.length === 0" class="py-8 text-center text-sm text-slate-500">
                        Nikto sa zatiaľ nepribližuje k limitu.
                    </li>
                </ul>
            </CardSection>
        </div>
    </div>
</template>
