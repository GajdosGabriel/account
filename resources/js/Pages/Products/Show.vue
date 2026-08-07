<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardSection from '../../Components/CardSection.vue';
import InputError from '../../Components/InputError.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    product: { type: Object, required: true },
    features: { type: Array, default: () => [] },
    plans: { type: Array, default: () => [] },
});

const base = `/products/${props.product.key}`;

/* ---------- nastavenia projektu ---------- */

const settingsOpen = ref(false);

const productForm = useForm({
    key: props.product.key,
    name: props.product.name,
    url: props.product.url ?? '',
    description: props.product.description ?? '',
    is_active: props.product.is_active,
});

const keyChanged = computed(() => productForm.key !== props.product.key);

const saveProduct = () => productForm.patch(base, {
    preserveScroll: true,
    onSuccess: () => { settingsOpen.value = false; },
});

const deleteProduct = () => {
    if (confirm(`Naozaj odstrániť projekt „${props.product.name}“? Zmažú sa aj plány, katalóg, tokeny a webhooky.`)) {
        router.delete(base);
    }
};

/* ---------- katalóg funkcií ---------- */

const featureOpen = ref(false);
const editingFeature = ref(null);

const featureForm = useForm({
    key: '', name: '', type: 'limit', unit: '', metric: '',
    default_value: '', description: '', sort_order: 0,
});

const openFeature = (feature = null) => {
    editingFeature.value = feature;
    featureOpen.value = true;

    featureForm.defaults({
        key: feature?.key ?? '',
        name: feature?.name ?? '',
        type: feature?.type ?? 'limit',
        unit: feature?.unit ?? '',
        metric: feature?.metric ?? '',
        default_value: feature?.default_value ?? '',
        description: feature?.description ?? '',
        sort_order: feature?.sort_order ?? 0,
    });
    featureForm.reset();
};

const saveFeature = () => {
    const options = { preserveScroll: true, onSuccess: () => { featureOpen.value = false; editingFeature.value = null; } };

    editingFeature.value
        ? featureForm.patch(`${base}/features/${editingFeature.value.id}`, options)
        : featureForm.post(`${base}/features`, options);
};

const deleteFeature = (feature) => {
    if (confirm(`Odstrániť "${feature.name}" z katalógu? Plány prídu o túto hodnotu.`)) {
        router.delete(`${base}/features/${feature.id}`, { preserveScroll: true });
    }
};

/* ---------- plány ---------- */

const planOpen = ref(false);
const editingPlan = ref(null);
const planFeatures = reactive({});

const planForm = useForm({
    key: '', name: '', price_cents: 0, interval: 'month',
    trial_days: 14, is_active: true, sort_order: 0, features: {},
});

const openPlan = (plan = null) => {
    editingPlan.value = plan;
    planOpen.value = true;

    Object.keys(planFeatures).forEach((k) => delete planFeatures[k]);

    props.features.forEach((feature) => {
        const value = plan ? plan.features[feature.key] : feature.default_value;
        planFeatures[feature.key] = feature.type === 'flag'
            ? Boolean(value)
            : (value === null || value === undefined ? '' : value);
    });

    planForm.defaults({
        key: plan?.key ?? '',
        name: plan?.name ?? '',
        price_cents: plan?.price_cents ?? 0,
        interval: plan?.interval ?? 'month',
        trial_days: plan?.trial_days ?? 14,
        is_active: plan?.is_active ?? true,
        sort_order: plan?.sort_order ?? 0,
        features: {},
    });
    planForm.reset();
};

const savePlan = () => {
    planForm.features = { ...planFeatures };

    const options = { preserveScroll: true, onSuccess: () => { planOpen.value = false; editingPlan.value = null; } };

    editingPlan.value
        ? planForm.patch(`${base}/plans/${editingPlan.value.id}`, options)
        : planForm.post(`${base}/plans`, options);
};

const deletePlan = (plan) => {
    if (confirm(`Odstrániť plán "${plan.name}"?`)) {
        router.delete(`${base}/plans/${plan.id}`, { preserveScroll: true });
    }
};

const formatFeatureValue = (feature, value) => {
    if (feature.type === 'flag') return value ? 'áno' : 'nie';
    if (value === null || value === undefined || value === '') return 'neobmedzene';
    return `${value}${feature.unit ? ' ' + feature.unit : ''}`;
};
</script>

<template>
    <Head :title="product.name" />

    <PageHeader :title="product.name" :subtitle="product.url || product.key">
        <template #action>
            <Link :href="`/organizations?product=${product.key}`" class="btn-secondary">
                <Icon name="building" :size="16" />
                Firmy projektu
            </Link>
            <button type="button" class="btn-secondary" @click="settingsOpen = !settingsOpen">
                <Icon name="settings" :size="16" />
                {{ settingsOpen ? 'Zavrieť' : 'Nastavenia' }}
            </button>
        </template>
    </PageHeader>

    <div class="space-y-6">
        <!-- Nastavenia projektu -->
        <CardSection
            v-if="settingsOpen"
            icon="code"
            title="Nastavenia projektu"
            description="Názov, adresa a kľúč. Kľúč sa používa v URL aj v odpovedi entitlements."
        >
            <form class="grid gap-4 sm:grid-cols-6" @submit.prevent="saveProduct">
                <div class="sm:col-span-4">
                    <label for="p_name">Názov</label>
                    <input id="p_name" v-model="productForm.name" type="text" required />
                    <InputError :message="productForm.errors.name" />
                </div>

                <div class="sm:col-span-2">
                    <label for="p_key">Kľúč</label>
                    <input id="p_key" v-model="productForm.key" type="text" required />
                    <InputError :message="productForm.errors.key" />
                </div>

                <div
                    v-if="keyChanged"
                    class="sm:col-span-6 flex items-start gap-2.5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-600/15"
                >
                    <Icon name="warning" :size="18" class="mt-0.5 text-amber-600" />
                    <span>
                        Kľúč sa objavuje v odpovedi <span class="font-mono">entitlements.product</span>.
                        Ak si podľa neho projekt niečo rozhoduje alebo cachuje, uprav to aj tam.
                        Service tokeny a webhooky zmena neovplyvní.
                    </span>
                </div>

                <div class="sm:col-span-6">
                    <label for="p_url">Adresa projektu</label>
                    <input id="p_url" v-model="productForm.url" type="url" placeholder="https://projekt1.sk" />
                    <p class="mt-1.5 text-xs text-slate-500">
                        Podľa domény sa kontrolujú návratové adresy a odkazy v e-mailoch.
                    </p>
                    <InputError :message="productForm.errors.url" />
                </div>

                <div class="sm:col-span-6">
                    <label for="p_description">Popis</label>
                    <textarea id="p_description" v-model="productForm.description" rows="2"></textarea>
                    <InputError :message="productForm.errors.description" />
                </div>

                <div class="sm:col-span-6 flex flex-wrap items-center gap-4 border-t border-slate-100 pt-4">
                    <button type="submit" class="btn-primary" :disabled="productForm.processing">
                        {{ productForm.processing ? 'Ukladám…' : 'Uložiť zmeny' }}
                    </button>

                    <label class="flex items-center gap-2 text-sm font-normal text-slate-600">
                        <input v-model="productForm.is_active" type="checkbox" />
                        aktívny — pri vypnutí prestane API prijímať volania
                    </label>

                    <button type="button" class="ml-auto text-sm text-rose-600 hover:underline" @click="deleteProduct">
                        Odstrániť projekt
                    </button>
                </div>
            </form>
        </CardSection>

        <!-- Katalóg funkcií -->
        <CardSection
            icon="shield"
            title="Katalóg funkcií"
            description="Určuje, aké kľúče môžu plány vôbec obsahovať. Bez neho by preklep prešiel ticho."
        >
            <template #action>
                <button type="button" class="btn-secondary btn-sm" @click="openFeature()">
                    <Icon name="plus" :size="15" />
                    Pridať
                </button>
            </template>

            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="pb-2 font-medium">Kľúč</th>
                        <th class="pb-2 font-medium">Názov</th>
                        <th class="pb-2 font-medium">Typ</th>
                        <th class="pb-2 font-medium">Metrika spotreby</th>
                        <th class="pb-2 font-medium">Predvolené</th>
                        <th class="pb-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="feature in features" :key="feature.id">
                        <td class="py-2.5 font-mono text-xs text-slate-700">{{ feature.key }}</td>
                        <td class="py-2.5 font-medium text-slate-900">{{ feature.name }}</td>
                        <td class="py-2.5">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                :class="feature.type === 'flag'
                                    ? 'bg-violet-50 text-violet-700 ring-violet-600/20'
                                    : 'bg-sky-50 text-sky-700 ring-sky-600/20'"
                            >
                                {{ feature.type === 'flag' ? 'prepínač' : 'limit' }}
                            </span>
                        </td>
                        <td class="py-2.5 font-mono text-xs text-slate-500">{{ feature.metric ?? '—' }}</td>
                        <td class="py-2.5 text-slate-600">{{ formatFeatureValue(feature, feature.default_value) }}</td>
                        <td class="py-2.5 text-right">
                            <button type="button" class="text-sm text-brand-700 hover:underline" @click="openFeature(feature)">Upraviť</button>
                            <button type="button" class="ml-3 text-sm text-rose-600 hover:underline" @click="deleteFeature(feature)">Zmazať</button>
                        </td>
                    </tr>
                    <tr v-if="features.length === 0">
                        <td colspan="6" class="py-8 text-center text-slate-500">
                            Katalóg je prázdny. Začni napríklad funkciou <span class="font-mono">max_records</span>.
                        </td>
                    </tr>
                </tbody>
            </table>

            <form v-if="featureOpen" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2" @submit.prevent="saveFeature">
                <div>
                    <label for="f_key">Kľúč</label>
                    <input id="f_key" v-model="featureForm.key" type="text" placeholder="max_records" required />
                    <InputError :message="featureForm.errors.key" />
                </div>
                <div>
                    <label for="f_name">Názov</label>
                    <input id="f_name" v-model="featureForm.name" type="text" placeholder="Počet záznamov" required />
                    <InputError :message="featureForm.errors.name" />
                </div>
                <div>
                    <label for="f_type">Typ</label>
                    <select id="f_type" v-model="featureForm.type">
                        <option value="limit">Limit — číslo, prázdne = neobmedzene</option>
                        <option value="flag">Prepínač — áno/nie</option>
                    </select>
                </div>
                <div v-if="featureForm.type === 'limit'">
                    <label for="f_unit">Jednotka</label>
                    <input id="f_unit" v-model="featureForm.unit" type="text" placeholder="záznamov" />
                </div>
                <div v-if="featureForm.type === 'limit'">
                    <label for="f_metric">Metrika spotreby</label>
                    <input id="f_metric" v-model="featureForm.metric" type="text" placeholder="records" />
                    <p class="mt-1.5 text-xs text-slate-500">Kľúč, ktorý projekt posiela do <span class="font-mono">/usage</span>.</p>
                    <InputError :message="featureForm.errors.metric" />
                </div>
                <div>
                    <label for="f_default">Predvolená hodnota</label>
                    <select v-if="featureForm.type === 'flag'" id="f_default" v-model="featureForm.default_value">
                        <option :value="false">nie</option>
                        <option :value="true">áno</option>
                    </select>
                    <input v-else id="f_default" v-model="featureForm.default_value" type="number" min="0" placeholder="prázdne = neobmedzene" />
                </div>
                <div class="sm:col-span-2 flex gap-2">
                    <button type="submit" class="btn-primary" :disabled="featureForm.processing">
                        {{ editingFeature ? 'Uložiť zmeny' : 'Pridať do katalógu' }}
                    </button>
                    <button type="button" class="btn-secondary" @click="featureOpen = false; editingFeature = null">Zrušiť</button>
                </div>
            </form>
        </CardSection>

        <!-- Cenník -->
        <CardSection icon="card" tone="emerald" title="Cenník" description="Hodnoty sa berú z katalógu, takže sa nedá vymyslieť neexistujúci kľúč.">
            <template #action>
                <button type="button" class="btn-secondary btn-sm" :disabled="features.length === 0" @click="openPlan()">
                    <Icon name="plus" :size="15" />
                    Nový plán
                </button>
            </template>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="plan in plans"
                    :key="plan.id"
                    class="rounded-xl border border-slate-200 p-4"
                    :class="{ 'opacity-50': !plan.is_active }"
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <h3 class="font-semibold text-slate-900">{{ plan.name }}</h3>
                        <span class="text-sm font-semibold text-slate-900">{{ plan.price }}</span>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500">
                        za {{ plan.interval === 'year' ? 'rok' : 'mesiac' }}
                        <span v-if="plan.trial_days"> · {{ plan.trial_days }} dní zdarma</span>
                    </p>

                    <ul class="mt-3 space-y-1 text-sm">
                        <li v-for="feature in features" :key="feature.key" class="flex justify-between gap-2">
                            <span class="truncate text-slate-600">{{ feature.name }}</span>
                            <span class="shrink-0 font-medium text-slate-900">
                                {{ formatFeatureValue(feature, plan.features[feature.key]) }}
                            </span>
                        </li>
                    </ul>

                    <div class="mt-4 flex gap-3 border-t border-slate-100 pt-3">
                        <button type="button" class="text-sm text-brand-700 hover:underline" @click="openPlan(plan)">Upraviť</button>
                        <button type="button" class="text-sm text-rose-600 hover:underline" @click="deletePlan(plan)">Zmazať</button>
                    </div>
                </article>

                <p v-if="plans.length === 0" class="text-sm text-slate-500">
                    Zatiaľ žiadne plány.
                    <span v-if="features.length === 0">Najprv doplň aspoň jednu funkciu do katalógu.</span>
                </p>
            </div>

            <form v-if="planOpen" class="mt-6 border-t border-slate-100 pt-5" @submit.prevent="savePlan">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="p_key">Kľúč</label>
                        <input id="p_key" v-model="planForm.key" type="text" placeholder="standard" required />
                        <InputError :message="planForm.errors.key" />
                    </div>
                    <div>
                        <label for="p_name">Názov</label>
                        <input id="p_name" v-model="planForm.name" type="text" placeholder="Standard" required />
                        <InputError :message="planForm.errors.name" />
                    </div>
                    <div>
                        <label for="p_price">Cena v centoch</label>
                        <input id="p_price" v-model.number="planForm.price_cents" type="number" min="0" required />
                        <p class="mt-1.5 text-xs text-slate-500">2900 = 29,00 €</p>
                    </div>
                    <div>
                        <label for="p_interval">Obdobie</label>
                        <select id="p_interval" v-model="planForm.interval">
                            <option value="month">mesačne</option>
                            <option value="year">ročne</option>
                        </select>
                    </div>
                    <div>
                        <label for="p_trial">Skúšobné dni</label>
                        <input id="p_trial" v-model.number="planForm.trial_days" type="number" min="0" max="365" />
                    </div>
                    <div>
                        <label for="p_order">Poradie</label>
                        <input id="p_order" v-model.number="planForm.sort_order" type="number" min="0" />
                    </div>
                </div>

                <div class="mt-5 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Hodnoty funkcií</p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="feature in features" :key="feature.key">
                            <label :for="`pf_${feature.key}`">{{ feature.name }}</label>
                            <label v-if="feature.type === 'flag'" class="flex items-center gap-2 text-sm font-normal text-slate-600">
                                <input :id="`pf_${feature.key}`" v-model="planFeatures[feature.key]" type="checkbox" />
                                povolené
                            </label>
                            <input
                                v-else
                                :id="`pf_${feature.key}`"
                                v-model="planFeatures[feature.key]"
                                type="number"
                                min="0"
                                placeholder="prázdne = neobmedzene"
                            />
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex items-center gap-3">
                    <button type="submit" class="btn-primary" :disabled="planForm.processing">
                        {{ editingPlan ? 'Uložiť plán' : 'Vytvoriť plán' }}
                    </button>
                    <button type="button" class="btn-secondary" @click="planOpen = false; editingPlan = null">Zrušiť</button>
                    <label class="ml-auto flex items-center gap-2 text-sm font-normal text-slate-600">
                        <input v-model="planForm.is_active" type="checkbox" />
                        aktívny
                    </label>
                </div>
            </form>
        </CardSection>
    </div>
</template>
