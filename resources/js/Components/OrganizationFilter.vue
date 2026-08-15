<script setup>
import { computed, reactive, watch, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { t } from '../Composables/useLang';

/**
 * Filter zoznamu organizácií.
 *
 * Tvar filtrov drží OrganizationQuery na backende — tento komponent len
 * skladá query string. Preto sa tu nič nefiltruje ani nedopočítava: keby
 * sa pravidlá zdvojili, výpis v administrácii by sa časom rozišiel s tým,
 * čo cez API vidí projekt.
 */
const props = defineProps({
    /** Aktuálne hodnoty z backendu — už znormalizované, prázdne kľúče chýbajú. */
    filters: { type: Object, default: () => ({}) },
    /** [{ key, name }] projektov do výberu. */
    products: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => ['active', 'suspended', 'archived'] },
    /** Počet zmazaných – kôš sa v stavoch ponúka, len keď v ňom niečo je. */
    trashedCount: { type: Number, default: 0 },
    /** Kam sa filtre posielajú. */
    route: { type: String, default: '/organizations' },
});

const statusLabel = (status) => {
    const key = `organizations.filter.statuses.${status}`;
    const line = t(key);

    return line === key ? status : line;
};

const form = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    product: props.filters.product ?? '',
    linked: props.filters.linked ?? '',
});

// Filter podľa projektu a „bez projektu" si protirečia — backend to rieši
// tiež, tu ide o to, aby sa výber nedal nastaviť do stavu, ktorý sa vzápätí
// sám prepíše.
watch(() => form.product, (value) => {
    if (value) form.linked = '';
});

watch(() => form.linked, (value) => {
    if (value) form.product = '';
});

const active = computed(() => Object.values(form).some((value) => value !== ''));

let timer = null;

// Písanie do hľadania sa zdržiava, výber zo selectu nie — čakať 300 ms
// po kliknutí do rozbaľovacieho zoznamu pôsobí ako zamrznutá stránka.
watch(() => form.q, () => submit(300));
watch([() => form.status, () => form.product, () => form.linked], () => submit(0));

function submit(delay) {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            props.route,
            Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '')),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, delay);
}

function reset() {
    Object.keys(form).forEach((key) => { form[key] = ''; });
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <input
            v-model="form.q"
            type="search"
            :placeholder="t('organizations.filter.search_placeholder')"
            class="max-w-xs!"
            :aria-label="t('organizations.filter.search')"
        />

        <select v-model="form.product" class="w-auto!" :aria-label="t('organizations.filter.product')">
            <option value="">{{ t('organizations.filter.all_products') }}</option>
            <option v-for="product in products" :key="product.key" :value="product.key">
                {{ product.name }}
            </option>
        </select>

        <select v-model="form.status" class="w-auto!" :aria-label="t('organizations.filter.status')">
            <option value="">{{ t('organizations.filter.all_statuses') }}</option>
            <option v-for="status in statuses" :key="status" :value="status">
                {{ statusLabel(status) }}
            </option>
            <!-- Kôš je stav zoznamu, nie stav firmy – preto až za oddeľovačom. -->
            <option v-if="trashedCount || filters.status === 'trashed'" value="trashed">
                {{ t('organizations.filter.trashed', { count: trashedCount }) }}
            </option>
        </select>

        <select v-model="form.linked" class="w-auto!" :aria-label="t('organizations.filter.linked')">
            <option value="">{{ t('organizations.filter.linked_any') }}</option>
            <option value="any">{{ t('organizations.filter.linked_only') }}</option>
            <option value="none">{{ t('organizations.filter.linked_none') }}</option>
        </select>

        <button v-if="active" type="button" class="btn-secondary btn-sm" @click="reset">
            {{ t('common.filter.reset') }}
        </button>
    </div>
</template>
