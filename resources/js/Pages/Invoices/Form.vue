<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardSection from '../../Components/CardSection.vue';
import InputError from '../../Components/InputError.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    organizations: { type: Array, default: () => [] },
    organization_id: { type: String, default: null },
    types: { type: Array, default: () => [] },
    payment_methods: { type: Array, default: () => [] },
    vat_rates: { type: Array, default: () => [] },
    next_numbers: { type: Object, default: () => ({}) },
});

const today = new Date().toISOString().slice(0, 10);
const inDays = (days) => new Date(Date.now() + days * 86400000).toISOString().slice(0, 10);

const form = useForm({
    organization_id: props.organization_id ?? '',
    type: 'invoice',
    payment_method: 'transfer',
    issued_at: today,
    delivered_at: today,
    due_at: inDays(14),
    variable_symbol: '',
    constant_symbol: '0308',
    specific_symbol: '',
    note: '',
    internal_note: '',
});

const search = ref('');

const filtered = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (!needle) return props.organizations.slice(0, 50);
    return props.organizations
        .filter((o) => o.name.toLowerCase().includes(needle) || (o.ico ?? '').includes(needle))
        .slice(0, 50);
});

const selected = computed(() => props.organizations.find((o) => o.id === form.organization_id));

const nextNumber = computed(() => props.next_numbers[form.type] ?? '—');

const submit = () => form.post('/invoices');
</script>

<template>
    <Head title="Nový doklad" />

    <PageHeader title="Nový doklad" subtitle="Vznikne koncept – číslo sa pridelí až pri vystavení.">
        <template #action>
            <Link href="/invoices" class="btn-secondary">Späť na zoznam</Link>
        </template>
    </PageHeader>

    <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="submit">
        <div class="space-y-6 lg:col-span-2">
            <CardSection icon="building" title="Odberateľ" description="Fakturačné údaje sa odfotia až pri vystavení.">
                <div v-if="!selected">
                    <label>Vyhľadať firmu</label>
                    <input v-model="search" type="text" placeholder="Názov alebo IČO…" />

                    <ul class="mt-3 max-h-72 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                        <li v-for="org in filtered" :key="org.id">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm transition hover:bg-slate-50"
                                @click="form.organization_id = org.id"
                            >
                                <span class="font-medium text-slate-900">{{ org.name }}</span>
                                <span class="text-xs text-slate-400">IČO {{ org.ico ?? '—' }}</span>
                            </button>
                        </li>
                        <li v-if="!filtered.length" class="px-4 py-6 text-center text-sm text-slate-400">
                            Nič sa nenašlo.
                        </li>
                    </ul>
                    <InputError :message="form.errors.organization_id" />
                </div>

                <div v-else class="flex items-center justify-between rounded-xl bg-brand-50 px-4 py-3 ring-1 ring-brand-600/15">
                    <div>
                        <p class="font-semibold text-slate-900">{{ selected.name }}</p>
                        <p class="text-xs text-slate-500">IČO {{ selected.ico ?? '—' }}</p>
                    </div>
                    <button type="button" class="text-sm text-slate-500 hover:text-slate-900" @click="form.organization_id = ''">
                        Zmeniť
                    </button>
                </div>
            </CardSection>

            <CardSection icon="card" title="Typ dokladu">
                <div class="grid gap-3 sm:grid-cols-3">
                    <label
                        v-for="type in types"
                        :key="type.value"
                        class="cursor-pointer rounded-xl border p-3.5 text-sm font-normal transition"
                        :class="form.type === type.value
                            ? 'border-brand-400 bg-brand-50 ring-2 ring-brand-500/20'
                            : 'border-slate-200 hover:border-slate-300'"
                    >
                        <input v-model="form.type" type="radio" :value="type.value" class="sr-only" />
                        <span class="block font-semibold text-slate-900">{{ type.label }}</span>
                    </label>
                </div>

                <p class="mt-3 flex items-center gap-1.5 text-sm text-slate-500">
                    <Icon name="invoice" :size="15" />
                    Ďalšie číslo v rade: <strong class="font-semibold text-slate-700">{{ nextNumber }}</strong>
                </p>
            </CardSection>
        </div>

        <div class="space-y-6">
            <CardSection icon="clock" title="Dátumy a platba">
                <div class="space-y-3">
                    <div>
                        <label>Dátum vystavenia</label>
                        <input v-model="form.issued_at" type="date" />
                    </div>
                    <div>
                        <label>Dátum dodania</label>
                        <input v-model="form.delivered_at" type="date" />
                    </div>
                    <div>
                        <label>Dátum splatnosti</label>
                        <input v-model="form.due_at" type="date" />
                        <InputError :message="form.errors.due_at" />
                    </div>
                    <div>
                        <label>Spôsob úhrady</label>
                        <select v-model="form.payment_method">
                            <option v-for="m in payment_methods" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label>Konštantný symbol</label>
                            <input v-model="form.constant_symbol" type="text" />
                        </div>
                        <div>
                            <label>Špecifický symbol</label>
                            <input v-model="form.specific_symbol" type="text" />
                        </div>
                    </div>
                    <div>
                        <label>Poznámka na faktúre</label>
                        <textarea v-model="form.note" rows="2"></textarea>
                    </div>
                </div>

                <template #footer>
                    <button type="submit" class="btn-primary w-full" :disabled="form.processing || !form.organization_id">
                        Vytvoriť koncept
                    </button>
                </template>
            </CardSection>

            <p class="px-1 text-xs text-slate-400">
                Variabilný symbol sa odvodí z čísla dokladu pri vystavení. Sadzbu DPH určí
                systém podľa krajiny a IČ DPH odberateľa – vrátane prenesenia daňovej povinnosti.
            </p>
        </div>
    </form>
</template>
