<script setup>
import { Head, Link } from '@inertiajs/vue3';
import CardSection from '../Components/CardSection.vue';
import StatusBadge from '../Components/StatusBadge.vue';
import Icon from '../Components/Icon.vue';

defineProps({
    stats: { type: Object, required: true },
    products: { type: Array, default: () => [] },
    attention: { type: Array, default: () => [] },
    near_limit: { type: Array, default: () => [] },
});
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
