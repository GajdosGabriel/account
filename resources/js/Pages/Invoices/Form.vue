<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardSection from '../../Components/CardSection.vue';
import InputError from '../../Components/InputError.vue';
import Icon from '../../Components/Icon.vue';
import { isoDate } from '../../Composables/useInvoiceActions';

const props = defineProps({
    organizations: { type: Array, default: () => [] },
    organization_id: { type: String, default: null },
    types: { type: Array, default: () => [] },
    payment_methods: { type: Array, default: () => [] },
    vat_rates: { type: Array, default: () => [] },
    next_numbers: { type: Object, default: () => ({}) },
    defaults: { type: Object, default: () => ({}) },
});

const today = isoDate();

const addDays = (date, days) => {
    const [y, m, d] = date.split('-').map(Number);
    return isoDate(new Date(y, m - 1, d + days));
};

const form = useForm({
    organization_id: props.organization_id ?? '',
    type: 'invoice',
    payment_method: props.defaults.payment_method ?? 'transfer',
    issued_at: today,
    delivered_at: today,
    due_at: addDays(today, props.defaults.due_days ?? 14),
    variable_symbol: '',
    constant_symbol: props.defaults.constant_symbol ?? '',
    specific_symbol: '',
    note: props.defaults.note ?? '',
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

// Splatnosť sa počíta zo splatnosti firmy (inak z Nastavení), kým ju niekto neprepíše ručne.
const dueTouched = ref(false);

watch([selected, () => form.issued_at], () => {
    if (dueTouched.value || !form.issued_at) return;
    form.due_at = addDays(form.issued_at, selected.value?.payment_terms_days || props.defaults.due_days || 14);
}, { immediate: true });

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
                    <label for="org-search">Vyhľadať firmu</label>
                    <input id="org-search" v-model="search" type="search" placeholder="Názov alebo IČO…" />

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
                        <label for="issued_at">Dátum vystavenia</label>
                        <input id="issued_at" v-model="form.issued_at" type="date" />
                    </div>
                    <div>
                        <label for="delivered_at">Dátum dodania</label>
                        <input id="delivered_at" v-model="form.delivered_at" type="date" />
                    </div>
                    <div>
                        <label for="due_at">Dátum splatnosti</label>
                        <input id="due_at" v-model="form.due_at" type="date" @input="dueTouched = true" />
                        <InputError :message="form.errors.due_at" />
                    </div>
                    <div>
                        <label for="payment_method">Spôsob úhrady</label>
                        <select id="payment_method" v-model="form.payment_method">
                            <option v-for="m in payment_methods" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="constant_symbol">Konštantný symbol</label>
                            <input id="constant_symbol" v-model="form.constant_symbol" type="text" />
                        </div>
                        <div>
                            <label for="specific_symbol">Špecifický symbol</label>
                            <input id="specific_symbol" v-model="form.specific_symbol" type="text" />
                        </div>
                    </div>
                    <div>
                        <label for="note">Poznámka na faktúre</label>
                        <textarea id="note" v-model="form.note" rows="2"></textarea>
                    </div>
                </div>

                <template #footer>
                    <button type="submit" class="btn-primary w-full" :disabled="form.processing || !form.organization_id">
                        Vytvoriť koncept
                    </button>
                    <p v-if="!form.organization_id" class="mt-2 text-center text-xs text-slate-400">Najprv vyber odberateľa.</p>
                </template>
            </CardSection>

            <p class="px-1 text-xs text-slate-400">
                Variabilný symbol sa odvodí z čísla dokladu pri vystavení. Sadzbu DPH určí
                systém podľa krajiny a IČ DPH odberateľa – vrátane prenesenia daňovej povinnosti.
            </p>
        </div>
    </form>
</template>
